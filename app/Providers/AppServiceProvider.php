<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PaymentProviders\PaymentProviderManager;
use App\Services\ScreeningImportExportService;
use App\Services\ManualOrderService;
use App\Services\OrderNumberGenerator;
use App\Services\SeatInventoryService;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar Manual Order Service con sus dependencias
        $this->app->singleton(ManualOrderService::class, function ($app) {
            return new ManualOrderService(
                $app->make(OrderNumberGenerator::class),
                $app->make(SeatInventoryService::class)
            );
        });

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
