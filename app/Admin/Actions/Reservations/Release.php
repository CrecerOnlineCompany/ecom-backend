<?php

namespace App\Admin\Actions\Reservations;

use OpenAdmin\Admin\Grid\Actions\Delete;

class Release extends Delete
{
    /**
     * @return string
     */
    public function name()
    {
        return 'Liberar';
    }
}
