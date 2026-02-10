<?php

namespace App\Admin\Controllers;

use App\Models\Cinema;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class CinemaController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Cines';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new Cinema());

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('name', __('admin.name'))->sortable();
        $grid->column('city', __('admin.city'))->sortable();
        $grid->column('address', __('admin.address'));
        $grid->column('phone', __('admin.phone'));
        $grid->column('email', __('admin.email'));
        $grid->column('is_active', __('admin.status'))->bool()->sortable();
        $grid->column('created_at', __('admin.created_at'))->sortable()->display(function ($value) {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s');
        });

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(Cinema::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('name', __('admin.name'));
        $show->field('city', __('admin.city'));
        $show->field('address', __('admin.address'));
        $show->field('phone', __('admin.phone'));
        $show->field('email', __('admin.email'));
        $show->field('latitude', __('admin.latitude'));
        $show->field('longitude', __('admin.longitude'));
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
        $form = new Form(new Cinema());

        $cinemaId = request()->route('cinema');
        $uniqueRule = 'required|unique:cinemas,name';
        if ($cinemaId) {
            $uniqueRule .= ',' . $cinemaId;
        }

        $form->text('name', __('admin.name'))->rules($uniqueRule);
        $form->text('city', __('admin.city'))->rules('required');
        $form->text('address', __('admin.address'))->rules('required');
        $form->text('phone', __('admin.phone'))->rules('nullable|string');
        $form->email('email', __('admin.email'))->rules('nullable|email');
        $form->decimal('latitude', __('admin.latitude'))->rules('nullable|numeric');
        $form->decimal('longitude', __('admin.longitude'))->rules('nullable|numeric');
        $form->textarea('description', __('admin.description'))->rules('nullable|string');
        $form->switch('is_active', __('admin.status'))->default(1);

        return $form;
    }
}
