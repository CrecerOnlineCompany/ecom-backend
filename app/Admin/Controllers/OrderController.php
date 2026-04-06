<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Orders\SyncOrder;
use App\Admin\Actions\Orders\SyncOrderWithPayment;
use App\Models\Order;
use App\Models\Movie;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\ScreeningSeat;
use App\Services\ManualOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class OrderController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Órdenes';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Order());

        $grid->model()->orderByDesc('id');

        $grid->disableCreateButton();

        // Agregar botón personalizado para crear reserva manual
        $grid->tools(function ($tools) {
            $url = route('admin.orders.manual.create');
            $tools->append(
                "<a href=\"{$url}\" class=\"btn btn-sm btn-success\" title=\"Crear Reserva Manual\" data-no-pjax=\"1\">
                    <i class=\"fa fa-plus\"></i> Crear Reserva Manual
                </a>"
            );
        });

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('order_number', 'Nro Orden')->sortable();
        $grid->column('customer_name', 'Cliente')->sortable();
        $grid->column('customer_email', 'Email');
        $grid->column('total_amount', 'Total')->sortable()->display(function ($value) {
            return '$' . number_format($value, 2);
        });
        $grid->column('status', 'Estado')->label([
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'default',
            'expired' => 'default',
            'refunded' => 'primary',
        ]);
        $grid->column('reserved_until', 'Reserva hasta')->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });
        $grid->column('paid_at', 'Pagado')->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });
        $grid->column('created_at', __('admin.created_at'))->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });

        $grid->filter(function ($filter) {
            $filter->like('order_number', 'Nro Orden');
            $filter->like('customer_name', 'Cliente');
            $filter->like('customer_email', 'Email');
            $filter->equal('status', 'Estado')->select([
                'pending' => 'Pendiente',
                'processing' => 'Procesando',
                'completed' => 'Completada',
                'failed' => 'Fallida',
                'cancelled' => 'Cancelada',
                'expired' => 'Expirada',
                'refunded' => 'Reembolsada',
            ]);
            $filter->between('created_at', 'Fecha creación');
        });

        $grid->actions(function ($actions) {
            $actions->add(new SyncOrder());
            $actions->add(new SyncOrderWithPayment());

            $actions->disableEdit();
            $actions->disableDelete();
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));
        $show->panel()->tools(function ($tools) {
            $tools->disableEdit();
            $tools->disableDelete();
        });

        $show->field('id', __('admin.id'));
        $show->field('uuid', 'UUID');
        $show->field('order_number', 'Nro Orden');
        $show->field('status', 'Estado');
        $show->field('total_amount', 'Total')->as(function ($value) {
            return '$' . number_format($value, 2);
        });
        $show->field('currency', 'Moneda');
        $show->field('purchase_device', 'Dispositivo');
        $show->field('ip_address', 'IP');

        $show->divider();

        $show->field('customer_name', 'Cliente');
        $show->field('customer_email', 'Email');
        $show->field('customer_phone', 'Teléfono');

        $show->divider();

        $show->field('screening.movie.title', 'Película');
        $show->field('screening.room.cinema.name', 'Cine');
        $show->field('screening.room.name', 'Sala');
        $show->field('screening.start_time', 'Función')->as(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });

        $show->divider();

        $show->field('reserved_until', 'Reserva hasta');
        $show->field('paid_at', 'Pagado');
        $show->field('cancelled_at', 'Cancelado');
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        $show->relation('tickets', 'Tickets', function ($tickets) {
            $tickets->column('id', __('admin.id'));
            $tickets->column('ticket_number', 'Nro Ticket');
            $tickets->column('price', 'Precio')->display(function ($value) {
                return '$' . number_format($value, 2);
            });
            $tickets->column('status', 'Estado');
            $tickets->column('created_at', 'Creado');
            $tickets->disableCreateButton();
            $tickets->disableActions();
            $tickets->disableFilter();
            $tickets->disablePagination();
        });

        $show->relation('paymentProviderTickets', 'Pagos asociados', function ($payments) {
            $payments->column('id', __('admin.id'));
            $payments->column('paymentProvider.name', 'Proveedor');
            $payments->column('transaction_id', 'Transacción');
            $payments->column('status', 'Estado')->label([
                'pending' => 'warning',
                'processing' => 'info',
                'completed' => 'success',
                'failed' => 'danger',
                'cancelled' => 'default',
                'expired' => 'default',
                'refunded' => 'primary',
                'approved' => 'success',
                'declined' => 'danger',
                'queued' => 'info',
                'finalization_failed' => 'danger',
            ]);
            $payments->column('initiated_at', 'Iniciado')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $payments->column('completed_at', 'Completado')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $payments->disableCreateButton();
            $payments->disableActions();
            $payments->disableFilter();
            $payments->disablePagination();
        });

        $show->relation('screeningSeats', 'Asientos de la orden', function ($seats) {
            $seats->column('id', __('admin.id'));
            $seats->column('seat.seat_code', 'Asiento');
            $seats->column('status', 'Estado');
            $seats->column('reserved_until', 'Reservado hasta');
            $seats->column('sold_at', 'Vendido');
            $seats->disableCreateButton();
            $seats->disableActions();
            $seats->disableFilter();
            $seats->disablePagination();
        });

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new Order());

        $form->display('id', __('admin.id'));
        $form->display('order_number', 'Nro Orden');
        $form->display('customer_name', 'Cliente');
        $form->display('customer_email', 'Email');
        $form->display('total_amount', 'Total');
        $form->display('status', 'Estado');
        $form->display('created_at', __('admin.created_at'));
        $form->display('updated_at', __('admin.updated_at'));

        return $form;
    }

    public function sync(Order $order, Request $request): RedirectResponse
    {
        $forcedPayment = $request->boolean('forced_payment') || $request->boolean('force-payment');

        $params = [
            '--order-number' => $order->order_number,
            '--force' => true,
        ];

        if ($forcedPayment) {
            $params['--forced-payment'] = true;
        }

        $exitCode = Artisan::call('orders:regenerate-tickets', $params);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            admin_error(
                'Sincronizar orden',
                "Falló la ejecución para la orden {$order->order_number}" . ($forcedPayment ? ' (con pago validado)' : '') . ". " . ($output ?: 'Sin salida del comando.')
            );
            return redirect()->route('admin.orders.index');
        }

        admin_success(
            'Sincronizar orden',
            "Comando ejecutado para {$order->order_number}" . ($forcedPayment ? ' (con pago validado)' : '') . ". " . ($output ?: 'Sin salida del comando.')
        );

        return redirect()->route('admin.orders.index');
    }

    /**
     * Mostrar formulario para crear reserva manual
     */
    public function showManualCreateForm(Request $request)
    {
        return view('admin.orders.manual-create');
    }

    /**
     * API: Obtener todas las películas
     */
    public function apiGetMovies(Request $request): JsonResponse
    {
        $movies = Movie::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        return response()->json([
            'success' => true,
            'data' => $movies,
        ]);
    }

    /**
     * API: Obtener screenings de una película
     */
    public function apiGetScreenings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'movie_id' => 'required|exists:movies,id',
        ]);

        $screenings = Screening::where('movie_id', $validated['movie_id'])
            ->where('is_active', true)
            ->with('room.cinema')
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($screening) {
                return [
                    'id' => $screening->id,
                    'start_time' => $screening->start_time->format('Y-m-d H:i'),
                    'price' => (float) $screening->price,
                    'cinema_name' => $screening->room->cinema->name,
                    'room_name' => $screening->room->name,
                    'format' => $screening->format,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $screenings,
        ]);
    }

    /**
     * API: Obtener grilla de asientos para un screening
     */
    public function apiGetSeatingChart(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
            ]);

            $screeningId = $validated['screening_id'];
            
            $screening = Screening::with('room.seats', 'room.cinema')
                ->findOrFail($screeningId);

            if (!$screening->room) {
                return response()->json([
                    'success' => false,
                    'message' => 'La función no tiene una sala asociada',
                ], 400);
            }

            // Obtener estado de los asientos en esta función
            $seatStatuses = ScreeningSeat::where('screening_id', $screeningId)
                ->get()
                ->keyBy('seat_id');

            $seats = [];
            if ($screening->room->seats) {
                $seats = $screening->room->seats
                    ->where('is_active', true)
                    ->map(function ($seat) use ($seatStatuses, $screening) {
                        $statusRecord = $seatStatuses->get($seat->id);
                        $status = $statusRecord ? $statusRecord->status : ScreeningSeat::STATUS_AVAILABLE;
                        
                        $modifier = (float) ($seat->price_modifier ?? 1);
                        if ($modifier <= 0) {
                            $modifier = 1.0;
                        }
                        $seatPrice = round((float) $screening->price * $modifier, 2);
                        
                        return [
                            'id' => $seat->id,
                            'row_number' => $seat->row_number,
                            'seat_number' => $seat->seat_number,
                            'seat_code' => $seat->seat_code,
                            'status' => $status,
                            'price' => $seatPrice,
                        ];
                    })
                    ->groupBy('row_number')
                    ->map(fn($group) => $group->values()->toArray())
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'seats' => $seats,
                    'room_name' => $screening->room->name,
                    'cinema_name' => $screening->room->cinema->name ?? 'Sin cine',
                    'screening_start_time' => $screening->start_time->format('Y-m-d H:i'),
                    'base_price' => (float) $screening->price,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida: ' . json_encode($e->errors()),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Error en apiGetSeatingChart', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'screening_id' => $request->input('screening_id'),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los asientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Crear orden manual
     */
    public function apiStoreManualOrder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'required|exists:seats,id',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
            ]);

            $service = app(ManualOrderService::class);
            $result = $service->createManualOrder(
                $validated['screening_id'],
                $validated['seat_ids'],
                $validated['customer_email'],
                $validated['customer_name'],
                $validated['customer_phone'] ?? null
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'order_id' => $result['order_id'],
                'order_number' => $result['order_number'],
                'redirect_url' => route('admin.orders.show', $result['order_id']),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in apiStoreManualOrder', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida: ' . json_encode($e->errors()),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in apiStoreManualOrder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
