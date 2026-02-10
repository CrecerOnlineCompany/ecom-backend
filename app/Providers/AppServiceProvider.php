<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PaymentProviders\PaymentProviderManager;
use App\Services\ScreeningImportExportService;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar Payment Provider Manager como singleton
        $this->app->singleton(PaymentProviderManager::class, function ($app) {
            return new PaymentProviderManager();
        });

        // Registrar Screening Import/Export Service
        $this->app->singleton(ScreeningImportExportService::class, function ($app) {
            return new ScreeningImportExportService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar HTTPS en producción
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
