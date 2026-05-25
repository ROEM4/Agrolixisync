<?php

namespace App\Services\ThesisMetrics;

use App\Models\Analysis;
use App\Models\Location;
use App\Models\ThesisMetric;
use Illuminate\Support\Carbon;

/**
 * Servicio para calcular TAR (Tiempo de Alerta de Riesgo)
 * 
 * TAR = Promedio(Hora de Alerta - Hora de Evento Crítico)
 * 
 * Mide qué tan rápido el sistema detecta y genera alerta
 * ante un evento crítico (lixiviación).
 */
class TARCalculator
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
     * Calcular TAR para el período
     */
    public function calculate(): ?float
    {
        // Obtener análisis con evento detectado y alerta generada
        $analyses = Analysis::where('location_id', $this->location->id)
            ->whereNotNull('event_detected_at')
            ->whereNotNull('alert_generated_at')
            ->whereBetween('analyzed_at', [
                $this->periodStart,
                $this->periodEnd
            ])
            ->get();

        if ($analyses->isEmpty()) {
            return null;
        }

        // Calcular diferencia en minutos para cada análisis
        $totalMinutes = 0;
        foreach ($analyses as $analysis) {
            $minutes = $analysis->event_detected_at
                ->diffInMinutes($analysis->alert_generated_at);
            $totalMinutes += abs($minutes);
        }

        // TAR = promedio de minutos
        return $totalMinutes / $analyses->count();
    }

    /**
     * Obtener estadísticas detalladas de TAR
     */
    public function getStatistics(): array
    {
        $analyses = Analysis::where('location_id', $this->location->id)
            ->whereNotNull('event_detected_at')
            ->whereNotNull('alert_generated_at')
            ->whereBetween('analyzed_at', [
                $this->periodStart,
                $this->periodEnd
            ])
            ->get();

        if ($analyses->isEmpty()) {
            return [
                'tar_average' => null,
                'tar_min' => null,
                'tar_max' => null,
                'sample_count' => 0,
                'status' => 'No data available',
            ];
        }

        $differences = $analyses->map(function ($analysis) {
            return abs($analysis->event_detected_at
                ->diffInMinutes($analysis->alert_generated_at));
        });

        return [
            'tar_average' => $differences->avg(),
            'tar_min' => $differences->min(),
            'tar_max' => $differences->max(),
            'tar_median' => $this->calculateMedian($differences->toArray()),
            'sample_count' => $analyses->count(),
            'status' => 'calculated',
        ];
    }

    /**
     * Guardar TAR en thesis_metrics
     */
    public function save(): ThesisMetric
    {
        $tar = $this->calculate();
        $stats = $this->getStatistics();

        $metric = ThesisMetric::updateOrCreate(
            [
                'location_id' => $this->location->id,
                'period_start_date' => $this->periodStart->toDateString(),
                'period_end_date' => $this->periodEnd->toDateString(),
            ],
            [
                'tar_minutes' => $tar,
                'tar_sample_count' => $stats['sample_count'],
                'tar_calculated_at' => now(),
                'calculated_by' => 'system',
            ]
        );

        return $metric;
    }

    /**
     * Calcular mediana
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) return 0;
        
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        return $values[$middle];
    }

    /**
     * Obtener interpretación de TAR
     */
    public function getInterpretation(): string
    {
        $tar = $this->calculate();

        if (!$tar) return "No hay datos suficientes";
        
        if ($tar < 5) return "Excelente: Alerta muy rápida (<5 min)";
        if ($tar < 15) return "Bueno: Alerta rápida (5-15 min)";
        if ($tar < 30) return "Aceptable: Alerta moderada (15-30 min)";
        if ($tar < 60) return "Lento: Alerta tardía (30-60 min)";
        return "Muy lento: Alerta muy tardía (>60 min)";
    }
}
