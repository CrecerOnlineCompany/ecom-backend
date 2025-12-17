<?php

namespace App\Admin\Controllers;

use App\Models\Room;
use App\Models\Cinema;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class RoomController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Salas';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Room());

        $grid->column('id', __('ID'))->sortable();
        $grid->column('cinema.name', __('Cine'))->sortable();
        $grid->column('name', __('Nombre'))->sortable();
        $grid->column('number', __('Número'));
        $grid->column('type', __('Tipo'));
        $grid->column('total_seats', __('Total asientos'))->sortable();
        $grid->column('is_active', __('Activo'))->bool()->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Room::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('cinema.name', __('Cine'));
        $show->field('name', __('Nombre'));
        $show->field('number', __('Número'));
        $show->field('type', __('Tipo'));
        $show->field('total_seats', __('Total asientos'));
        $show->field('rows', __('Filas'));
        $show->field('columns', __('Columnas'));
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
        $form = new Form(new Room());

        $form->select('cinema_id', __('Cine'))->options(Cinema::pluck('name', 'id'))
            ->rules('required|exists:cinemas,id');
        $form->text('number', __('Número'))->rules('required|string');
        $form->text('name', __('Nombre'))->rules('required|string');
        $form->number('total_seats', __('Total asientos'))->rules('required|integer|min:1');
        $form->select('type', __('Tipo'))->options([
            '2D' => '2D',
            '3D' => '3D',
            'IMAX' => 'IMAX',
            '4DX' => '4DX',
        ])->default('2D');
        $form->number('rows', __('Filas'))->rules('required|integer|min:1');
        $form->number('columns', __('Columnas'))->rules('required|integer|min:1');
        $form->textarea('description', __('Descripción'))->rules('nullable|string');
        $form->switch('is_active', __('Activo'))->default(1);

        return $form;
    }
}
