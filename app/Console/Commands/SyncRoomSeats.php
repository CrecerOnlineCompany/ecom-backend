<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\Screening;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRoomSeats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rooms:sync-seats {--room-id= : Sincronizar una sala específica}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Sincroniza los asientos disponibles de las funciones con el tamaño actual de las salas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $roomId = $this->option('room-id');

            if ($roomId) {
                $this->syncRoom($roomId);
            } else {
                $this->syncAllRooms();
            }

            $this->info('✓ Sincronización completada exitosamente.');
        } catch (\Exception $e) {
            $this->error('✗ Error durante la sincronización: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sincroniza todas las salas
     */
    private function syncAllRooms()
    {
        $rooms = Room::all();
        
        if ($rooms->isEmpty()) {
            $this->warn('No hay salas para sincronizar.');
            return;
        }

        $this->info("Sincronizando " . $rooms->count() . " sala(s)...\n");

        foreach ($rooms as $room) {
            $this->syncRoom($room->id);
        }
    }

    /**
     * Sincroniza una sala específica
     */
    private function syncRoom($roomId)
    {
        $room = Room::find($roomId);

        if (!$room) {
            $this->error("Sala con ID {$roomId} no encontrada.");
            return;
        }

        // Obtener el total de asientos activos en la sala
        $totalActiveSeats = $room->seats()->where('is_active', true)->count();

        // Obtener funciones activas de la sala
        $screenings = Screening::where('room_id', $room->id)
            ->where('is_active', true)
            ->get();

        $screeningsUpdated = 0;
        
        foreach ($screenings as $screening) {
            // Calcular asientos disponibles = total de asientos - asientos vendidos
            $soldSeats = $screening->tickets()->count();
            $availableSeats = max(0, $totalActiveSeats - $soldSeats);

            // Si cambió, actualizar
            if ($screening->available_seats != $availableSeats) {
                $screening->update(['available_seats' => $availableSeats]);
                $screeningsUpdated++;

                $this->line(
                    "  📽️  Función: {$screening->movie->title} - " .
                    "{$screening->start_time->format('d/m/Y H:i')} | " .
                    "Asientos: {$availableSeats}"
                );
            }
        }

        $this->info(
            "✓ Sala '{$room->name}' - Total asientos: {$totalActiveSeats} | " .
            "Funciones actualizadas: {$screeningsUpdated}"
        );
    }
}
