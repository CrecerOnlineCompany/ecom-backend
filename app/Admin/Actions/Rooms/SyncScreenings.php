<?php

namespace App\Admin\Actions\Rooms;

use OpenAdmin\Admin\Actions\RowAction;

class SyncScreenings extends RowAction
{
    public $name = 'Sincronizar funciones';

    public $icon = 'icon-refresh';

    public function href()
    {
        return route('admin.rooms.sync-screenings', ['room' => $this->getKey()]);
    }
}
