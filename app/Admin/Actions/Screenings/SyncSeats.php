<?php

namespace App\Admin\Actions\Screenings;

use App\Models\Screening;
use OpenAdmin\Admin\Actions\RowAction;

class SyncSeats extends RowAction
{
    public $name = 'Sincronizar asientos';

    public $icon = 'icon-refresh';

    public function handle(Screening $screening)
    {
        $room = $screening->room;
        if (!$room) {
            return $this->response()->error('Sin sala', 'La función no tiene sala asociada.')->refresh();
        }

        $activeSeats = (int) $room->seats()->where('is_active', true)->count();
        if ($activeSeats <= 0 && !is_null($room->total_seats)) {
            $activeSeats = (int) $room->total_seats;
        }

        if ($screening->available_seats !== $activeSeats) {
            $screening->update(['available_seats' => $activeSeats]);
        }

        return $this->response()->success('Asientos sincronizados')->refresh();
    }
}
