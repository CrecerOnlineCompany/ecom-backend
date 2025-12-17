<?php

namespace App\Admin\Controllers;

use App\Models\Cinema;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class CinemaController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Cines';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Cinema());

        $grid->column('id', __('ID'))->sortable();
        $grid->column('name', __('Nombre'))->sortable();
        $grid->column('city', __('Ciudad'))->sortable();
        $grid->column('address', __('Dirección'));
        $grid->column('phone', __('Teléfono'));
        $grid->column('email', __('Email'));
        $grid->column('is_active', __('Activo'))->bool()->sortable();
        $grid->column('created_at', __('Creado'))->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Cinema::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('name', __('Nombre'));
        $show->field('city', __('Ciudad'));
        $show->field('address', __('Dirección'));
        $show->field('phone', __('Teléfono'));
        $show->field('email', __('Email'));
        $show->field('latitude', __('Latitud'));
        $show->field('longitude', __('Longitud'));
        $show->field('description', __('Descripción'));
        $show->field('is_active', __('Activo'))->bool();
        $show->field('created_at', __('Creado'));
        $show->field('updated_at', __('Actualizado'));

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new Cinema());

        $form->text('name', __('Nombre'))->rules('required|unique:cinemas,name');
        $form->text('city', __('Ciudad'))->rules('required');
        $form->text('address', __('Dirección'))->rules('required');
        $form->text('phone', __('Teléfono'))->rules('nullable|string');
        $form->email('email', __('Email'))->rules('nullable|email');
        $form->decimal('latitude', __('Latitud'))->rules('nullable|numeric');
        $form->decimal('longitude', __('Longitud'))->rules('nullable|numeric');
        $form->textarea('description', __('Descripción'))->rules('nullable|string');
        $form->switch('is_active', __('Activo'))->default(1);

        return $form;
    }
}
