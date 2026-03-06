<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use OpenAdmin\Admin\Admin;
use OpenAdmin\Admin\Controllers\Dashboard;
use OpenAdmin\Admin\Layout\Column;
use OpenAdmin\Admin\Layout\Content;
use OpenAdmin\Admin\Layout\Row;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->css_file(Admin::asset("open-admin/css/pages/dashboard.css"))
            ->title('Dashboard')
            ->description('Description...')
            ->row(Dashboard::title())
            ->row(function (Row $row) {

                $row->column(4, function (Column $column) {
                    $column->append(Dashboard::environment());
                });

                $row->column(4, function (Column $column) {
                    $column->append(Dashboard::extensions());
                });

                $row->column(4, function (Column $column) {
                    $column->append(Dashboard::dependencies());
                });
            })
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
