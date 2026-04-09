<?php

namespace App\Admin\Controllers;

use App\Models\Product;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class ProductController extends AdminController
{
    protected $title = 'Productos';

    protected function grid()
    {
        $grid = new Grid(new Product());
        $grid->model()->orderBy('sort_order')->orderBy('name');

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('code', 'Código')->sortable();
        $grid->column('image_path', 'Imagen')->image('', 60, 60);
        $grid->column('name', 'Nombre')->sortable();
        $grid->column('type', 'Tipo')->label([
            Product::TYPE_PRODUCT => 'info',
            Product::TYPE_COMBO => 'success',
        ]);
        $grid->column('unit_price', 'Precio')->display(function ($value) {
            return '$' . number_format((float) $value, 2);
        })->sortable();
        $grid->column('currency', 'Moneda')->sortable();
        $grid->column('is_active', 'Activo')->bool();
        $grid->column('sort_order', 'Orden')->sortable();

        $grid->filter(function ($filter) {
            $filter->like('code', 'Código');
            $filter->like('name', 'Nombre');
            $filter->equal('type', 'Tipo')->select([
                Product::TYPE_PRODUCT => 'Producto',
                Product::TYPE_COMBO => 'Combo',
            ]);
            $filter->equal('is_active', 'Activo')->radio([
                '' => 'Todos',
                1 => 'Sí',
                0 => 'No',
            ]);
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(Product::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('code', 'Código');
        $show->field('image_path', 'Imagen')->image();
        $show->field('name', 'Nombre');
        $show->field('type', 'Tipo');
        $show->field('unit_price', 'Precio');
        $show->field('currency', 'Moneda');
        $show->field('is_active', 'Activo')->bool();
        $show->field('sort_order', 'Orden');
        $show->field('metadata', 'Metadata')->json();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    protected function form()
    {
        $form = new Form(new Product());

        $form->text('code', 'Código')
            ->help('Código único que se envía desde checkout, por ejemplo: POCHO_MEDIUM')
            ->rules('required|max:120|unique:products,code,{{id}}');
        $form->text('name', 'Nombre')->rules('required|max:255');
        $form->image('image_path', 'Imagen')
            ->uniqueName()
            ->move('products')
            ->help('Opcional. Imagen para mostrar en venta manual / checkout.');
        $form->select('type', 'Tipo')
            ->options([
                Product::TYPE_PRODUCT => 'Producto',
                Product::TYPE_COMBO => 'Combo',
            ])
            ->default(Product::TYPE_PRODUCT)
            ->rules('required|in:product,combo');
        $form->currency('unit_price', 'Precio')->symbol('$')->default(0)->rules('required|numeric|min:0');
        $form->text('currency', 'Moneda')->default('ARS')->rules('required|max:8');
        $form->switch('is_active', 'Activo')->default(1);
        $form->number('sort_order', 'Orden')->default(100)->rules('required|integer|min:0');
        $form->textarea('metadata', 'Metadata (JSON)')->help('Opcional')->rules('nullable|json');

        $form->saving(function (Form $form) {
            $form->code = strtoupper(trim((string) $form->code));
            $form->type = strtolower(trim((string) $form->type));
            $form->currency = strtoupper(trim((string) $form->currency));
        });

        return $form;
    }
}
