<?php

namespace App\Jobs;

use App\Models\Location;
use App\Services\ThesisMetrics\ThesisMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Job: Calcular Métricas de Tesis
 * 
 * Se ejecuta automáticamente según Schedule.
 * Calcula TAR, PDS, NCES para todas las ubicaciones.
 */
class CalculateThesisMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ?Location $location;
    private Carbon $periodStart;
    private Carbon $periodEnd;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ?Location $location = null,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null
    ) {
        $this->location = $location;
        $this->periodStart = $periodStart ?? Carbon::now()->subMonth();
        $this->periodEnd = $periodEnd ?? Carbon::now();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $locations = $this->location 
                ? collect([$this->location]) 
                : Location::all();

            foreach ($locations as $location) {
                Log::info("Calculating thesis metrics for location: {$location->name}");

                $service = new ThesisMetricsService(
                    $location,
                    $this->periodStart,
                    $this->periodEnd
                );

                // Validar datos
                $validation = $service->validateData();
                if (!$validation['is_valid']) {
                    Log::warning("Data validation issues for {$location->name}:", $validation['issues']);
                    continue;
                }

                // Calcular todos los indicadores
                $metric = $service->calculateAll();

                Log::info("Thesis metrics calculated successfully", [
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'tar' => $metric->tar_minutes,
                    'pds' => $metric->pds_percentage,
                    'nces' => $metric->nces_difference,
                    'period' => "{$this->periodStart->format('Y-m-d')} to {$this->periodEnd->format('Y-m-d')}"
                ]);
            }

            Log::info("Thesis metrics calculation job completed successfully");

        } catch (\Exception $e) {
            Log::error("Error calculating thesis metrics: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CalculateThesisMetricsJob failed: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);
    }
}
