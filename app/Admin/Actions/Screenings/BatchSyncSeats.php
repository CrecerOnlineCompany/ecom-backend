<?php

namespace App\Admin\Actions\Screenings;

use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Collection;
use OpenAdmin\Admin\Actions\BatchAction;

class BatchSyncSeats extends BatchAction
{
    public $name = 'Sincronizar asientos';

    public $icon = 'icon-refresh';

    public function handle(Collection $collection)
    {
        $screenings = $collection->load('room');
        $roomIds = $screenings->pluck('room_id')->filter()->unique()->values();

        $activeSeatsByRoom = Seat::query()
            ->whereIn('room_id', $roomIds)
            ->where('is_active', true)
            ->selectRaw('room_id, COUNT(*) as cnt')
            ->groupBy('room_id')
            ->pluck('cnt', 'room_id');

        $updated = 0;
        $skipped = 0;

        foreach ($screenings as $screening) {
            if (!$screening instanceof Screening) {
                $skipped++;
                continue;
            }

            $room = $screening->room;
            if (!$room) {
                $skipped++;
                continue;
            }

            $available = (int) ($activeSeatsByRoom[$room->id] ?? 0);
            if ($available <= 0 && !is_null($room->total_seats)) {
                $available = (int) $room->total_seats;
            }

            if ($screening->available_seats !== $available) {
                $screening->update(['available_seats' => $available]);
                $updated++;
            }
        }

        $message = "Actualizadas: {$updated}. Omitidas: {$skipped}.";
        return $this->response()->success('Asientos sincronizados', $message)->refresh();
    }
}
