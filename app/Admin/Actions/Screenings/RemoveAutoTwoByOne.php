<?php

namespace App\Admin\Actions\Screenings;

use App\Models\Promotion;
use App\Models\Screening;
use OpenAdmin\Admin\Actions\RowAction;

class RemoveAutoTwoByOne extends RowAction
{
    public $name = 'Quitar 2x1';
    public $icon = 'icon-close';

    public function handle(Screening $screening)
    {
        $code = 'AUTO2X1-S' . $screening->id;
        $promotion = Promotion::query()->where('code', $code)->first();

        if (!$promotion) {
            return $this->response()->warning('No existe 2x1 automático para esta función')->refresh();
        }

        $promotion->update([
            'is_active' => false,
            'is_automatic' => false,
        ]);

        return $this->response()->success('2x1 automático desactivado')->refresh();
    }

    public function dialog()
    {
        $this->confirm('¿Desactivar 2x1 automático para esta función?');
    }
}
