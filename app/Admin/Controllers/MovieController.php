<?php

namespace App\Admin\Controllers;

use App\Models\Movie;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

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

        $grid->column('id', __('ID'))->sortable();
        $grid->column('title', __('Título'))->sortable();
        $grid->column('genre', __('Género'))->sortable();
        $grid->column('duration', __('Duración (min)'))->sortable();
        $grid->column('rating', __('Clasificación'));
        $grid->column('language', __('Idioma'))->sortable();
        $grid->column('release_date', __('Fecha de estreno'))->sortable();
        $grid->column('is_active', __('Activo'))->bool()->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Movie::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('title', __('Título'));
        $show->field('description', __('Descripción'));
        $show->field('genre', __('Género'));
        $show->field('duration', __('Duración (min)'));
        $show->field('rating', __('Clasificación'));
        $show->field('director', __('Director'));
        $show->field('cast', __('Reparto'));
        $show->field('language', __('Idioma'));
        $show->field('poster_url', __('URL del póster'));
        $show->field('trailer_url', __('URL del tráiler'));
        $show->field('release_date', __('Fecha de estreno'));
        $show->field('end_date', __('Fecha de fin'));
        $show->field('is_active', __('Activo'))->bool();
        $show->field('created_at', __('Creado'));
        $show->field('updated_at', __('Actualizado'));

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new Movie());

        $form->text('title', __('Título'))->rules('required|unique:movies,title');
        $form->textarea('description', __('Descripción'))->rules('nullable|string');
        $form->text('genre', __('Género'))->rules('required');
        $form->number('duration', __('Duración (min)'))->rules('required|integer|min:1');
        $form->text('rating', __('Clasificación'))->rules('nullable|string')
            ->help('Ejemplos: G, PG, PG-13, R, NC-17');
        $form->text('director', __('Director'))->rules('nullable|string');
        $form->text('cast', __('Reparto'))->rules('nullable|string');
        $form->select('language', __('Idioma'))->options([
            'es' => 'Español',
            'en' => 'Inglés',
            'fr' => 'Francés',
            'de' => 'Alemán',
        ])->default('es');
        $form->url('poster_url', __('URL del póster'))->rules('nullable|url');
        $form->url('trailer_url', __('URL del tráiler'))->rules('nullable|url');
        $form->date('release_date', __('Fecha de estreno'))->rules('required|date');
        $form->date('end_date', __('Fecha de fin'))->rules('nullable|date|after:release_date');
        $form->switch('is_active', __('Activo'))->default(1);

        return $form;
    }
}
