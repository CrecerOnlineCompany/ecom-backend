<?php

namespace App\Admin\Controllers;

use App\Models\PaymentProvider;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class PaymentProviderController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Payment Providers';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new PaymentProvider());

        $grid->column('id', __('admin.id'));
        $grid->column('name', __('admin.name'));
        $grid->column('display_name', __('admin.display_name'));
        $grid->column('is_active', __('admin.status'))->bool();
        $grid->column('requires_redirect', 'Requiere Redirect')->bool();
        $grid->column('supports_webhook', 'Soporta Webhook')->bool();

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(PaymentProvider::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('name', __('admin.name'));
        $show->field('display_name', __('admin.display_name'));
        $show->field('description', __('admin.description'));
        $show->field('icon_url', __('admin.icon_url'));
        $show->field('is_active', __('admin.status'))->bool();
        $show->field('requires_redirect', 'Requiere Redirect')->bool();
        $show->field('supports_webhook', 'Soporta Webhook')->bool();
        $show->field('webhook_url', 'Webhook URL');
        $show->field('webhook_secret', 'Webhook Secret');
        $show->field('config', 'Configuración')->json();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new PaymentProvider());
        $paymentProviderId = request()->route('payment_provider');
        $uniqueRule = 'required|unique:payment_providers,name';
        if ($paymentProviderId) {
            $uniqueRule .= ',' . $paymentProviderId;
        }

        $form->text('name', __('admin.name'))->rules($uniqueRule);
        $form->text('display_name', __('admin.display_name'))->rules('required');
        $form->textarea('description', __('admin.description'));
        $form->url('icon_url', __('admin.icon_url'));
        $form->switch('is_active', __('admin.status'))->default(1);
        $form->switch('requires_redirect', 'Requiere Redirect')->default(0);
        $form->switch('supports_webhook', 'Soporta Webhook')->default(1);
        
        $form->textarea('config', 'Configuración (JSON)')->help('Ej: {"access_token":"APP_USR-...", "currency_id":"ARS"}')->rows(8);

        return $form;
    }
}
