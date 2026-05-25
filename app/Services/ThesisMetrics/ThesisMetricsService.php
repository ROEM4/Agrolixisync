<?php

namespace App\Services\ThesisMetrics;

use App\Models\Location;
use App\Models\ThesisMetric;
use App\Services\ThesisMetrics\PFCalculator;
use Illuminate\Support\Carbon;

/**
 * ThesisMetricsService - Orquestador
 * 
 * Calcula todos los indicadores de tesis en un solo llamado:
 * - TAR (Tiempo de Alerta de Riesgo)
 * - PDS (Precisión del Diagnóstico del Sistema)
 * - NCES (Nivel de Conductividad Eléctrica en Suelo)
 * - PF (Índice de Pérdida de Fertilizante)
 */
class ThesisMetricsService
{
    private Location $location;
    private Carbon $periodStart;
    private Carbon $periodEnd;

    public function __construct(Location $location, ?Carbon $periodStart = null, ?Carbon $periodEnd = null)
    {
        $this->location = $location;
        $this->periodStart = $periodStart ?? Carbon::now()->subMonth();
        $this->periodEnd = $periodEnd ?? Carbon::now();
    }

    /**
     * Calcular todos los indicadores
     */
    public function calculateAll(): ThesisMetric
    {
        // Calculadores
        $tarCalc = new TARCalculator($this->location, $this->periodStart, $this->periodEnd);
        $pdsCalc = new PDSCalculator($this->location, $this->periodStart, $this->periodEnd);
        $ncesCalc = new NCESCalculator($this->location, $this->periodStart, $this->periodEnd);
        $pfCalc = new PFCalculator($this->location, $this->periodStart, $this->periodEnd);
        $pfData = $pfCalc->calculate();

        // Obtener o crear registro
        $metric = ThesisMetric::updateOrCreate(
            [
                'location_id' => $this->location->id,
                'period_start_date' => $this->periodStart->toDateString(),
                'period_end_date' => $this->periodEnd->toDateString(),
            ],
            [
                'calculated_by' => 'system',
            ]
        );

        // Calcular TAR
        $tarData = $tarCalc->getStatistics();
        $metric->update([
            'tar_minutes' => $tarData['tar_average'],
            'tar_sample_count' => $tarData['sample_count'],
            'tar_calculated_at' => now(),
        ]);

        // Calcular PDS
        $pdsData = $pdsCalc->getStatistics();
        $metric->update([
            'pds_percentage' => $pdsData['pds_percentage'],
            'pds_total_tests' => $pdsData['total_tests'],
            'pds_correct_detections' => $pdsData['true_positives'] + $pdsData['true_negatives'],
            'pds_false_positives' => $pdsData['false_positives'],
            'pds_false_negatives' => $pdsData['false_negatives'],
            'pds_calculated_at' => now(),
        ]);

        // Calcular NCES
        $ncesData = $ncesCalc->getStatistics();
        $metric->update([
            'nces_control_avg' => $ncesData['control_avg'],
            'nces_experimental_avg' => $ncesData['experimental_avg'],
            'nces_difference' => $ncesData['nces_difference'],
            'nces_control_samples' => $ncesData['control_samples'],
            'nces_experimental_samples' => $ncesData['experimental_samples'],
            'nces_calculated_at' => now(),
        ]);

        $pfData = $pfCalc->calculate();
        if ($pfData) {
            $metric->update([
                'pf_percentage' => $pfData['pf_percentage'],
                'pf_reference_ce' => $pfData['reference_ce'],
                'pf_measured_ce' => $pfData['measured_ce'],
                'pf_calculated_at' => now(),
            ]);
        }

        return $metric->refresh();
    }

    /**
     * Obtener resumen completo de indicadores
     */
    public function getSummary(): array
    {
        $tarCalc = new TARCalculator($this->location, $this->periodStart, $this->periodEnd);
        $pdsCalc = new PDSCalculator($this->location, $this->periodStart, $this->periodEnd);
        $ncesCalc = new NCESCalculator($this->location, $this->periodStart, $this->periodEnd);
        $pfCalc = new PFCalculator($this->location, $this->periodStart, $this->periodEnd);

        return [
            'period' => [
                'start' => $this->periodStart->format('Y-m-d'),
                'end' => $this->periodEnd->format('Y-m-d'),
            ],
            'location' => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ],
            'tar' => [
                'value' => $tarCalc->calculate(),
                'formatted' => $this->formatTAR($tarCalc->calculate()),
                'interpretation' => $tarCalc->getInterpretation(),
                'stats' => $tarCalc->getStatistics(),
            ],
            'pds' => [
                'value' => $pdsCalc->calculate(),
                'interpretation' => $pdsCalc->getInterpretation(),
                'report' => $pdsCalc->generateDiagnosticReport(),
            ],
            'nces' => [
                'difference' => $ncesCalc->calculate()['nces'],
                'interpretation' => $ncesCalc->getInterpretation(),
                'report' => $ncesCalc->generateComparativeReport(),
            ],
            'pf' => [
                'value' => optional($pfData)['pf_percentage'] ?? null,
                'reference_ce' => optional($pfData)['reference_ce'] ?? null,
                'measured_ce' => optional($pfData)['measured_ce'] ?? null,
                'interpretation' => $pfCalc->getInterpretation($pfData ?? []),
            ],
        ];
    }

    /**
     * Formatear TAR para visualización
     */
    private function formatTAR(?float $minutes): ?string
    {
        if (!$minutes) return null;
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }
        return "{$mins}m";
    }

    /**
     * Obtener últimas métricas
     */
    public function getLatestMetrics(): ?ThesisMetric
    {
        return ThesisMetric::where('location_id', $this->location->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Obtener evolución de indicadores (últimos N períodos)
     */
    public function getEvolution($periods = 12): array
    {
        $metrics = ThesisMetric::where('location_id', $this->location->id)
            ->orderBy('period_end_date', 'desc')
            ->limit($periods)
            ->get()
            ->reverse()
            ->values();

        return [
            'location' => $this->location->name,
            'periods' => $metrics->count(),
            'tar_trend' => $metrics->pluck('tar_minutes')->toArray(),
            'pds_trend' => $metrics->pluck('pds_percentage')->toArray(),
            'nces_trend' => $metrics->pluck('nces_difference')->toArray(),
            'pf_trend' => $metrics->pluck('pf_percentage')->toArray(),
            'dates' => $metrics->pluck('period_end_date')->map(fn($d) => $d->format('Y-m-d'))->toArray(),
        ];
    }

    /**
     * Validar integridad de los datos
     */
    public function validateData(): array
    {
        $issues = [];

        // Verificar sensores del grupo control
        $controlSensors = $this->location->sensors()
            ->whereHas('sensorGroups', fn($q) => $q->where('group_type', 'CONTROL'))
            ->count();

        if ($controlSensors === 0) {
            $issues[] = "No hay sensores asignados al grupo CONTROL";
        }

        // Verificar sensores del grupo experimental
        $experimentalSensors = $this->location->sensors()
            ->whereHas('sensorGroups', fn($q) => $q->where('group_type', 'EXPERIMENTAL'))
            ->count();

        if ($experimentalSensors === 0) {
            $issues[] = "No hay sensores asignados al grupo EXPERIMENTAL";
        }

        // Verificar datos de lectura
        $readings = $this->location->sensors()
            ->with('readings')
            ->whereBetween('recorded_at', [$this->periodStart, $this->periodEnd])
            ->count();

        if ($readings === 0) {
            $issues[] = "No hay lecturas en el período especificado";
        }

        // Verificar análisis
        $analyses = $this->location->analyses()
            ->whereBetween('analyzed_at', [$this->periodStart, $this->periodEnd])
            ->count();

        if ($analyses === 0) {
            $issues[] = "No hay análisis realizados en el período";
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'summary' => [
                'control_sensors' => $controlSensors,
                'experimental_sensors' => $experimentalSensors,
                'readings_count' => $readings,
                'analysis_count' => $analyses,
            ]
        ];
    }
}
