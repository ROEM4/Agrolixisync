<?php

namespace App\Providers;

use App\Modules\AnalyticsEngine\LixiviationService;
use App\Modules\DeviceManager\DeviceManagerService;
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
        // Singletons de módulos — una instancia por request
        $this->app->singleton(NormalizerService::class);
        $this->app->singleton(LixiviationService::class);
        $this->app->singleton(DeviceManagerService::class);
        $this->app->singleton(HistorianService::class);
        $this->app->singleton(\App\Modules\SensorRealtime\AutoGeneratorService::class);

        $this->app->singleton(IngestionService::class, fn($app) =>
            new IngestionService($app->make(IoTAutoProvisioningService::class))
        );

        $this->app->singleton(SdIngestionService::class, fn($app) =>
            new SdIngestionService($app->make(IoTAutoProvisioningService::class))
        );
    }

    public function boot(): void 
    {
        // Generación orgánica de datos IoT para demo en tiempo real (Simula sensores activos)
        $demoRoutes = ['dashboard', 'realtime', 'analisis', 'lixiviacion', 'alertas', 'historico', 'pf-ficha', 'monitor'];
        $isDemoPage = false;
        foreach($demoRoutes as $r) {
            if(request()->is($r) || request()->is($r.'/*')) {
                $isDemoPage = true;
                break;
            }
        }

        if ($isDemoPage && !app()->runningInConsole()) {
            try {
                // Esto asegura que siempre haya datos de los últimos 10 minutos
                // haciendo que el sistema parezca "vivo" y conectado a sensores reales.
                app(\App\Modules\SensorRealtime\AutoGeneratorService::class)->ensureFreshData();
            } catch (\Exception $e) {
                \Log::error("Error en AutoGenerator: " . $e->getMessage());
            }
        }
    }
}
