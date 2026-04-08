<?php

namespace App\Admin\Controllers;

use App\Models\Promotion;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class PromotionController extends AdminController
{
    protected $title = 'Promociones';

    protected function grid()
    {
        $grid = new Grid(new Promotion());
        $grid->model()->orderBy('priority')->orderByDesc('id');

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('code', 'Código');
        $grid->column('name', 'Nombre');
        $grid->column('type', 'Tipo')->label([
            Promotion::TYPE_PERCENTAGE => 'info',
            Promotion::TYPE_FIXED_AMOUNT => 'warning',
            Promotion::TYPE_BXGY => 'success',
        ]);
        $grid->column('is_active', 'Activa')->bool();
        $grid->column('is_automatic', 'Automática')->bool();
        $grid->column('is_stackable', 'Acumulable')->bool();
        $grid->column('priority', 'Prioridad')->sortable();
        $grid->column('starts_at', 'Inicio');
        $grid->column('ends_at', 'Fin');

        $grid->filter(function ($filter) {
            $filter->like('name', 'Nombre');
            $filter->like('code', 'Código');
            $filter->equal('type', 'Tipo')->select([
                Promotion::TYPE_PERCENTAGE => 'Porcentaje',
                Promotion::TYPE_FIXED_AMOUNT => 'Monto fijo',
                Promotion::TYPE_BXGY => 'BxGy (2x1, 3x2, etc)',
            ]);
            $filter->equal('is_active', 'Activa')->radio([
                '' => 'Todos',
                1 => 'Sí',
                0 => 'No',
            ]);
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(Promotion::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('code', 'Código');
        $show->field('name', 'Nombre');
        $show->field('type', 'Tipo');
        $show->field('description', 'Descripción');
        $show->field('is_active', 'Activa')->bool();
        $show->field('is_automatic', 'Automática')->bool();
        $show->field('is_stackable', 'Acumulable')->bool();
        $show->field('priority', 'Prioridad');
        $show->field('starts_at', 'Inicio');
        $show->field('ends_at', 'Fin');
        $show->field('usage_limit', 'Límite de uso');
        $show->field('usage_count', 'Usos acumulados');
        $show->field('settings', 'Configuración')->json();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    protected function form()
    {
        $form = new Form(new Promotion());

        $form->text('code', 'Código')
            ->help('Opcional. Si se completa, puede aplicarse desde checkout con promotion_code.')
            ->rules('nullable|max:80|unique:promotions,code,{{id}}');
        $form->text('name', 'Nombre')->rules('required|max:255');
        $form->select('type', 'Tipo')
            ->options([
                Promotion::TYPE_PERCENTAGE => 'Porcentaje',
                Promotion::TYPE_FIXED_AMOUNT => 'Monto fijo',
                Promotion::TYPE_BXGY => 'BxGy (2x1, 3x2, etc)',
            ])->rules('required');
        $form->textarea('description', 'Descripción');

        $form->switch('is_active', 'Activa')->default(1);
        $form->switch('is_automatic', 'Automática')->default(0)
            ->help('Si está activa, se evalúa en checkout sin necesidad de código.');
        $form->switch('is_stackable', 'Acumulable')->default(0)
            ->help('Si está inactiva, al aplicarse esta promo se detiene la evaluación de las demás.');
        $form->number('priority', 'Prioridad')->default(100)->rules('required|integer');

        $form->datetime('starts_at', 'Inicio');
        $form->datetime('ends_at', 'Fin');
        $form->number('usage_limit', 'Límite de uso')->help('Opcional');
        $form->display('usage_count', 'Usos acumulados')->default(0);

        $form->textarea('settings', 'Configuración (JSON)')
            ->help(
                "Ejemplos:\n" .
                "percentage: {\"percentage\":10,\"target_item_type\":\"ticket_seat\"}\n" .
                "fixed_amount: {\"amount\":500,\"target_item_type\":\"ticket_seat\"}\n" .
                "bxgy (2x1): {\"buy_qty\":2,\"pay_qty\":1,\"target_item_type\":\"ticket_seat\"}"
            )
            ->rules('required|json')
            ->default('{}');

        return $form;
    }
}
