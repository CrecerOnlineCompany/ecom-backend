<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Orders\SyncOrder;
use App\Admin\Actions\Orders\SyncOrderWithPayment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class OrderController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Órdenes';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Order());

        $grid->model()->orderByDesc('id');

        $grid->disableCreateButton();

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('order_number', 'Nro Orden')->sortable();
        $grid->column('customer_name', 'Cliente')->sortable();
        $grid->column('customer_email', 'Email');
        $grid->column('total_amount', 'Total')->sortable()->display(function ($value) {
            return '$' . number_format($value, 2);
        });
        $grid->column('status', 'Estado')->label([
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'default',
            'expired' => 'default',
            'refunded' => 'primary',
        ]);
        $grid->column('reserved_until', 'Reserva hasta')->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });
        $grid->column('paid_at', 'Pagado')->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });
        $grid->column('created_at', __('admin.created_at'))->sortable()->display(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });

        $grid->filter(function ($filter) {
            $filter->like('order_number', 'Nro Orden');
            $filter->like('customer_name', 'Cliente');
            $filter->like('customer_email', 'Email');
            $filter->equal('status', 'Estado')->select([
                'pending' => 'Pendiente',
                'processing' => 'Procesando',
                'completed' => 'Completada',
                'failed' => 'Fallida',
                'cancelled' => 'Cancelada',
                'expired' => 'Expirada',
                'refunded' => 'Reembolsada',
            ]);
            $filter->between('created_at', 'Fecha creación');
        });

        $grid->actions(function ($actions) {
            $actions->add(new SyncOrder());
            $actions->add(new SyncOrderWithPayment());

            $actions->disableEdit();
            $actions->disableDelete();
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));
        $show->panel()->tools(function ($tools) {
            $tools->disableEdit();
            $tools->disableDelete();
        });

        $show->field('id', __('admin.id'));
        $show->field('uuid', 'UUID');
        $show->field('order_number', 'Nro Orden');
        $show->field('status', 'Estado');
        $show->field('total_amount', 'Total')->as(function ($value) {
            return '$' . number_format($value, 2);
        });
        $show->field('currency', 'Moneda');
        $show->field('purchase_device', 'Dispositivo');
        $show->field('ip_address', 'IP');

        $show->divider();

        $show->field('customer_name', 'Cliente');
        $show->field('customer_email', 'Email');
        $show->field('customer_phone', 'Teléfono');

        $show->divider();

        $show->field('screening.movie.title', 'Película');
        $show->field('screening.room.cinema.name', 'Cine');
        $show->field('screening.room.name', 'Sala');
        $show->field('screening.start_time', 'Función')->as(function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s') : '-';
        });

        $show->divider();

        $show->field('reserved_until', 'Reserva hasta');
        $show->field('paid_at', 'Pagado');
        $show->field('cancelled_at', 'Cancelado');
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        $show->relation('tickets', 'Tickets', function ($tickets) {
            $tickets->column('id', __('admin.id'));
            $tickets->column('ticket_number', 'Nro Ticket');
            $tickets->column('price', 'Precio')->display(function ($value) {
                return '$' . number_format($value, 2);
            });
            $tickets->column('status', 'Estado');
            $tickets->column('created_at', 'Creado');
            $tickets->disableCreateButton();
            $tickets->disableActions();
            $tickets->disableFilter();
            $tickets->disablePagination();
        });

        $show->relation('paymentProviderTickets', 'Pagos asociados', function ($payments) {
            $payments->column('id', __('admin.id'));
            $payments->column('paymentProvider.name', 'Proveedor');
            $payments->column('transaction_id', 'Transacción');
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

        $show->relation('screeningSeats', 'Asientos de la orden', function ($seats) {
            $seats->column('id', __('admin.id'));
            $seats->column('seat.seat_code', 'Asiento');
            $seats->column('status', 'Estado');
            $seats->column('reserved_until', 'Reservado hasta');
            $seats->column('sold_at', 'Vendido');
            $seats->disableCreateButton();
            $seats->disableActions();
            $seats->disableFilter();
            $seats->disablePagination();
        });

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new Order());

        $form->display('id', __('admin.id'));
        $form->display('order_number', 'Nro Orden');
        $form->display('customer_name', 'Cliente');
        $form->display('customer_email', 'Email');
        $form->display('total_amount', 'Total');
        $form->display('status', 'Estado');
        $form->display('created_at', __('admin.created_at'));
        $form->display('updated_at', __('admin.updated_at'));

        return $form;
    }

    public function sync(Order $order, Request $request): RedirectResponse
    {
        $forcedPayment = $request->boolean('forced_payment') || $request->boolean('force-payment');

        $params = [
            '--order-number' => $order->order_number,
            '--force' => true,
        ];

        if ($forcedPayment) {
            $params['--forced-payment'] = true;
        }

        $exitCode = Artisan::call('orders:regenerate-tickets', $params);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            admin_error(
                'Sincronizar orden',
                "Falló la ejecución para la orden {$order->order_number}" . ($forcedPayment ? ' (con pago validado)' : '') . ". " . ($output ?: 'Sin salida del comando.')
            );
            return redirect()->route('admin.orders.index');
        }

        admin_success(
            'Sincronizar orden',
            "Comando ejecutado para {$order->order_number}" . ($forcedPayment ? ' (con pago validado)' : '') . ". " . ($output ?: 'Sin salida del comando.')
        );

        return redirect()->route('admin.orders.index');
    }
}
