<?php

namespace App\Admin\Controllers;

use App\Models\Ticket;
use App\Models\TicketDetail;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;
use OpenAdmin\Admin\Form;

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

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('ticket_number', __('admin.ticket_number'))->sortable();
        $grid->column('customer_name', 'Cliente')->sortable();
        $grid->column('movie_title', 'Película')->sortable();
        $grid->column('cinema_name', 'Cine')->sortable();
        $grid->column('screening_start_time', 'Función')->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : '-';
        });
        $grid->column('price', __('admin.price'))->sortable()->display(function ($value) {
            return '$' . number_format($value, 2);
        });
        $grid->column('status', __('admin.ticket_status'))->sortable()->label([
            'confirmed' => 'success',
            'pending_payment' => 'warning',
            'cancelled' => 'danger',
        ]);
        $grid->column('purchased_at', 'Fecha Compra')->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });

        // Filtros
        $grid->filter(function ($filter) {
            $filter->like('customer_name', 'Cliente');
            $filter->like('movie_title', 'Película');
            $filter->equal('status', 'Estado')->select([
                'confirmed' => 'Confirmado',
                'pending_payment' => 'Pago Pendiente',
                'cancelled' => 'Cancelado',
            ]);
            $filter->between('purchased_at', 'Fecha Compra');
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Ticket::findOrFail($id));

        $show->panel('Información de la Compra', function ($show) {
            $show->field('id', __('admin.id'));
            $show->field('ticket_number', 'Número de Entrada');
            $show->field('status', 'Estado')->as(function ($status) {
                return match($status) {
                    'confirmed' => '✓ Confirmado',
                    'pending_payment' => '⏳ Pago Pendiente',
                    'cancelled' => '✗ Cancelado',
                    default => $status,
                };
            });
            $show->field('purchased_at', 'Fecha Compra')->as(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $show->field('payment_method', 'Método de Pago');
        });

        $show->panel('Información del Cliente', function ($show) {
            $show->field('customer_name', 'Nombre');
            $show->field('customer_email', 'Email');
            $show->field('customer_phone', 'Teléfono');
        });

        $show->panel('Información de la Función', function ($show) {
            $show->field('movie_title', 'Película');
            $show->field('cinema_name', 'Cine');
            $show->field('room_name', 'Sala');
            $show->field('screening_start_time', 'Hora')->as(function ($value) {
                return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
            });
            $show->field('screening_format', 'Formato');
        });

        $show->panel('Información de Precios', function ($show) {
            $show->field('original_price', 'Precio Original')->as(function ($value) {
                return '$' . number_format($value, 2);
            });
            $show->field('discount_amount', 'Descuento')->as(function ($value) {
                return '-$' . number_format($value, 2);
            });
            $show->field('discount_code', 'Código Descuento');
            $show->field('price', 'Precio Total')->as(function ($value) {
                return '$' . number_format($value, 2);
            });
        });

        // Mostrar detalles de asientos - editable
        $show->hasMany('details', 'Asientos Comprados', function ($relation) {
            $relation->column('id', 'ID')->sortable();
            $relation->column('seat_code', 'Asiento');
            $relation->column('row_number', 'Fila');
            $relation->column('seat_number', 'Número');
            $relation->column('price', 'Precio')->display(function ($value) {
                return '$' . number_format($value, 2);
            });
            $relation->column('status', 'Estado')->label([
                'confirmed' => 'success',
                'cancelled' => 'danger',
                'used' => 'info',
            ])->display(function ($status) {
                return match($status) {
                    'confirmed' => 'Confirmado',
                    'cancelled' => 'Cancelado',
                    'used' => 'Validado',
                    default => $status,
                };
            });
            $relation->column('qr_code', 'QR Generado')->display(function ($value) {
                return $value ? '✓' : '✗';
            });
            $relation->column('used_at', 'Validado en')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            
            // Acciones directas en la tabla
            $relation->actions(function ($actions) {
                $actions->disableDelete();
                $actions->disableCreate();
            });
        });

        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    /**
     * Make a form builder for editing tickets.
     */
    protected function form()
    {
        $form = new Form(new Ticket());

        $form->display('id', __('admin.id'));
        $form->text('ticket_number', 'Número de Entrada')->readonly();
        
        $form->select('status', 'Estado')->options([
            'pending_payment' => 'Pago Pendiente',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
        ])->required();

        $form->text('customer_name', 'Nombre Cliente')->readonly();
        $form->email('customer_email', 'Email Cliente')->readonly();
        $form->text('customer_phone', 'Teléfono Cliente');

        $form->text('movie_title', 'Película')->readonly();
        $form->text('cinema_name', 'Cine')->readonly();
        $form->text('room_name', 'Sala')->readonly();

        $form->decimal('price', 'Precio Total')->readonly();
        $form->text('payment_method', 'Método de Pago');
        $form->decimal('discount_amount', 'Monto Descuento');
        $form->text('discount_code', 'Código Descuento');

        // Tabla editable de detalles (asientos)
        $form->hasMany('details', 'Asientos de la Entrada', function ($form) {
            $form->display('id', 'ID');
            $form->display('seat_code', 'Código Asiento');
            $form->display('row_number', 'Fila');
            $form->display('seat_number', 'Número');
            $form->display('price', 'Precio')->display(function ($value) {
                return '$' . number_format($value, 2);
            });
            
            $form->select('status', 'Estado')->options([
                'confirmed' => 'Confirmado',
                'cancelled' => 'Cancelado',
                'used' => 'Validado',
            ]);
            
            $form->datetime('used_at', 'Validado en');
        });

        $form->display('purchased_at', 'Fecha Compra');
        $form->display('created_at', __('admin.created_at'));
        $form->display('updated_at', __('admin.updated_at'));

        return $form;
    }

    /**
     * Update detail status (asiento) from inline editing
     */
    public function updateDetail($ticketId, $detailId)
    {
        $detail = TicketDetail::where('ticket_id', $ticketId)->findOrFail($detailId);
        
        if (request()->has('status')) {
            $detail->update(['status' => request('status')]);
        }
        
        if (request()->has('used_at')) {
            $detail->update(['used_at' => request('used_at')]);
        }

        return response()->json(['message' => 'Detail actualizado exitosamente', 'detail' => $detail]);
    }

    /**
     * Cancel a detail (seat) from ticket
     */
    public function cancelDetail($ticketId, $detailId)
    {
        $detail = TicketDetail::where('ticket_id', $ticketId)->findOrFail($detailId);
        
        if ($detail->status === 'used') {
            return response()->json(['message' => 'No se puede cancelar un asiento validado'], 422);
        }

        $detail->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Asiento cancelado exitosamente']);
    }
}
