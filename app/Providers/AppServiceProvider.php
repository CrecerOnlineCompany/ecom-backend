<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            !class_exists('\Aimeos\Admin\JQAdm\Site\Standard', false)
            && class_exists('\Aimeos\Admin\JQAdm\Locale\Site\Standard')
        ) {
            class_alias('\Aimeos\Admin\JQAdm\Locale\Site\Standard', '\Aimeos\Admin\JQAdm\Site\Standard');
        }

        Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_AR.UTF-8', 'es_AR', 'es_ES.UTF-8', 'es_ES', 'Spanish');

        // Forzar HTTPS en producción
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('admin', function ($user, $class = null, $roles = []) {
            if (isset($user->superuser) && $user->superuser) {
                return true;
            }

            return app('\Aimeos\Shop\Base\Support')->checkUserGroup($user, $roles);
        });
    }
}
