<?php

namespace App\Admin\Controllers;

use App\Services\PdfGenerator;
use App\Models\Ticket;
use App\Models\TicketDetail;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;
use OpenAdmin\Admin\Form;
use Symfony\Component\HttpFoundation\Response;

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

        $grid->model()->with(['details', 'screening.movie', 'screening.room.cinema', 'seat'])->orderByDesc('id');
        $grid->disableCreateButton();

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('ticket_number', __('admin.ticket_number'))->sortable();
        $grid->column('customer_name', 'Cliente')->sortable();
        $grid->column('movie_title', 'Película')->display(function ($value) {
            return $value ?: optional($this->screening?->movie)->title ?: '-';
        })->sortable();
        $grid->column('cinema_name', 'Cine')->display(function ($value) {
            return $value ?: optional($this->screening?->room?->cinema)->name ?: '-';
        })->sortable();
        $grid->column('screening_start_time', 'Función')->display(function ($value) {
            $date = $value ?: optional($this->screening)->start_time;
            return $date ? \Carbon\Carbon::parse($date)->format('d/m/Y H:i') : '-';
        });
        $grid->column('price', __('admin.price'))->sortable()->display(function ($value) {
            return '$' . number_format($value, 2);
        });
        $grid->column('status', __('admin.ticket_status'))->sortable()->label([
            'confirmed' => 'success',
            'pending_payment' => 'warning',
            'cancelled' => 'danger',
        ]);
        $grid->column('details', 'Asientos')->display(function () {
            $seats = $this->details->pluck('seat_code')->filter()->values();
            if ($seats->isEmpty()) {
                return $this->seat_code ?: (optional($this->seat)->seat_code ?: '-');
            }

            return $seats->take(4)->implode(', ') . ($seats->count() > 4 ? ' ...' : '');
        });
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

        $grid->actions(function ($actions) {
            $actions->disableDelete();
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Ticket::findOrFail($id));
        $show->panel()->tools(function ($tools) use ($id) {
            $tools->disableDelete();

            $downloadUrl = route('admin.tickets.thermal-pdf', ['ticket' => $id]);
            $tools->append(
                "<a class='btn btn-sm btn-primary' target='_blank' href='{$downloadUrl}'>Descargar PDF Térmico</a>"
            );
        });

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
            $show->field('seat_code', 'Asiento(s)')->as(function () use ($show) {
                $ticket = $show->getModel();
                $seats = $ticket->details->pluck('seat_code')->filter()->values();
                if ($seats->isNotEmpty()) {
                    return $seats->implode(', ');
                }

                return $ticket->seat_code ?: (optional($ticket->seat)->seat_code ?: '-');
            });
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

        $show->panel('Orden Asociada', function ($show) {
            $show->field('order.id', 'ID Orden');
            $show->field('order.order_number', 'Nro Orden');
            $show->field('order.status', 'Estado Orden')->as(function ($status) {
                if (!$status) {
                    return '-';
                }

                return match ($status) {
                    'pending' => 'Pendiente',
                    'processing' => 'Procesando',
                    'completed' => 'Completada',
                    'failed' => 'Fallida',
                    'cancelled' => 'Cancelada',
                    'expired' => 'Expirada',
                    'refunded' => 'Reembolsada',
                    default => $status,
                };
            });
            $show->field('order.total_amount', 'Total Orden')->as(function ($value) {
                return $value !== null ? '$' . number_format((float) $value, 2) : '-';
            });
            $show->field('order.paid_at', 'Orden Pagada')->as(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
        });

        $show->relation('paymentProviders', 'Pagos Asociados', function ($payments) {
            $payments->column('id', __('admin.id'));
            $payments->column('paymentProvider.name', 'Proveedor');
            $payments->column('transaction_id', 'Transacción');
            $payments->column('reference_number', 'Referencia');
            $payments->column('status', 'Estado')->label([
                'pending' => 'warning',
                'processing' => 'info',
                'completed' => 'success',
                'failed' => 'danger',
                'cancelled' => 'default',
                'expired' => 'default',
                'refunded' => 'primary',
                'approved' => 'success',
                'declined' => 'danger',
                'queued' => 'info',
                'finalization_failed' => 'danger',
            ]);
            $payments->column('initiated_at', 'Iniciado')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $payments->column('completed_at', 'Completado')->display(function ($value) {
                return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
            });
            $payments->disableCreateButton();
            $payments->disableActions();
            $payments->disableFilter();
            $payments->disablePagination();
        });

        $show->panel('Vista Ticket', function ($show) {
            $show->field('ticket_preview', 'Ticket')->unescape()->as(function () use ($show) {
                $ticket = $show->getModel();
                $movie = $ticket->movie_title ?: optional($ticket->screening?->movie)->title ?: '-';
                $cinema = $ticket->cinema_name ?: optional($ticket->screening?->room?->cinema)->name ?: '-';
                $room = $ticket->room_name ?: optional($ticket->screening?->room)->name ?: '-';
                $start = $ticket->screening_start_time ?: optional($ticket->screening)->start_time;
                $function = $start ? \Carbon\Carbon::parse($start)->format('d/m/Y H:i') : '-';
                $seats = $ticket->details->pluck('seat_code')->filter()->values();
                $seatText = $seats->isNotEmpty()
                    ? $seats->implode(', ')
                    : ($ticket->seat_code ?: (optional($ticket->seat)->seat_code ?: '-'));

                return "
                    <div style='font-family: monospace; border:1px dashed #666; padding:10px; max-width:380px'>
                        <div style='text-align:center;font-weight:bold'>{$cinema}</div>
                        <div style='text-align:center'>{$room}</div>
                        <hr>
                        <div><b>Ticket:</b> {$ticket->ticket_number}</div>
                        <div><b>Película:</b> {$movie}</div>
                        <div><b>Función:</b> {$function}</div>
                        <div><b>Asientos:</b> {$seatText}</div>
                        <div><b>Total:</b> $" . number_format((float) $ticket->price, 2) . "</div>
                    </div>
                ";
            });
        });

        // Mostrar detalles de asientos - editable
        $show->relation('details', 'Asientos Comprados', function ($relation) {
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
            
            $relation->disableCreateButton();
            $relation->disableActions();
            $relation->disableFilter();
            $relation->disablePagination();
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

        $form->tools(function ($tools) {
            $tools->disableDelete();
        });

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
            $form->display('price', 'Precio');
            
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
     * Download thermal-printer-friendly PDF for a ticket.
     */
    public function thermalPdf(int $ticket): Response
    {
        $model = Ticket::with(['details', 'screening.movie', 'screening.room.cinema', 'order', 'seat'])
            ->findOrFail($ticket);

        $pdfGenerator = new PdfGenerator();
        $pdf = $pdfGenerator->generateThermalTicketPdf($model);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', sprintf(
                'inline; filename="ticket-thermal-%s.pdf"',
                $model->ticket_number ?? $model->id
            ));
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
