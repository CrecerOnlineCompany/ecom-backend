<?php

namespace App\Admin\Actions\Screenings;

use App\Models\Promotion;
use App\Models\Screening;
use OpenAdmin\Admin\Actions\RowAction;

class AssignAutoTwoByOne extends RowAction
{
    public $name = '2x1 auto';
    public $icon = 'icon-tag';

    public function handle(Screening $screening)
    {
        $code = 'AUTO2X1-S' . $screening->id;
        $name = '2x1 Automático - Función #' . $screening->id;

        Promotion::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => Promotion::TYPE_BXGY,
                'description' => 'Promoción automática 2x1 asignada desde el grid de funciones.',
                'is_active' => true,
                'is_automatic' => true,
                'is_stackable' => false,
                'priority' => 10,
                'starts_at' => now(),
                'ends_at' => null,
                'settings' => [
                    'buy_qty' => 2,
                    'pay_qty' => 1,
                    'target_item_type' => 'ticket_seat',
                    'screening_ids' => [$screening->id],
                ],
            ]
        );

        return $this->response()->success('2x1 automático asignado')->refresh();
    }

    public function dialog()
    {
        $this->confirm('¿Asignar 2x1 automático a esta función?');
    }
}
