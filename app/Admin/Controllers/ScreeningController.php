<?php

namespace App\Admin\Controllers;

use App\Models\Screening;
use App\Models\Movie;
use App\Models\Room;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class ScreeningController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Funciones';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Screening());

        $grid->column('id', __('ID'))->sortable();
        $grid->column('movie.title', __('Película'))->sortable();
        $grid->column('room.cinema.name', __('Cine'))->sortable();
        $grid->column('room.name', __('Sala'));
        $grid->column('start_time', __('Inicio'))->sortable();
        $grid->column('price', __('Precio'))->sortable();
        $grid->column('available_seats', __('Asientos disponibles'))->sortable();
        $grid->column('is_active', __('Activo'))->bool()->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Screening::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('movie.title', __('Película'));
        $show->field('room.cinema.name', __('Cine'));
        $show->field('room.name', __('Sala'));
        $show->field('start_time', __('Hora de inicio'));
        $show->field('end_time', __('Hora de fin'));
        $show->field('price', __('Precio'));
        $show->field('format', __('Formato'));
        $show->field('available_seats', __('Asientos disponibles'));
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
        $form = new Form(new Screening());

        $form->select('movie_id', __('Película'))->options(Movie::where('is_active', true)->pluck('title', 'id'))
            ->rules('required|exists:movies,id');
        $form->select('room_id', __('Sala'))->options(function ($id) {
            if ($id) {
                return Room::pluck('name', 'id');
            }
        })->rules('required|exists:rooms,id');
        $form->datetime('start_time', __('Hora de inicio'))->rules('required|date_format:Y-m-d H:i:s');
        $form->datetime('end_time', __('Hora de fin'))->rules('required|date_format:Y-m-d H:i:s');
        $form->decimal('price', __('Precio'), 2)->rules('required|numeric|min:0.01');
        $form->select('format', __('Formato'))->options([
            '2D' => '2D',
            '3D' => '3D',
            'IMAX' => 'IMAX',
            '4DX' => '4DX',
        ])->default('2D');
        $form->switch('is_active', __('Activo'))->default(1);

        return $form;
    }
}
