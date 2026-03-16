<?php

namespace App\Admin\Actions\Orders;

use OpenAdmin\Admin\Actions\RowAction;

class SyncOrder extends RowAction
{
    public $name = 'Generar tickets';

    public $icon = 'icon-refresh';

    public function href()
    {
        return route('admin.orders.sync', ['order' => $this->getKey()]);
    }
}
