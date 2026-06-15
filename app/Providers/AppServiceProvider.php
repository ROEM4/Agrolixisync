<?php

namespace App\Providers;

use App\Modules\AnalyticsEngine\LixiviationService;
use App\Modules\Historian\HistorianService;
use App\Modules\SensorRealtime\IngestionService;
use App\Modules\SensorRealtime\NormalizerService;
use App\Modules\Storage\SdIngestionService;
use App\Services\IoTAutoProvisioningService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NormalizerService::class);
        $this->app->singleton(LixiviationService::class);
        $this->app->singleton(HistorianService::class);

        $this->app->singleton(IngestionService::class, fn($app) =>
            new IngestionService($app->make(IoTAutoProvisioningService::class))
        );

        $this->app->singleton(SdIngestionService::class, fn($app) =>
            new SdIngestionService($app->make(IoTAutoProvisioningService::class))
        );
    }

    public function boot(): void 
    {
        // Sin AutoGenerator — solo datos reales del Arduino
    }
}