<?php

namespace App\Admin\Controllers;

use App\Models\Screening;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Order;
use App\Models\ScreeningSeat;
use App\Admin\Actions\Screenings\SyncSeats;
use App\Admin\Actions\Screenings\BatchSyncSeats;
use App\Services\ScreeningImportExportService;
use App\Services\SeatInventoryService;
use App\Services\OrderNumberGenerator;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ScreeningController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Funciones';

    protected ScreeningImportExportService $importExportService;
    protected SeatInventoryService $inventoryService;
    protected array $pendingExcludedSeatIds = [];

    public function __construct(
        ScreeningImportExportService $importExportService,
        SeatInventoryService $inventoryService
    ) {
        $this->importExportService = $importExportService;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Screening());

        $grid->column('id', __('admin.id'));
        $grid->column('movie.title', __('admin.movie'));
        $grid->column('room.cinema.name', __('admin.cinema'));
        $grid->column('room.name', __('admin.room'));
        $grid->column('start_time', __('admin.start_time'))->display(function ($value) {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s');
        });
        $grid->column('price', __('admin.price'));
        $grid->column('available_seats', __('admin.available_seats'));
        $grid->column('is_active', __('admin.status'))->bool();

        // Tools (botones de acción)
        $grid->tools(function ($tools) {
            $tools->append('<a class="btn btn-sm btn-success" href="' . route('admin.screenings.export.excel') . '" target="_blank"><i class="fa fa-download"></i> Excel</a>');
            $tools->append('<a class="btn btn-sm btn-info" href="' . route('admin.screenings.export.csv') . '" target="_blank"><i class="fa fa-download"></i> CSV</a>');
            $tools->append('<a class="btn btn-sm btn-warning" href="' . route('admin.screenings.import.form') . '"><i class="fa fa-upload"></i> Importar</a>');
            $tools->batch(function (Grid\Tools\BatchActions $batch) {
                $batch->add(new BatchSyncSeats());
            });
        });

        $grid->actions(function ($actions) {
            $actions->add(new SyncSeats());
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Screening::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('movie.title', __('admin.movie'));
        $show->field('room.cinema.name', __('admin.cinema'));
        $show->field('room.name', __('admin.room'));
        $show->field('start_time', __('admin.start_time'));
        $show->field('end_time', __('admin.end_time'));
        $show->field('price', __('admin.price'));
        $show->field('format', __('admin.format'));
        $show->field('available_seats', __('admin.available_seats'));
        $show->field('is_active', __('admin.status'))->bool();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        $show->relation('adminExcludedReservations', 'Reservas Excluidas', function ($reservations) {
            $reservations->model()->orderByDesc('id');
            $reservations->column('id', __('admin.id'));
            $reservations->column('seat.seat_code', 'Asiento');
            $reservations->column('order.order_number', 'Orden');
            $reservations->column('status', 'Estado')->label([
                ScreeningSeat::STATUS_AVAILABLE => 'success',
                ScreeningSeat::STATUS_RESERVED => 'warning',
                ScreeningSeat::STATUS_SOLD => 'danger',
            ]);
            $reservations->column('reserved_until', 'Reservado hasta')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $reservations->column('created_at', 'Creada')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $reservations->disableCreateButton();
            $reservations->disableActions();
            $reservations->disableFilter();
        });

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new Screening());

        $form->select('movie_id', __('admin.movie'))
            ->options(Movie::where('is_active', true)->pluck('title', 'id'))
            ->load('room_id', route('admin.screenings.movie-rooms.options'))
            ->rules('required|exists:movies,id');
        $form->select('room_id', __('admin.room'))
            ->options(function ($id) {
                if ($id) {
                    return Room::query()->whereKey($id)->pluck('name', 'id');
                }
            })
            ->rules('required|exists:rooms,id');
        $form->datetime('start_time', __('admin.start_time'))->rules('required|date_format:Y-m-d H:i:s');
        $form->datetime('end_time', __('admin.end_time'))->rules('required|date_format:Y-m-d H:i:s');
        $form->decimal('price', __('admin.price'), 2)->rules('required|numeric|min:0.01');
        $form->select('format', __('admin.format'))->options([
            '2D' => '2D',
            '3D' => '3D',
            'IMAX' => 'IMAX',
            '4DX' => '4DX',
        ])->default('2D');
        $form->switch('is_active', __('admin.status'))->default(1);

        $selectedRoomId = request()->input('room_id');
        if (empty($selectedRoomId) && $form->model()->exists) {
            $selectedRoomId = $form->model()->room_id;
        }

        $seatOptionsQuery = Seat::query()
            ->where('is_active', true)
            ->with('room');

        if (!empty($selectedRoomId)) {
            $seatOptionsQuery->where('room_id', (int) $selectedRoomId);
        }

        $seatOptions = $seatOptionsQuery
            ->orderBy('room_id')
            ->orderBy('row_number')
            ->orderBy('seat_number')
            ->get()
            ->mapWithKeys(function (Seat $seat) {
                $roomName = optional($seat->room)->name ?: ('Sala #' . $seat->room_id);
                return [
                    $seat->id => sprintf('%s - %s', $roomName, $seat->seat_code),
                ];
            })
            ->toArray();

        $excludedSeatsField = $form->multipleSelect('excluded_seat_ids', 'Asientos a excluir')
            ->options($seatOptions)
            ->help('Solo muestra asientos de la sala seleccionada. Se crearán reservas administrativas para impedir su venta.');

        if ($form->model()->exists) {
            $preselectedExcludedSeatIds = ScreeningSeat::query()
                ->where('screening_id', $form->model()->id)
                ->where('status', ScreeningSeat::STATUS_RESERVED)
                ->where('reserved_by_type', 'admin_exclusion')
                ->pluck('seat_id')
                ->toArray();

            $excludedSeatsField->value($preselectedExcludedSeatIds);
        }

        $form->ignore(['excluded_seat_ids']);
        $form->html($this->getExcludedSeatsDynamicScript());

        $form->saved(function (Form $form) {
            $inputSeatIds = $this->pendingExcludedSeatIds;
            $screening = $form->model();

            Log::info('Admin screening save: excluded seats payload ready', [
                'screening_id' => $screening->id ?? null,
                'excluded_seat_ids_count' => count($inputSeatIds),
                'excluded_seat_ids' => $inputSeatIds,
            ]);

            if (empty($inputSeatIds)) {
                return;
            }

            $validSeatIds = Seat::query()
                ->where('room_id', $screening->room_id)
                ->where('is_active', true)
                ->whereIn('id', $inputSeatIds)
                ->pluck('id')
                ->toArray();

            Log::info('Admin screening save: seat validation against room', [
                'screening_id' => $screening->id,
                'screening_room_id' => $screening->room_id,
                'input_seat_ids' => $inputSeatIds,
                'valid_seat_ids' => $validSeatIds,
            ]);

            if (empty($validSeatIds)) {
                $inputSeatDetails = Seat::query()
                    ->whereIn('id', $inputSeatIds)
                    ->get(['id', 'room_id', 'seat_code'])
                    ->map(fn (Seat $seat) => [
                        'id' => $seat->id,
                        'room_id' => $seat->room_id,
                        'seat_code' => $seat->seat_code,
                    ])
                    ->toArray();

                Log::warning('Admin screening save: no valid seats for screening room', [
                    'screening_id' => $screening->id,
                    'screening_room_id' => $screening->room_id,
                    'input_seat_ids' => $inputSeatIds,
                    'input_seat_details' => $inputSeatDetails,
                ]);
                admin_warning(
                    'Asientos excluidos',
                    'Los asientos seleccionados no pertenecen a la sala de esta función. Volvé a elegirlos con la sala correcta.'
                );
                return;
            }

            $invalidCount = count(array_diff($inputSeatIds, $validSeatIds));
            $alreadyExcludedSeatIds = ScreeningSeat::query()
                ->where('screening_id', $screening->id)
                ->whereIn('seat_id', $validSeatIds)
                ->where('status', ScreeningSeat::STATUS_RESERVED)
                ->where('reserved_by_type', 'admin_exclusion')
                ->pluck('seat_id')
                ->toArray();

            $seatIdsToReserve = array_values(array_diff($validSeatIds, $alreadyExcludedSeatIds));
            Log::info('Admin screening save: exclusion diff', [
                'screening_id' => $screening->id,
                'valid_seat_ids' => $validSeatIds,
                'already_excluded_seat_ids' => $alreadyExcludedSeatIds,
                'seat_ids_to_reserve' => $seatIdsToReserve,
            ]);

            try {
                DB::transaction(function () use ($screening, $validSeatIds, $seatIdsToReserve) {
                    $this->inventoryService->ensureScreeningSeats($screening->id);

                    if (empty($seatIdsToReserve)) {
                        Log::info('Admin screening save: no new seats to reserve', [
                            'screening_id' => $screening->id,
                            'valid_seat_ids' => $validSeatIds,
                        ]);
                        return;
                    }

                    $order = Order::create([
                        'uuid' => Str::uuid(),
                        'order_number' => OrderNumberGenerator::generate(),
                        'customer_name' => 'Bloqueo Administrativo',
                        'customer_email' => "admin-exclusion-screening-{$screening->id}@local.invalid",
                        'customer_phone' => null,
                        'user_id' => null,
                        'screening_id' => $screening->id,
                        'total_amount' => 0,
                        'currency' => 'ARS',
                        'status' => Order::STATUS_PENDING,
                        'purchase_device' => 'admin',
                        'ip_address' => request()->ip(),
                        'reserved_until' => now()->addYears(20),
                        'payment_data' => [
                            'type' => 'admin_seat_exclusion',
                            'source' => 'admin_screening_form',
                            'seat_ids' => $seatIdsToReserve,
                        ],
                    ]);

                    $reserveResult = $this->inventoryService->reserveSeats(
                        screening_id: $screening->id,
                        seat_ids: $seatIdsToReserve,
                        holder_type: 'admin_exclusion',
                        holder_id: (string) $screening->id,
                        ttl_seconds: 60 * 60 * 24 * 365 * 20,
                        order_id: $order->id
                    );

                    Log::info('Admin screening save: reserveSeats result', [
                        'screening_id' => $screening->id,
                        'order_id' => $order->id,
                        'requested_seat_ids' => $seatIdsToReserve,
                        'reserve_success' => $reserveResult['success'] ?? null,
                        'reserved' => $reserveResult['reserved'] ?? [],
                        'failed' => $reserveResult['failed'] ?? [],
                    ]);

                    if (!$reserveResult['success']) {
                        throw new \RuntimeException(
                            'No se pudieron excluir todos los asientos: ' . json_encode($reserveResult['failed'])
                        );
                    }
                });

                $message = 'Asientos excluidos y reservados correctamente.';
                if ($invalidCount > 0) {
                    $message .= " Se omitieron {$invalidCount} asientos que no pertenecen a la sala.";
                }

                admin_success('Asientos excluidos', $message);
            } catch (\Throwable $e) {
                Log::error('Error creating admin seat exclusions for screening', [
                    'screening_id' => $screening->id,
                    'seat_ids' => $validSeatIds,
                    'error' => $e->getMessage(),
                ]);

                admin_error('Asientos excluidos', 'La función se guardó, pero falló la reserva administrativa de asientos: ' . $e->getMessage());
            } finally {
                $this->pendingExcludedSeatIds = [];
            }
        });

        return $form;
    }

    public function store()
    {
        $this->pendingExcludedSeatIds = $this->extractExcludedSeatIdsFromRequest();
        $roomId = (int) request()->input('room_id');
        if ($roomId > 0) {
            $this->pendingExcludedSeatIds = $this->filterSeatIdsByRoom($this->pendingExcludedSeatIds, $roomId);
            $this->mergeAvailableSeatsFromRoom($roomId);
        }
        request()->request->remove('excluded_seat_ids');
        Log::info('Admin screening store: excluded_seat_ids extracted', [
            'room_id' => $roomId,
            'excluded_seat_ids_count' => count($this->pendingExcludedSeatIds),
            'excluded_seat_ids' => $this->pendingExcludedSeatIds,
        ]);

        return parent::store();
    }

    public function update($id)
    {
        $this->pendingExcludedSeatIds = $this->extractExcludedSeatIdsFromRequest();
        $roomId = (int) request()->input('room_id');
        if ($roomId <= 0) {
            $roomId = (int) Screening::query()->whereKey($id)->value('room_id');
        }
        if ($roomId > 0) {
            $this->pendingExcludedSeatIds = $this->filterSeatIdsByRoom($this->pendingExcludedSeatIds, $roomId);
            $this->mergeAvailableSeatsFromRoom($roomId);
        }
        request()->request->remove('excluded_seat_ids');
        Log::info('Admin screening update: excluded_seat_ids extracted', [
            'screening_id' => (int) $id,
            'room_id' => $roomId,
            'excluded_seat_ids_count' => count($this->pendingExcludedSeatIds),
            'excluded_seat_ids' => $this->pendingExcludedSeatIds,
        ]);

        return parent::update($id);
    }

    private function getExcludedSeatsDynamicScript(): string
    {
        $url = route('admin.screenings.room-seats.options');

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roomSelect = document.querySelector('select.room_id');
    if (!roomSelect) return;

    const getExcludedChoices = function () {
        return window.choices_excluded_seat_ids || null;
    };

    const reloadExcludedSeats = function (roomId) {
        const excludedChoices = getExcludedChoices();
        if (!excludedChoices) return;

        excludedChoices.removeActiveItems();
        excludedChoices.setChoices([], 'id', 'text', true);

        if (!roomId) return;

        admin.ajax.post('{$url}', { query: roomId }, function (response) {
            const seats = Array.isArray(response.data) ? response.data : [];
            excludedChoices.setChoices(seats, 'id', 'text', true);
        });
    };

    roomSelect.addEventListener('change', function () {
        reloadExcludedSeats(this.value);
    });
});
</script>
HTML;
    }

    public function roomSeatsOptions(Request $request)
    {
        $roomId = (int) $request->input('query');
        if ($roomId <= 0) {
            return response()->json([]);
        }

        $data = Seat::query()
            ->where('room_id', $roomId)
            ->where('is_active', true)
            ->orderBy('row_number')
            ->orderBy('seat_number')
            ->get(['id', 'seat_code'])
            ->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'text' => $seat->seat_code,
            ])
            ->values();

        return response()->json($data);
    }

    public function movieRoomsOptions(Request $request)
    {
        $movieId = (int) $request->input('query');
        if ($movieId <= 0) {
            return response()->json([]);
        }

        $data = Room::query()
            ->where('is_active', true)
            ->with('cinema')
            ->orderBy('cinema_id')
            ->orderBy('name')
            ->get(['id', 'cinema_id', 'name'])
            ->map(function (Room $room) {
                $cinemaName = optional($room->cinema)->name ?: 'Sin cine';
                return [
                    'id' => $room->id,
                    'text' => "{$cinemaName} - {$room->name}",
                ];
            })
            ->values();

        return response()->json($data);
    }

    private function extractExcludedSeatIdsFromRequest(): array
    {
        $raw = request()->input('excluded_seat_ids', []);
        Log::info('Admin screening request: excluded_seat_ids raw', [
            'raw_type' => gettype($raw),
            'raw_value' => $raw,
        ]);

        if (is_string($raw)) {
            $trimmed = trim($raw);

            if ($trimmed === '' || $trimmed === '[]') {
                $raw = [];
            } elseif (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                $raw = is_array($decoded) ? $decoded : [];
            } else {
                $raw = explode(',', $trimmed);
            }
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $parsed = collect($raw)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->toArray();

        Log::info('Admin screening request: excluded_seat_ids parsed', [
            'parsed_count' => count($parsed),
            'parsed_value' => $parsed,
        ]);

        return $parsed;
    }

    private function filterSeatIdsByRoom(array $seatIds, int $roomId): array
    {
        if (empty($seatIds)) {
            return [];
        }

        $validIds = Seat::query()
            ->where('room_id', $roomId)
            ->whereIn('id', $seatIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $dropped = array_values(array_diff($seatIds, $validIds));
        if (!empty($dropped)) {
            Log::warning('Admin screening request: dropped excluded_seat_ids from other room', [
                'room_id' => $roomId,
                'dropped_seat_ids' => $dropped,
            ]);
        }

        return array_values(array_unique($validIds));
    }

    private function mergeAvailableSeatsFromRoom(int $roomId): void
    {
        $room = Room::query()
            ->withCount(['seats as active_seats_count' => function ($query) {
                $query->where('is_active', true);
            }])
            ->find($roomId);

        if (!$room) {
            Log::warning('Admin screening: room not found while setting available_seats', [
                'room_id' => $roomId,
            ]);
            return;
        }

        $availableSeats = (int) $room->active_seats_count;
        if ($availableSeats <= 0 && !is_null($room->total_seats)) {
            $availableSeats = (int) $room->total_seats;
        }

        request()->merge(['available_seats' => $availableSeats]);

        Log::info('Admin screening: available_seats set from room', [
            'room_id' => $roomId,
            'available_seats' => $availableSeats,
        ]);
    }

    /**
     * Exportar screenings a Excel
     */
    public function exportExcel()
    {
        return $this->importExportService->exportScreenings('xlsx');
    }

    /**
     * Exportar screenings a CSV
     */
    public function exportCsv()
    {
        return $this->importExportService->exportScreenings('csv');
    }

    /**
     * Mostrar formulario de importación
     */
    public function showImportForm()
    {
        return view('admin.screenings.import');
    }

    public function showWeeklyScreeningsForm()
    {
        $movies = Movie::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        $rooms = Room::query()
            ->where('is_active', true)
            ->with('cinema')
            ->orderBy('cinema_id')
            ->orderBy('name')
            ->get();

        return view('admin.screenings.weekly-screenings', [
            'movies' => $movies,
            'rooms' => $rooms,
        ]);
    }

    public function storeWeeklyScreenings(Request $request)
    {
        $validated = $request->validate([
            'movie_id' => 'required|integer|exists:movies,id',
            'room_id' => 'required|integer|exists:rooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'in:0,1,2,3,4,5,6',
            'start_time' => 'required|date_format:H:i',
            'price' => 'required|numeric|min:0.01',
            'format' => 'required|string',
            'is_active' => 'required|in:0,1',
        ]);

        $movie = Movie::query()
            ->whereKey((int) $validated['movie_id'])
            ->where('is_active', true)
            ->first();

        if (!$movie) {
            return redirect()
                ->back()
                ->withErrors(['movie_id' => 'No se encontró la película activa seleccionada.'])
                ->withInput();
        }

        $room = Room::find((int) $validated['room_id']);
        if (!$room) {
            return redirect()
                ->back()
                ->withErrors(['room_id' => 'No se encontró la sala seleccionada.'])
                ->withInput();
        }

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        if ($endDate->lt($startDate)) {
            return redirect()
                ->back()
                ->withErrors(['end_date' => 'La fecha hasta debe ser mayor o igual a la fecha desde.'])
                ->withInput();
        }

        $durationMinutes = (int) $movie->duration;
        if ($durationMinutes <= 0) {
            return redirect()
                ->back()
                ->withErrors(['duration' => 'La película no tiene duración válida.'])
                ->withInput();
        }

        $weekdays = array_map('intval', $validated['weekdays']);
        $time = $validated['start_time'];
        $price = (float) $validated['price'];
        $format = (string) $validated['format'];
        $isActive = (int) $validated['is_active'] === 1;

        $availableSeats = $this->getAvailableSeatsForRoom($room);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $movie,
            $room,
            $startDate,
            $endDate,
            $weekdays,
            $time,
            $price,
            $format,
            $isActive,
            $durationMinutes,
            $availableSeats,
            &$created,
            &$skipped
        ) {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                    $startTime = Carbon::parse($cursor->format('Y-m-d') . ' ' . $time);

                    $exists = Screening::query()
                        ->where('room_id', $room->id)
                        ->where('start_time', $startTime)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                    } else {
                        Screening::create([
                            'movie_id' => $movie->id,
                            'room_id' => $room->id,
                            'start_time' => $startTime,
                            'end_time' => $startTime->copy()->addMinutes($durationMinutes),
                            'price' => $price,
                            'format' => $format,
                            'available_seats' => $availableSeats,
                            'is_active' => $isActive,
                        ]);
                        $created++;
                    }
                }

                $cursor->addDay();
            }
        });

        return redirect()
            ->route('admin.screenings.weekly-screenings.form')
            ->with('weekly_screenings_result', [
                'created' => $created,
                'skipped' => $skipped,
            ]);
    }

    /**
     * Procesar importación de archivo
     */
    public function importFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $results = $this->importExportService->importScreenings($request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => "Importación completada: {$results['created']} creadas, {$results['updated']} actualizadas, {$results['failed']} errores.",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar archivo: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function getAvailableSeatsForRoom(Room $room): int
    {
        $activeSeats = Seat::query()
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->count();

        if ($activeSeats <= 0 && !is_null($room->total_seats)) {
            $activeSeats = (int) $room->total_seats;
        }

        return (int) $activeSeats;
    }
}
