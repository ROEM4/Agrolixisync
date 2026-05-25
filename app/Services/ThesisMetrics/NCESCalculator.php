<?php

namespace App\Services\ThesisMetrics;

use App\Models\Location;
use App\Models\Reading;
use App\Models\SensorGroup;
use App\Models\ThesisMetric;
use Illuminate\Support\Carbon;

/**
 * Servicio para calcular NCES (Nivel de Conductividad Eléctrica en Suelo)
 * 
 * NCES = Promedio(CE_control) - Promedio(CE_experimental)
 * 
 * Compara la conductividad entre un grupo control (sin tratamiento)
 * y un grupo experimental (con tratamiento/variación).
 * 
 * Permite estudiar el impacto de tratamientos en la lixiviación.
 */
class NCESCalculator
{
    private Location $location;
    private Carbon $periodStart;
    private Carbon $periodEnd;

    public function __construct(Location $location, Carbon $periodStart, Carbon $periodEnd)
    {
        $this->location = $location;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    /**
     * Calcular NCES
     * 
     * NCES = CE_promedio_control - CE_promedio_experimental
     */
    public function calculate(): array
    {
        $controlAvg = $this->calculateControlAverage();
        $experimentalAvg = $this->calculateExperimentalAverage();

        return [
            'control_avg' => $controlAvg,
            'experimental_avg' => $experimentalAvg,
            'nces' => $controlAvg && $experimentalAvg ? 
                      $controlAvg - $experimentalAvg : 
                      null,
        ];
    }

    /**
     * Calcular promedio CE en grupo control
     */
    private function calculateControlAverage(): ?float
    {
        // Obtener sensores del grupo control
        $controlSensors = SensorGroup::where('location_id', $this->location->id)
            ->where('group_type', 'CONTROL')
            ->where('start_date', '<=', $this->periodEnd)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $this->periodStart);
            })
            ->pluck('sensor_id')
            ->toArray();

        if (empty($controlSensors)) {
            return null;
        }

        // Obtener promedio de conductividad en el período
        $readings = Reading::whereIn('sensor_id', $controlSensors)
            ->whereBetween('recorded_at', [
                $this->periodStart,
                $this->periodEnd
            ])
            ->get();

        if ($readings->isEmpty()) {
            return null;
        }

        return $readings->avg('conductivity');
    }

    /**
     * Calcular promedio CE en grupo experimental
     */
    private function calculateExperimentalAverage(): ?float
    {
        // Obtener sensores del grupo experimental
        $experimentalSensors = SensorGroup::where('location_id', $this->location->id)
            ->where('group_type', 'EXPERIMENTAL')
            ->where('start_date', '<=', $this->periodEnd)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $this->periodStart);
            })
            ->pluck('sensor_id')
            ->toArray();

        if (empty($experimentalSensors)) {
            return null;
        }

        // Obtener promedio de conductividad en el período
        $readings = Reading::whereIn('sensor_id', $experimentalSensors)
            ->whereBetween('recorded_at', [
                $this->periodStart,
                $this->periodEnd
            ])
            ->get();

        if ($readings->isEmpty()) {
            return null;
        }

        return $readings->avg('conductivity');
    }

    /**
     * Obtener estadísticas detalladas
     */
    public function getStatistics(): array
    {
        $controlSensors = SensorGroup::where('location_id', $this->location->id)
            ->where('group_type', 'CONTROL')
            ->where('start_date', '<=', $this->periodEnd)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $this->periodStart);
            })
            ->pluck('sensor_id')
            ->toArray();

        $experimentalSensors = SensorGroup::where('location_id', $this->location->id)
            ->where('group_type', 'EXPERIMENTAL')
            ->where('start_date', '<=', $this->periodEnd)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $this->periodStart);
            })
            ->pluck('sensor_id')
            ->toArray();

        $ncesResult = $this->calculate();

        $controlReadings = !empty($controlSensors) ?
            Reading::whereIn('sensor_id', $controlSensors)
                ->whereBetween('recorded_at', [$this->periodStart, $this->periodEnd])
                ->count() : 0;

        $experimentalReadings = !empty($experimentalSensors) ?
            Reading::whereIn('sensor_id', $experimentalSensors)
                ->whereBetween('recorded_at', [$this->periodStart, $this->periodEnd])
                ->count() : 0;

        return [
            'control_avg' => $ncesResult['control_avg'],
            'experimental_avg' => $ncesResult['experimental_avg'],
            'nces_difference' => $ncesResult['nces'],
            'control_sensors_count' => count($controlSensors),
            'experimental_sensors_count' => count($experimentalSensors),
            'control_samples' => $controlReadings,
            'experimental_samples' => $experimentalReadings,
            'status' => (!empty($controlSensors) && !empty($experimentalSensors)) ? 'calculated' : 'incomplete',
        ];
    }

    /**
     * Guardar NCES en thesis_metrics
     */
    public function save(): ThesisMetric
    {
        $stats = $this->getStatistics();

        $metric = ThesisMetric::updateOrCreate(
            [
                'location_id' => $this->location->id,
                'period_start_date' => $this->periodStart->toDateString(),
                'period_end_date' => $this->periodEnd->toDateString(),
            ],
            [
                'nces_control_avg' => $stats['control_avg'],
                'nces_experimental_avg' => $stats['experimental_avg'],
                'nces_difference' => $stats['nces_difference'],
                'nces_control_samples' => $stats['control_samples'],
                'nces_experimental_samples' => $stats['experimental_samples'],
                'nces_calculated_at' => now(),
                'calculated_by' => 'system',
            ]
        );

        return $metric;
    }

    /**
     * Obtener interpretación de NCES
     */
    public function getInterpretation(): string
    {
        $result = $this->calculate();
        $nces = $result['nces'];

        if (!$nces) return "Datos insuficientes para comparación";
        
        if ($nces > 200) return "Lixiviación severa en experimental";
        if ($nces > 100) return "Diferencia significativa: Control mejor";
        if ($nces > 50) return "Diferencia moderada: Control mantiene mejor CE";
        if ($nces > 0) return "Diferencia leve: Control ligeramente mejor";
        if ($nces == 0) return "Sin diferencia apreciable";
        if ($nces > -50) return "Experimental mantiene mejor CE";
        return "Experimental significativamente mejor que control";
    }

    /**
     * Generar reporte comparativo
     */
    public function generateComparativeReport(): array
    {
        $stats = $this->getStatistics();
        $interpretation = $this->getInterpretation();

        return [
            'period' => [
                'start' => $this->periodStart->format('Y-m-d'),
                'end' => $this->periodEnd->format('Y-m-d'),
            ],
            'control_group' => [
                'sensors_count' => $stats['control_sensors_count'],
                'samples_count' => $stats['control_samples'],
                'ce_average' => round($stats['control_avg'] ?? 0, 2),
                'unit' => 'µS/cm',
            ],
            'experimental_group' => [
                'sensors_count' => $stats['experimental_sensors_count'],
                'samples_count' => $stats['experimental_samples'],
                'ce_average' => round($stats['experimental_avg'] ?? 0, 2),
                'unit' => 'µS/cm',
            ],
            'nces' => [
                'difference' => round($stats['nces_difference'] ?? 0, 2),
                'percentage_change' => $stats['control_avg'] ? 
                    round(($stats['nces_difference'] / $stats['control_avg']) * 100, 2) : 
                    null,
                'interpretation' => $interpretation,
            ],
            'status' => $stats['status'],
        ];
    }
}
