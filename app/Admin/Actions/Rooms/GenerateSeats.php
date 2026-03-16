<?php

namespace App\Admin\Actions\Rooms;

use OpenAdmin\Admin\Actions\RowAction;

class GenerateSeats extends RowAction
{
    public $name = 'Generar asientos';

    public $icon = 'icon-cogs';

    public function href()
    {
        return route('admin.rooms.generate-seats', ['room' => $this->getKey()]);
    }
}
