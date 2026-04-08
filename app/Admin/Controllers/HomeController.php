<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Screening;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use OpenAdmin\Admin\Admin;
use OpenAdmin\Admin\Layout\Content;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->copy()->addDay()->startOfDay();

        $ordersByStatus = Order::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'orders_total' => (int) Order::query()->count(),
            'orders_pending' => (int) (($ordersByStatus['pending'] ?? 0) + ($ordersByStatus['processing'] ?? 0)),
            'orders_completed_today' => (int) Order::query()
                ->where('status', Order::STATUS_COMPLETED)
                ->where('paid_at', '>=', $startOfDay)
                ->where('paid_at', '<', $endOfDay)
                ->count(),
            'tickets_total' => (int) Ticket::query()->count(),
            'tickets_sold_today' => (int) Ticket::query()
                ->where('purchased_at', '>=', $startOfDay)
                ->where('purchased_at', '<', $endOfDay)
                ->count(),
            'screenings_today' => (int) Screening::query()
                ->where('is_active', true)
                ->where('start_time', '>=', $startOfDay)
                ->where('start_time', '<', $endOfDay)
                ->count(),
            'promotions_active' => (int) Promotion::query()
                ->currentlyActive()
                ->count(),
        ];

        $shortcuts = [
            [
                'title' => 'Crear Reserva Manual',
                'description' => 'Venta rápida desde admin',
                'route' => route('admin.orders.manual.create'),
                'icon' => 'fa-plus',
                'btn' => 'btn-success',
            ],
            [
                'title' => 'Órdenes',
                'description' => 'Seguimiento y estados',
                'route' => route('admin.orders.index'),
                'icon' => 'fa-shopping-cart',
                'btn' => 'btn-primary',
            ],
            [
                'title' => 'Funciones',
                'description' => 'Horarios y precios',
                'route' => route('admin.screenings.index'),
                'icon' => 'fa-clock-o',
                'btn' => 'btn-info',
            ],
            [
                'title' => 'Promociones',
                'description' => 'Reglas, 2x1 y descuentos',
                'route' => route('admin.promotions.index'),
                'icon' => 'fa-tags',
                'btn' => 'btn-warning',
            ],
            [
                'title' => 'Entradas',
                'description' => 'Control de tickets',
                'route' => route('admin.tickets.index'),
                'icon' => 'fa-ticket',
                'btn' => 'btn-default',
            ],
            [
                'title' => 'Proveedores de Pago',
                'description' => 'Configuración de cobros',
                'route' => route('admin.payment-providers.index'),
                'icon' => 'fa-credit-card',
                'btn' => 'btn-default',
            ],
        ];

        return $content
            ->css_file(Admin::asset("open-admin/css/pages/dashboard.css"))
            ->title('Dashboard CINEA')
            ->description('Accesos rápidos y métricas operativas')
            ->row(view('admin.dashboard.overview', [
                'stats' => $stats,
                'shortcuts' => $shortcuts,
            ]))
            ->row(view('admin.dashboard.maintenance'));
    }

    public function regenerateOrderTickets(Request $request): RedirectResponse
    {
        $exitCode = Artisan::call('orders:regenerate-tickets', [
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            return redirect()
                ->route('admin.home')
                ->with('maintenance_success', 'Regeneración de tickets ejecutada correctamente.')
                ->with('maintenance_output', $output);
        }

        return redirect()
            ->route('admin.home')
            ->with('maintenance_error', 'La regeneración finalizó con errores.')
            ->with('maintenance_output', $output);
    }
}
