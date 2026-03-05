<?php

namespace App\Admin\Actions\Reservations;

use OpenAdmin\Admin\Grid\Tools\BatchDelete;

class BatchRelease extends BatchDelete
{
    public function __construct()
    {
        $this->name = 'Liberar seleccionadas';
    }
}
