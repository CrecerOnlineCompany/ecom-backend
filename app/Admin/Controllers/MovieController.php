<?php

namespace App\Admin\Controllers;

use App\Models\Movie;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;
use Illuminate\Support\Facades\Storage;

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
}
