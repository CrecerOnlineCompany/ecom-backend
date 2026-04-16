<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Reservations\BatchRelease;
use App\Admin\Actions\Reservations\Release;
use App\Models\ScreeningSeat;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class ReservationController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Reservas';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new ScreeningSeat());
        $selectedStatus = request()->input('status');
        $businessTimezone = config('app.screening_timezone', 'America/Argentina/Buenos_Aires');

        $grid->model()
            ->with(['order', 'seat', 'screening.movie', 'screening.room.cinema'])
            ->when(empty($selectedStatus), function ($query) {
                $query->where('status', ScreeningSeat::STATUS_RESERVED);
            })
            ->orderByDesc('id');

        $grid->disableCreateButton();

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('order.order_number', 'Orden');
        $grid->column('movie_title', 'Película')->display(function () {
            return optional(optional($this->screening)->movie)->title ?: '-';
        });
        $grid->column('cinema_name', 'Cine')->display(function () {
            return optional(optional(optional($this->screening)->room)->cinema)->name ?: '-';
        });
        $grid->column('room_name', 'Sala')->display(function () {
            return optional(optional($this->screening)->room)->name ?: '-';
        });
        $grid->column('screening.start_time', 'Función')->display(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i')
                : '-';
        });
        $grid->column('seat.seat_code', 'Asiento');
        $grid->column('reserved_until', 'Reservado hasta')->sortable()->display(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });
        $grid->column('reserved_by_type', 'Tipo Reserva');
        $grid->column('reserved_by_id', 'ID Reserva');
        $grid->column('status', 'Estado')->using([
            ScreeningSeat::STATUS_AVAILABLE => 'Disponible',
            ScreeningSeat::STATUS_RESERVED => 'Reservada',
            ScreeningSeat::STATUS_SOLD => 'Vendida',
        ])->label([
            ScreeningSeat::STATUS_AVAILABLE => 'success',
            ScreeningSeat::STATUS_RESERVED => 'warning',
            ScreeningSeat::STATUS_SOLD => 'danger',
        ]);
        $grid->column('created_at', 'Creada')->sortable()->display(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });
        $grid->column('updated_at', 'Actualizada')->sortable()->display(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });

        $grid->filter(function ($filter) {
            $filter->expand();
            $filter->disableIdFilter();

            $filter->like('order.order_number', 'Nro Orden');
            $filter->like('screening.movie.title', 'Película');
            $filter->like('screening.room.cinema.name', 'Cine');
            $filter->like('screening.room.name', 'Sala');
            $filter->like('seat.seat_code', 'Asiento');

            $filter->equal('order_id', 'ID Orden');
            $filter->equal('screening_id', 'ID Función');
            $filter->equal('seat_id', 'ID Asiento');
            $filter->equal('status', 'Estado')->select([
                ScreeningSeat::STATUS_AVAILABLE => 'Disponible',
                ScreeningSeat::STATUS_RESERVED => 'Reservada',
                ScreeningSeat::STATUS_SOLD => 'Vendida',
            ]);
            $filter->equal('reserved_by_type', 'Tipo Reserva');
            $filter->like('reserved_by_id', 'ID Reserva');
            $filter->between('screening.start_time', 'Fecha Función');
            $filter->between('reserved_until', 'Reservado hasta');
            $filter->between('created_at', 'Creada');
        });

        $grid->actions(function ($actions) {
            $actions->disableEdit();
            $actions->disableDelete();
            $actions->add(new Release());
        });

        $grid->tools(function (Grid\Tools $tools) {
            $tools->batch(function (Grid\Tools\BatchActions $batch) {
                $batch->disableDelete();
                $batch->add(new BatchRelease());
            });
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(ScreeningSeat::findOrFail($id));
        $businessTimezone = config('app.screening_timezone', 'America/Argentina/Buenos_Aires');
        $show->panel()->tools(function ($tools) {
            $tools->disableEdit();
            $tools->disableDelete();
        });

        $show->field('id', __('admin.id'));
        $show->field('order.order_number', 'Orden');
        $show->field('screening.movie.title', 'Película');
        $show->field('screening.room.cinema.name', 'Cine');
        $show->field('screening.room.name', 'Sala');
        $show->field('screening.start_time', 'Función')->as(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });
        $show->field('seat.seat_code', 'Asiento');
        $show->field('reserved_by_type', 'Tipo Reserva');
        $show->field('reserved_by_id', 'ID Reserva');
        $show->field('reserved_until', 'Reservado hasta')->as(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });
        $show->field('created_at', __('admin.created_at'))->as(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });
        $show->field('updated_at', __('admin.updated_at'))->as(function ($value) use ($businessTimezone) {
            return $value
                ? \Carbon\Carbon::parse($value, 'UTC')->setTimezone($businessTimezone)->format('d/m/Y H:i:s')
                : '-';
        });

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new ScreeningSeat());

        $form->display('id', __('admin.id'));
        $form->display('order.order_number', 'Orden');
        $form->display('screening.movie.title', 'Película');
        $form->display('screening.start_time', 'Función');
        $form->display('seat.seat_code', 'Asiento');
        $form->display('status', 'Estado');
        $form->display('reserved_until', 'Reservado hasta');
        $form->display('created_at', __('admin.created_at'));
        $form->display('updated_at', __('admin.updated_at'));

        return $form;
    }

    /**
     * Release reservation instead of deleting inventory row.
     */
    public function destroy($id)
    {
        try {
            $ids = collect(explode(',', (string) $id))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No se recibieron reservas para liberar.',
                ]);
            }

            $released = 0;
            $skippedAvailable = 0;
            $skippedSold = 0;

            ScreeningSeat::whereIn('id', $ids)->get()->each(function (ScreeningSeat $reservation) use (&$released, &$skippedAvailable, &$skippedSold) {
                if ($reservation->status === ScreeningSeat::STATUS_RESERVED) {
                    $reservation->update([
                        'status' => ScreeningSeat::STATUS_AVAILABLE,
                        'reserved_until' => null,
                        'reserved_by_type' => null,
                        'reserved_by_id' => null,
                        'order_id' => null,
                    ]);
                    $released++;
                    return;
                }

                if ($reservation->status === ScreeningSeat::STATUS_AVAILABLE) {
                    $skippedAvailable++;
                    return;
                }

                if ($reservation->status === ScreeningSeat::STATUS_SOLD) {
                    $skippedSold++;
                }
            });

            $found = $released + $skippedAvailable + $skippedSold;
            if ($found === 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'No se encontraron reservas para procesar.',
                ]);
            }

            $message = "Procesadas: {$found}. Liberadas: {$released}.";
            if ($skippedAvailable > 0 || $skippedSold > 0) {
                $message .= " Omitidas: disponibles {$skippedAvailable}, vendidas {$skippedSold}.";
            }

            return response()->json([
                'status' => true,
                'message' => $message,
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage() ?: trans('admin.delete_failed'),
            ]);
        }
    }
}
