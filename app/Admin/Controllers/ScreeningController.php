<?php

namespace App\Admin\Controllers;

use App\Models\Screening;
use App\Models\Movie;
use App\Models\Room;
use App\Services\ScreeningImportExportService;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;
use Illuminate\Http\Request;

class ScreeningController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Funciones';

    protected ScreeningImportExportService $importExportService;

    public function __construct(ScreeningImportExportService $importExportService)
    {
        $this->importExportService = $importExportService;
    }

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Screening());

        $grid->column('id', __('admin.id'));
        $grid->column('movie.title', __('admin.movie'));
        $grid->column('room.cinema.name', __('admin.cinema'));
        $grid->column('room.name', __('admin.room'));
        $grid->column('start_time', __('admin.start_time'))->display(function ($value) {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s');
        });
        $grid->column('price', __('admin.price'));
        $grid->column('available_seats', __('admin.available_seats'));
        $grid->column('is_active', __('admin.status'))->bool();

        // Tools (botones de acción)
        $grid->tools(function ($tools) {
            $tools->append('<a class="btn btn-sm btn-success" href="' . route('admin.screenings.export.excel') . '" target="_blank"><i class="fa fa-download"></i> Excel</a>');
            $tools->append('<a class="btn btn-sm btn-info" href="' . route('admin.screenings.export.csv') . '" target="_blank"><i class="fa fa-download"></i> CSV</a>');
            $tools->append('<a class="btn btn-sm btn-warning" href="' . route('admin.screenings.import.form') . '"><i class="fa fa-upload"></i> Importar</a>');
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Screening::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('movie.title', __('admin.movie'));
        $show->field('room.cinema.name', __('admin.cinema'));
        $show->field('room.name', __('admin.room'));
        $show->field('start_time', __('admin.start_time'));
        $show->field('end_time', __('admin.end_time'));
        $show->field('price', __('admin.price'));
        $show->field('format', __('admin.format'));
        $show->field('available_seats', __('admin.available_seats'));
        $show->field('is_active', __('admin.status'))->bool();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new Screening());

        $form->select('movie_id', __('admin.movie'))->options(Movie::where('is_active', true)->pluck('title', 'id'))
            ->rules('required|exists:movies,id');
        $form->select('room_id', __('admin.room'))->options(function ($id) {
            if ($id) {
                return Room::pluck('name', 'id');
            }
        })->rules('required|exists:rooms,id');
        $form->datetime('start_time', __('admin.start_time'))->rules('required|date_format:Y-m-d H:i:s');
        $form->datetime('end_time', __('admin.end_time'))->rules('required|date_format:Y-m-d H:i:s');
        $form->decimal('price', __('admin.price'), 2)->rules('required|numeric|min:0.01');
        $form->select('format', __('admin.format'))->options([
            '2D' => '2D',
            '3D' => '3D',
            'IMAX' => 'IMAX',
            '4DX' => '4DX',
        ])->default('2D');
        $form->switch('is_active', __('admin.status'))->default(1);

        return $form;
    }

    /**
     * Exportar screenings a Excel
     */
    public function exportExcel()
    {
        return $this->importExportService->exportScreenings('xlsx');
    }

    /**
     * Exportar screenings a CSV
     */
    public function exportCsv()
    {
        return $this->importExportService->exportScreenings('csv');
    }

    /**
     * Mostrar formulario de importación
     */
    public function showImportForm()
    {
        return view('admin.screenings.import');
    }

    /**
     * Procesar importación de archivo
     */
    public function importFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $results = $this->importExportService->importScreenings($request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => "Importación completada: {$results['created']} creadas, {$results['updated']} actualizadas, {$results['failed']} errores.",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar archivo: ' . $e->getMessage(),
            ], 422);
        }
    }
}
