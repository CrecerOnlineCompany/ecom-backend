<?php

namespace App\Admin\Controllers;

use App\Models\Ticket;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class TicketController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Entradas';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Ticket());

        $grid->column('id', __('ID'))->sortable();
        $grid->column('ticket_number', __('Número de entrada'))->sortable();
        $grid->column('user.name', __('Usuario'))->sortable();
        $grid->column('screening.movie.title', __('Película'))->sortable();
        $grid->column('seat.seat_code', __('Asiento'));
        $grid->column('price', __('Precio'))->sortable();
        $grid->column('status', __('Estado'))->sortable();
        $grid->column('created_at', __('Comprado'))->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Ticket::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('ticket_number', __('Número de entrada'));
        $show->field('user.name', __('Usuario'));
        $show->field('user.email', __('Email del usuario'));
        $show->field('screening.movie.title', __('Película'));
        $show->field('screening.start_time', __('Hora de función'));
        $show->field('screening.room.cinema.name', __('Cine'));
        $show->field('seat.seat_code', __('Asiento'));
        $show->field('price', __('Precio'));
        $show->field('status', __('Estado'));
        $show->field('qr_code', __('Código QR'));
        $show->field('used_at', __('Usado en'));
        $show->field('created_at', __('Creado'));
        $show->field('updated_at', __('Actualizado'));

        return $show;
    }
}
