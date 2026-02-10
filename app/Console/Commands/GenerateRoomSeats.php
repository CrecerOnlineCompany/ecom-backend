<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\Seat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $room = Room::find($roomId);

        if (!$room) {
            $this->error("Sala con ID {$roomId} no encontrada.");
            return;
        }

        // Validar que tenga filas y columnas
        if (!$room->rows || !$room->columns) {
            $this->error("La sala '{$room->name}' no tiene filas y columnas definidas.");
            return;
        }

        $existingSeats = $room->seats()->count();

        // Si ya tiene asientos y no forzamos, preguntar
        if ($existingSeats > 0 && !$force) {
            if (!$this->confirm(
                "La sala '{$room->name}' ya tiene {$existingSeats} asientos. ¿Deseas regenerarlos?",
                false
            )) {
                $this->line("Omitiendo sala '{$room->name}'");
                return;
            }
        }

        DB::transaction(function () use ($room, $existingSeats, $force) {
            // Eliminar solo asientos SIN tickets
            $seatsWithoutTickets = $room->seats()
                ->whereDoesntHave('tickets')
                ->delete();

            if ($seatsWithoutTickets > 0) {
                $this->line("  🗑️  {$seatsWithoutTickets} asientos sin tickets eliminados");
            }

            $seats = [];
            $seatCount = 0;
            $newSeatsCreated = 0;

            // Generar asientos por fila y columna
            for ($row = 1; $row <= $room->rows; $row++) {
                for ($col = 1; $col <= $room->columns; $col++) {
                    $rowLetter = chr(64 + $row); // A, B, C, etc.
                    $seatCode = $rowLetter . $col;
                    $seatCount++;

                    // Verificar si el asiento ya existe
                    $existingSeat = Seat::where('room_id', $room->id)
                        ->where('row_number', $row)
                        ->where('seat_number', $col)
                        ->first();

                    if ($existingSeat) {
                        // Asiento ya existe, mantenerlo
                        continue;
                    }

                    // Crear nuevo asiento
                    $seats[] = [
                        'room_id' => $room->id,
                        'row_number' => $row,
                        'seat_number' => $col,
                        'seat_code' => $seatCode,
                        'type' => 'regular',
                        'price_modifier' => 0,
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

            $this->info("✓ Sala '{$room->name}' - {$seatCount} asientos totales (Nuevos: {$newSeatsCreated}, Filas: {$room->rows}, Columnas: {$room->columns})");
        });
    }
}
