<?php

namespace App\Admin\Controllers;

use App\Models\Room;
use App\Models\Cinema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use App\Admin\Actions\Rooms\GenerateSeats;
use App\Admin\Actions\Rooms\SyncScreenings;
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

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('cinema.name', __('admin.cinema'))->sortable();
        $grid->column('name', __('admin.name'))->sortable();
        $grid->column('number', __('admin.room_number'));
        $grid->column('type', __('admin.type'));
        $grid->column('total_seats', __('admin.total_seats'))->sortable();
        $grid->column('is_active', __('admin.status'))->bool()->sortable();

        $grid->actions(function ($actions) {
            $actions->add(new GenerateSeats());
            $actions->add(new SyncScreenings());
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Room::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('cinema.name', __('admin.cinema'));
        $show->field('name', __('admin.name'));
        $show->field('number', __('admin.room_number'));
        $show->field('type', __('admin.type'));
        $show->field('total_seats', __('admin.total_seats'));
        $show->field('rows', __('admin.rows'));
        $show->field('columns', __('admin.columns'));
        $show->field('description', __('admin.description'));
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
        $form = new Form(new Room());

        $form->select('cinema_id', __('admin.cinema'))->options(Cinema::pluck('name', 'id'))
            ->rules('required|exists:cinemas,id');
        $form->text('number', __('admin.room_number'))->rules('required|string');
        $form->text('name', __('admin.name'))->rules('required|string');
        $form->number('total_seats', __('admin.total_seats'))->rules('required|integer|min:1');
        $form->select('type', __('admin.type'))->options([
            '2D' => '2D',
            '3D' => '3D',
            'IMAX' => 'IMAX',
            '4DX' => '4DX',
        ])->default('2D');
        $form->number('rows', __('admin.rows'))->rules('required|integer|min:1');
        $form->number('columns', __('admin.columns'))->rules('required|integer|min:1');
        $form->textarea('description', __('admin.description'))->rules('nullable|string');
        $form->switch('is_active', __('admin.status'))->default(1);

        return $form;
    }

    public function generateSeats(Room $room): RedirectResponse
    {
        $exitCode = Artisan::call('rooms:generate-seats', [
            '--room-id' => $room->id,
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            admin_success('Asientos', "Asientos regenerados correctamente. {$output}");
        } else {
            admin_error('Asientos', "Error regenerando asientos. {$output}");
        }

        return redirect()->route('admin.rooms.index');
    }

    public function syncScreenings(Room $room): RedirectResponse
    {
        $exitCode = Artisan::call('rooms:sync-seats', [
            '--room-id' => $room->id,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            admin_success('Funciones', "Asientos disponibles sincronizados. {$output}");
        } else {
            admin_error('Funciones', "Error sincronizando funciones. {$output}");
        }

        return redirect()->route('admin.rooms.index');
    }
}
