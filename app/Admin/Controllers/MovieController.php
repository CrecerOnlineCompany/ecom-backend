<?php

namespace App\Admin\Controllers;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Screening;
use App\Models\Seat;
use App\Admin\Actions\Movies\WeeklyScreeningsForm;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MovieController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Películas';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Movie());

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('poster_image', __('admin.poster_image'))->display(function ($value) {
            return $value ? '<img src="/images/movies/'.$value.'" style="max-width:100px;height:auto;" />' : '-';
        })->sortable();
        $grid->column('title', __('admin.title'))->sortable();
        $grid->column('genre', __('admin.genre'))->sortable();
        $grid->column('duration', __('admin.duration'))->sortable();
        $grid->column('rating', __('admin.rating'));
        $grid->column('language', __('admin.language'))->sortable();
        $grid->column('release_date', __('admin.release_date'))->sortable()->display(function ($value) {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        });
        $grid->column('is_active', __('admin.status'))->bool()->sortable();

        $grid->actions(function ($actions) {
            $actions->add(new WeeklyScreeningsForm());
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Movie::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('title', __('admin.title'));
        $show->field('description', __('admin.description'));
        $show->field('genre', __('admin.genre'));
        $show->field('duration', __('admin.duration'));
        $show->field('rating', __('admin.rating'));
        $show->field('director', __('admin.director'));
        $show->field('cast', __('admin.cast'));
        $show->field('language', __('admin.language'));
        $show->field('poster_image', __('admin.poster_image'))->display(function ($value) {
            return $value ? '<img src="/images/movies/'.$value.'" style="max-width:300px;height:auto;" />' : '-';
        });
        $show->field('poster_url', __('admin.poster_url'));
        $show->field('trailer_url', __('admin.trailer_url'));
        $show->field('release_date', __('admin.release_date'));
        $show->field('end_date', __('admin.end_date'));
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
        $form = new Form(new Movie());

        $movieId = request()->route('movie');
        $uniqueRule = 'required|unique:movies,title';
        if ($movieId) {
            $uniqueRule .= ',' . $movieId;
        }

        $form->text('title', __('admin.title'))->rules($uniqueRule);
        $form->textarea('description', __('admin.description'))->rules('nullable|string');
        $form->text('genre', __('admin.genre'))->rules('required');
        $form->number('duration', __('admin.duration'))->rules('required|integer|min:1');
        $form->text('rating', __('admin.rating'))->rules('nullable|string')
            ->help(__('admin.rating_help'));
        $form->text('director', __('admin.director'))->rules('nullable|string');
        $form->text('cast', __('admin.cast'))->rules('nullable|string');
        $form->select('language', __('admin.language'))->options([
            'es' => __('admin.spanish'),
            'en' => __('admin.english'),
            'fr' => __('admin.french'),
            'de' => __('admin.german'),
        ])->default('es');
        $form->file('poster_image', __('admin.poster_image'))
            ->disk('admin')
            ->rules('nullable|mimes:jpeg,png,jpg,gif,webp|max:5120')
            ->help(__('admin.poster_image_help'));
        $form->url('poster_url', __('admin.poster_url'))->rules('nullable|url');
        $form->url('trailer_url', __('admin.trailer_url'))->rules('nullable|url');
        $form->date('release_date', __('admin.release_date'))->rules('required|date');
        $form->date('end_date', __('admin.end_date'))->rules('nullable|date|after:release_date');
        $form->switch('is_active', __('admin.status'))->default(1);

        $form->deleting(function (Form $form) {
            $this->handleImageDelete($form->model());
        });

        return $form;
    }

    /**
     * Handle image delete
     */
    private function handleImageDelete(Movie $movie)
    {
        if ($movie->poster_image) {
            Storage::disk('admin')->delete($movie->poster_image);
        }
    }

    public function showWeeklyScreeningsForm(Movie $movie)
    {
        $rooms = Room::query()
            ->where('is_active', true)
            ->with('cinema')
            ->orderBy('cinema_id')
            ->orderBy('name')
            ->get();

        return view('admin.movies.weekly-screenings', [
            'movie' => $movie,
            'rooms' => $rooms,
        ]);
    }

    public function storeWeeklyScreenings(Request $request, Movie $movie)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'in:0,1,2,3,4,5,6',
            'start_time' => 'required|date_format:H:i',
            'price' => 'required|numeric|min:0.01',
            'format' => 'required|string',
            'is_active' => 'required|in:0,1',
        ]);

        $room = Room::find((int) $validated['room_id']);
        if (!$room) {
            return redirect()
                ->back()
                ->withErrors(['room_id' => 'No se encontró la sala seleccionada.'])
                ->withInput();
        }

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        if ($endDate->lt($startDate)) {
            return redirect()
                ->back()
                ->withErrors(['end_date' => 'La fecha hasta debe ser mayor o igual a la fecha desde.'])
                ->withInput();
        }

        $durationMinutes = (int) $movie->duration;
        if ($durationMinutes <= 0) {
            return redirect()
                ->back()
                ->withErrors(['duration' => 'La película no tiene duración válida.'])
                ->withInput();
        }

        $weekdays = array_map('intval', $validated['weekdays']);
        $time = $validated['start_time'];
        $price = (float) $validated['price'];
        $format = (string) $validated['format'];
        $isActive = (int) $validated['is_active'] === 1;

        $availableSeats = $this->getAvailableSeatsForRoom($room);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $movie,
            $room,
            $startDate,
            $endDate,
            $weekdays,
            $time,
            $price,
            $format,
            $isActive,
            $durationMinutes,
            $availableSeats,
            &$created,
            &$skipped
        ) {
            $businessTimezone = config('app.screening_timezone', 'America/Argentina/Buenos_Aires');
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                    $startTime = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $cursor->format('Y-m-d') . ' ' . $time,
                        $businessTimezone
                    )->utc();

                    $exists = Screening::query()
                        ->where('room_id', $room->id)
                        ->where('start_time', $startTime)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                    } else {
                        Screening::create([
                            'movie_id' => $movie->id,
                            'room_id' => $room->id,
                            'start_time' => $startTime,
                            'end_time' => $startTime->copy()->addMinutes($durationMinutes),
                            'price' => $price,
                            'format' => $format,
                            'available_seats' => $availableSeats,
                            'is_active' => $isActive,
                        ]);
                        $created++;
                    }
                }

                $cursor->addDay();
            }
        });

        return redirect()
            ->route('admin.movies.weekly-screenings.form', ['movie' => $movie->id])
            ->with('weekly_screenings_result', [
                'created' => $created,
                'skipped' => $skipped,
            ]);
    }

    private function getAvailableSeatsForRoom(Room $room): int
    {
        $activeSeats = Seat::query()
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->count();

        if ($activeSeats <= 0 && !is_null($room->total_seats)) {
            $activeSeats = (int) $room->total_seats;
        }

        return (int) $activeSeats;
    }
}
