<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\Seat;
use App\Models\ScreeningSeat;
use App\Models\TicketDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateRoomSeats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rooms:generate-seats {--room-id= : Generar asientos para una sala específica} {--force : Regenerar asientos existentes}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Genera los asientos para las salas basado en filas y columnas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $roomId = $this->option('room-id');
            $force = $this->option('force');

            if ($roomId) {
                $this->generateRoom($roomId, $force);
            } else {
                $this->generateAllRooms($force);
            }

            $this->info('✓ Generación de asientos completada exitosamente.');
        } catch (\Exception $e) {
            $this->error('✗ Error durante la generación: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Genera asientos para todas las salas
     */
    private function generateAllRooms($force = false)
    {
        $rooms = Room::all();
        
        if ($rooms->isEmpty()) {
            $this->warn('No hay salas disponibles.');
            return;
        }

        $this->info("Procesando " . $rooms->count() . " sala(s)...\n");

        foreach ($rooms as $room) {
            $this->generateRoom($room->id, $force);
        }
    }

    /**
     * Genera asientos para una sala específica
     */
    private function generateRoom($roomId, $force = false)
    {
        $logger = Log::channel('room_seats');
        $room = Room::find($roomId);

        if (!$room) {
            $this->error("Sala con ID {$roomId} no encontrada.");
            $logger->warning('Room seats generation skipped: room not found', [
                'room_id' => $roomId,
            ]);
            return;
        }

        // Validar que tenga filas y columnas
        if (!$room->rows || !$room->columns) {
            $this->error("La sala '{$room->name}' no tiene filas y columnas definidas.");
            $logger->warning('Room seats generation skipped: missing rows/columns', [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'rows' => $room->rows,
                'columns' => $room->columns,
            ]);
            return;
        }

        $existingSeats = $room->seats()->count();
        $logger->info('Room seats generation started', [
            'room_id' => $room->id,
            'room_name' => $room->name,
            'rows' => $room->rows,
            'columns' => $room->columns,
            'existing_seats' => $existingSeats,
            'force' => (bool) $force,
        ]);

        // Si ya tiene asientos y no forzamos, preguntar
        if ($existingSeats > 0 && !$force) {
            if (!$this->confirm(
                "La sala '{$room->name}' ya tiene {$existingSeats} asientos. ¿Deseas regenerarlos?",
                false
            )) {
                $this->line("Omitiendo sala '{$room->name}'");
                $logger->info('Room seats generation cancelled by user', [
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                ]);
                return;
            }
        }

        DB::transaction(function () use ($room, $existingSeats, $force) {
            $logger = Log::channel('room_seats');
            $cutoff = now()->startOfDay();
            $roomSeatIdsQuery = $room->seats()->select('id');

            $futureTicketSeatIds = TicketDetail::query()
                ->whereIn('seat_id', $roomSeatIdsQuery)
                ->whereHas('screening', function ($query) use ($cutoff) {
                    $query->where('start_time', '>=', $cutoff);
                })
                ->pluck('seat_id')
                ->unique()
                ->values()
                ->toArray();

            $reservedSeatIds = ScreeningSeat::query()
                ->whereIn('seat_id', $roomSeatIdsQuery)
                ->whereIn('status', [ScreeningSeat::STATUS_RESERVED, ScreeningSeat::STATUS_SOLD])
                ->whereHas('screening', function ($query) use ($cutoff) {
                    $query->where('start_time', '>=', $cutoff);
                })
                ->pluck('seat_id')
                ->unique()
                ->values()
                ->toArray();

            $protectedSeatIds = array_values(array_unique(array_merge(
                $futureTicketSeatIds,
                $reservedSeatIds
            )));

            // Eliminar solo asientos SIN tickets
            $deleteQuery = $room->seats()
                ->whereDoesntHave('tickets')
                ->whereDoesntHave('ticketDetails');

            if (!empty($protectedSeatIds)) {
                $deleteQuery->whereNotIn('id', $protectedSeatIds);
            }

            $seatsWithoutTickets = $deleteQuery->delete();

            if ($seatsWithoutTickets > 0) {
                $this->line("  🗑️  {$seatsWithoutTickets} asientos sin tickets eliminados");
            }

            $logger->info('Room seats cleanup completed', [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'deleted_seats' => $seatsWithoutTickets,
                'protected_seats_count' => count($protectedSeatIds),
                'protected_ticket_details' => count($futureTicketSeatIds),
                'protected_reservations' => count($reservedSeatIds),
                'cutoff' => $cutoff->toDateTimeString(),
            ]);

            $seats = [];
            $seatCount = 0;
            $newSeatsCreated = 0;
            $updatedSeatsCount = 0;

            // Generar asientos por fila y columna
            for ($row = 1; $row <= $room->rows; $row++) {
                for ($col = 1; $col <= $room->columns; $col++) {
                    $seatCode = (string) ((($row - 1) * $room->columns) + $col);
                    $seatCount++;

                    // Verificar si el asiento ya existe
                    $existingSeat = Seat::where('room_id', $room->id)
                        ->where('row_number', $row)
                        ->where('seat_number', $col)
                        ->first();

                    if ($existingSeat) {
                        // Asiento ya existe, actualizar seat_code si es necesario
                        if ($existingSeat->seat_code !== $seatCode) {
                            $existingSeat->update(['seat_code' => $seatCode]);
                            $updatedSeatsCount++;
                        }
                        continue;
                    }

                    // Crear nuevo asiento
                    $seats[] = [
                        'room_id' => $room->id,
                        'row_number' => $row,
                        'seat_number' => $col,
                        'seat_code' => $seatCode,
                        'type' => 'regular',
                        'price_modifier' => 1.0,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $newSeatsCreated++;

                    // Insertar en lotes de 500
                    if (count($seats) === 500) {
                        Seat::insert($seats);
                        $seats = [];
                    }
                }
            }

            // Insertar asientos restantes
            if (!empty($seats)) {
                Seat::insert($seats);
            }

            // Actualizar total_seats en la sala
            $room->update(['total_seats' => $seatCount]);

            $this->info("✓ Sala '{$room->name}' - {$seatCount} asientos totales (Nuevos: {$newSeatsCreated}, Actualizados: {$updatedSeatsCount}, Filas: {$room->rows}, Columnas: {$room->columns})");
            $logger->info('Room seats generation finished', [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'total_seats' => $seatCount,
                'new_seats' => $newSeatsCreated,
                'updated_seats' => $updatedSeatsCount,
            ]);
        });
    }
}
