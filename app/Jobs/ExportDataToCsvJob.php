<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Models\Location;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job: Exportar Datos a CSV
 * 
 * Genera automáticamente archivos CSV con datos de sensores,
 * análisis y métricas de tesis.
 * 
 * Nombre del archivo: AGROlixisync_export_YYYY_MM_DD_[type].csv
 */
class ExportDataToCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DEFAULT_RETENTION_DAYS = 30;

    private ?Location $location;
    private string $exportType;
    private Carbon $periodStart;
    private Carbon $periodEnd;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $exportType = 'FULL_EXPORT',
        ?Location $location = null,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null
    ) {
        $this->exportType = $exportType;
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
                Log::info("Starting CSV export for location: {$location->name}", [
                    'type' => $this->exportType,
                ]);

                $dataExport = $this->createExportRecord($location);

                try {
                    // Generar datos según tipo
                    $data = $this->generateExportData($location);

                    if (empty($data)) {
                        Log::warning("No data to export for location {$location->name}");
                        $dataExport->update([
                            'export_status' => 'COMPLETED',
                            'record_count' => 0,
                            'completed_at' => now(),
                        ]);
                        continue;
                    }

                    // Generar CSV
                    $csvContent = $this->generateCsv($data);
                    $filename = $this->generateFilename($location);
                    $filepath = "exports/{$filename}";

                    // Guardar archivo
                    Storage::disk('local')->put($filepath, $csvContent);

                    // Actualizar registro
                    $fileSize = strlen($csvContent);
                    $dataExport->update([
                        'filename' => $filename,
                        'filepath' => $filepath,
                        'file_size_bytes' => $fileSize,
                        'record_count' => count($data),
                        'export_status' => 'COMPLETED',
                        'completed_at' => now(),
                    ]);

                    Log::info("CSV export completed", [
                        'location' => $location->name,
                        'filename' => $filename,
                        'records' => count($data),
                        'size_bytes' => $fileSize,
                    ]);

                } catch (\Exception $e) {
                    Log::error("CSV export failed for {$location->name}: {$e->getMessage()}");
                    $dataExport->update([
                        'export_status' => 'FAILED',
                        'error_message' => $e->getMessage(),
                        'completed_at' => now(),
                    ]);
                }
            }

            // Limpiar exportes antiguos
            $this->cleanupOldExports();

            Log::info("Data export job completed");

        } catch (\Exception $e) {
            Log::error("Error in ExportDataToCsvJob: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Crear registro de exportación
     */
    private function createExportRecord(Location $location): DataExport
    {
        return DataExport::create([
            'location_id' => $location->id,
            'export_type' => $this->exportType,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'export_status' => 'PROCESSING',
            'triggered_by' => 'scheduler',
            'started_at' => now(),
            'storage_location' => 'LOCAL_STORAGE',
        ]);
    }

    /**
     * Generar datos según tipo de exportación
     */
    private function generateExportData(Location $location): array
    {
        return match($this->exportType) {
            'FULL_EXPORT' => $this->getFullExportData($location),
            'ANALYSIS_EXPORT' => $this->getAnalysisExportData($location),
            'THESIS_METRICS' => $this->getThesisMetricsExportData($location),
            'SYSTEM_TESTS' => $this->getSystemTestsExportData($location),
            'PERIODIC_EXPORT' => $this->getFullExportData($location),
            default => [],
        };
    }

    /**
     * Datos para exportación completa
     */
    private function getFullExportData(Location $location): array
    {
        $data = [];

        // Encabezados
        $data[] = [
            'timestamp',
            'sensor_id',
            'sensor_name',
            'depth_cm',
            'conductivity_uS_cm',
            'humidity_percent',
            'temperature_celsius',
            'location_name'
        ];

        // Lecturas
        $readings = $location->sensors()
            ->with('readings')
            ->get()
            ->flatMap(function ($sensor) {
                return $sensor->readings()
                    ->whereBetween('recorded_at', [
                        $this->periodStart,
                        $this->periodEnd
                    ])
                    ->get()
                    ->map(fn($reading) => [
                        $reading->recorded_at->format('Y-m-d H:i:s'),
                        $sensor->id,
                        $sensor->name ?? $sensor->code,
                        $sensor->depth,
                        round($reading->conductivity, 2),
                        round($reading->humidity, 2),
                        round($reading->temperature, 2),
                        $reading->location->name ?? ''
                    ]);
            });

        return array_merge($data, $readings->toArray());
    }

    /**
     * Datos para exportación de análisis
     */
    private function getAnalysisExportData(Location $location): array
    {
        $data = [
            [
                'analysis_date',
                'location_name',
                'delta_ce_uS_cm',
                'ratio_ce',
                'lixiviation_detected',
                'risk_level',
                'risk_percentage',
                'confidence_level'
            ]
        ];

        $analyses = $location->analyses()
            ->whereBetween('analyzed_at', [$this->periodStart, $this->periodEnd])
            ->get()
            ->map(fn($analysis) => [
                $analysis->analyzed_at->format('Y-m-d H:i:s'),
                $location->name,
                round($analysis->delta_conductivity, 2),
                round($analysis->conductivity_profundo / max($analysis->conductivity_superficial, 1), 3),
                $analysis->lixiviation_detected ? 'SI' : 'NO',
                $analysis->risk_level,
                round($analysis->risk_percentage, 2),
                $analysis->confidence_level
            ]);

        return array_merge($data, $analyses->toArray());
    }

    /**
     * Datos para exportación de métricas de tesis
     */
    private function getThesisMetricsExportData(Location $location): array
    {
        $data = [
            [
                'period_start',
                'period_end',
                'tar_minutes',
                'tar_sample_count',
                'pds_percentage',
                'pds_tests_count',
                'pds_correct',
                'pds_false_positives',
                'pds_false_negatives',
                'nces_control_avg',
                'nces_experimental_avg',
                'nces_difference'
            ]
        ];

        $metrics = $location->thesisMetrics()
            ->whereBetween('period_end_date', [$this->periodStart, $this->periodEnd])
            ->get()
            ->map(fn($metric) => [
                $metric->period_start_date->format('Y-m-d'),
                $metric->period_end_date->format('Y-m-d'),
                round($metric->tar_minutes ?? 0, 2),
                $metric->tar_sample_count,
                round($metric->pds_percentage ?? 0, 2),
                $metric->pds_total_tests,
                $metric->pds_correct_detections,
                $metric->pds_false_positives,
                $metric->pds_false_negatives,
                round($metric->nces_control_avg ?? 0, 2),
                round($metric->nces_experimental_avg ?? 0, 2),
                round($metric->nces_difference ?? 0, 2)
            ]);

        return array_merge($data, $metrics->toArray());
    }

    /**
     * Datos para exportación de pruebas del sistema
     */
    private function getSystemTestsExportData(Location $location): array
    {
        $data = [
            [
                'validated_date',
                'system_detected',
                'actual_anomaly',
                'match_result',
                'confidence_level',
                'is_correct'
            ]
        ];

        $tests = $location->systemTests()
            ->whereBetween('validated_at', [$this->periodStart, $this->periodEnd])
            ->where('included_in_pds', true)
            ->get()
            ->map(fn($test) => [
                $test->validated_at->format('Y-m-d H:i:s'),
                $test->system_detected_anomaly ? 'SI' : 'NO',
                $test->actual_anomaly_existed ? 'SI' : 'NO',
                $test->match_result,
                $test->system_confidence,
                $test->isCorrect() ? 'SI' : 'NO'
            ]);

        return array_merge($data, $tests->toArray());
    }

    /**
     * Generar contenido CSV
     */
    private function generateCsv(array $data): string
    {
        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', $cell) . '"', $row)) . "\n";
        }
        return $csv;
    }

    /**
     * Generar nombre de archivo
     */
    private function generateFilename(Location $location): string
    {
        $date = now()->format('Y_m_d');
        $type = strtolower($this->exportType);
        $sanitizedLocation = str_replace(' ', '_', $location->name);

        return "AGROlixisync_export_{$date}_{$type}_{$sanitizedLocation}.csv";
    }

    /**
     * Limpiar exportes antiguos
     */
    private function cleanupOldExports(): void
    {
        $days = config('agrolixisync.retention.readings_days', self::DEFAULT_RETENTION_DAYS);
        $cutoffDate = Carbon::now()->subDays($days);

        $oldExports = DataExport::where('completed_at', '<', $cutoffDate)
            ->where('is_backup', false)
            ->get();

        foreach ($oldExports as $export) {
            if (Storage::disk('local')->exists($export->filepath)) {
                Storage::disk('local')->delete($export->filepath);
            }
            $export->delete();
            Log::info("Deleted old export: {$export->filename}");
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExportDataToCsvJob failed: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);
    }
}
