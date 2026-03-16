<?php

namespace App\Admin\Actions\Orders;

use OpenAdmin\Admin\Actions\RowAction;

class SyncOrderWithPayment extends RowAction
{
    public $name = 'Sincronizar con pago';

    public $icon = 'icon-money';

    public function href()
    {
        return route('admin.orders.sync', ['order' => $this->getKey(), 'forced_payment' => 1]);
    }
}
