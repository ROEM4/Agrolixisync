<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Analysis;
use App\Models\Location;
use App\Models\Reading;
use App\Models\Sensor;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class LixiviationComparativeService
{
    /**
     * Realiza análisis comparativo entre sensores superficial y profundo
     * Detecta lixiviación basándose en la diferencia de conductividad
     */
    public function analyzeLocationPair(Location $location): ?Analysis
    {
        // Obtener sensores de la ubicación
        $superficialSensor = $location->sensors()
            ->where('depth', 0)
            ->where('is_active', true)
            ->first();

        $deepSensor = $location->sensors()
            ->where('depth', '>', 0)
            ->where('is_active', true)
            ->first();

        if (!$superficialSensor || !$deepSensor) {
            return null;
        }

        return $this->performComparativeAnalysis($superficialSensor, $deepSensor, $location);
    }

    /**
     * Realiza el análisis comparativo entre dos sensores
     */
    private function performComparativeAnalysis(
        Sensor $superficialSensor,
        Sensor $deepSensor,
        Location $location
    ): ?Analysis {
        // Obtener últimas lecturas
        $readingSuperficial = $superficialSensor->readings()
            ->latest('recorded_at')
            ->first();

        $readingProfundo = $deepSensor->readings()
            ->latest('recorded_at')
            ->first();

        if (!$readingSuperficial || !$readingProfundo) {
            return null;
        }

        // Calcular delta de conductividad
        $conductivitySuperficial = $readingSuperficial->conductivity ?? 0;
        $conductivityProfundo = $readingProfundo->conductivity ?? 0;
        $deltaConduct = $conductivityProfundo - $conductivitySuperficial;

        // Obtener umbral configurado
        $threshold = (float) Setting::getByKey('lixiviation_threshold', 150.0);

        // Determinar si hay lixiviación
        $lixiviationDetected = $deltaConduct > $threshold;

        // Calcular nivel de riesgo
        $riskLevel = $this->calculateRiskLevel($deltaConduct, $threshold);
        $riskPercentage = $this->calculateRiskPercentage(
            $deltaConduct,
            $threshold,
            $readingSuperficial->humidity ?? 0,
            $readingSuperficial->temperature ?? 0
        );

        // Crear registro de análisis
        $analysis = Analysis::create([
            'lote_id' => $location->lote_id ?? 1,
            'location_id' => $location->id,
            'sensor_superficial_id' => $superficialSensor->id,
            'sensor_profundo_id' => $deepSensor->id,
            'reading_superficial_id' => $readingSuperficial->id,
            'reading_profundo_id' => $readingProfundo->id,
            'conductivity_superficial' => $conductivitySuperficial,
            'conductivity_profundo' => $conductivityProfundo,
            'delta_conductivity' => $deltaConduct,
            'threshold_used' => $threshold,
            'lixiviation_detected' => $lixiviationDetected,
            'risk_level' => $riskLevel,
            'risk_percentage' => $riskPercentage,
            'notes' => "Análisis automático: H={$readingSuperficial->humidity}%, T={$readingSuperficial->temperature}°C",
            'analyzed_at' => now(),
        ]);

        // Generar alerta si es necesario
        if ($lixiviationDetected && Setting::getByKey('enable_alerts', true)) {
            $this->createAlert($analysis, $superficialSensor, $deepSensor, $readingSuperficial, $readingProfundo);
        }

        return $analysis;
    }

    /**
     * Calcula el nivel de riesgo basado en el delta de conductividad
     */
    private function calculateRiskLevel(float $delta, float $threshold): string
    {
        if ($delta <= $threshold * 0.5) {
            return 'bajo';
        }

        if ($delta <= $threshold * 0.75) {
            return 'medio';
        }

        return 'alto';
    }

    /**
     * Calcula el porcentaje de riesgo considerando múltiples factores
     */
    private function calculateRiskPercentage(
        float $delta,
        float $threshold,
        float $humidity,
        float $temperature
    ): float {
        // Base: porcentaje del delta respecto al umbral
        $deltaPercentage = min(($delta / $threshold) * 100, 100);

        // Factor humedad: 0-100% de humedad añade 0-20% de riesgo
        $humidityFactor = ($humidity / 100) * 20;

        // Factor temperatura: a mayor temperatura, mayor riesgo (optimo 20-25°C)
        $tempOptimal = 22.5;
        $tempDeviation = abs($temperature - $tempOptimal);
        $temperatureFactor = min(($tempDeviation / 15) * 15, 15);

        $totalRisk = $deltaPercentage + $humidityFactor + $temperatureFactor;

        return min($totalRisk, 100);
    }

    /**
     * Crea una alerta por lixiviación detectada
     */
    private function createAlert(
        Analysis $analysis,
        Sensor $superficialSensor,
        Sensor $deepSensor,
        Reading $readingSuperficial,
        Reading $readingProfundo
    ): Alert {
        $level = $analysis->risk_level;
        
        $descriptions = [
            'bajo' => sprintf(
                'Riesgo bajo de lixiviación detectado. Delta: %.2f µS/cm',
                $analysis->delta_conductivity
            ),
            'medio' => sprintf(
                'Riesgo medio de lixiviación. Delta: %.2f µS/cm. Humedad: %.1f%%',
                $analysis->delta_conductivity,
                $readingSuperficial->humidity ?? 0
            ),
            'alto' => sprintf(
                'ALERTA: Alto riesgo de lixiviación. Delta: %.2f µS/cm. Temp: %.1f°C, Humedad: %.1f%%',
                $analysis->delta_conductivity,
                $readingSuperficial->temperature ?? 0,
                $readingSuperficial->humidity ?? 0
            ),
        ];

        $recommendations = [
            'bajo' => 'Monitorear próximas mediciones. Mantener riego regular.',
            'medio' => 'Reducir riego para evitar lixiviación. Aumentar frecuencia de monitoreo.',
            'alto' => 'ACCIÓN INMEDIATA: Suspender riego temporalmente. Contactar al agrónomo.',
        ];

        return Alert::create([
            'analysis_id' => $analysis->id,
            'lote_id' => $analysis->lote_id,
            'location_id' => $analysis->location_id,
            'type' => 'lixiviacion',
            'level' => $level,
            'description' => $descriptions[$level] ?? 'Lixiviación detectada',
            'recommendation' => $recommendations[$level] ?? 'Revisar análisis',
            'is_resolved' => false,
            'notified' => false,
        ]);
    }

    /**
     * Obtiene análisis reciente para una ubicación (últimas 24 horas)
     */
    public function getRecentAnalysis(Location $location, int $hoursBack = 24): Collection
    {
        return Analysis::where('location_id', $location->id)
            ->where('analyzed_at', '>=', now()->subHours($hoursBack))
            ->latest('analyzed_at')
            ->get();
    }

    /**
     * Obtiene estadísticas resumidas para una ubicación
     */
    public function getLocationStatistics(Location $location): array
    {
        $recentAnalysis = $this->getRecentAnalysis($location, 24);
        
        $lixiviationCount = $recentAnalysis->where('lixiviation_detected', true)->count();
        $totalAnalysis = $recentAnalysis->count();
        $averageDelta = $recentAnalysis->avg('delta_conductivity') ?? 0;
        $maxDelta = $recentAnalysis->max('delta_conductivity') ?? 0;
        $lastAnalysis = $recentAnalysis->first();

        return [
            'total_analysis' => $totalAnalysis,
            'lixiviation_events' => $lixiviationCount,
            'lixiviation_rate' => $totalAnalysis > 0 ? ($lixiviationCount / $totalAnalysis) * 100 : 0,
            'average_delta' => (float) $averageDelta,
            'max_delta' => (float) $maxDelta,
            'current_status' => $lastAnalysis ? ($lastAnalysis->lixiviation_detected ? 'lixiviacion' : 'normal') : 'sin_datos',
            'current_risk' => $lastAnalysis?->risk_level ?? 'desconocido',
            'last_analysis_at' => $lastAnalysis?->analyzed_at?->toIso8601String(),
        ];
    }

    /**
     * Obtiene datos comparativos entre ambos sensores para gráficos
     */
    public function getComparativeData(Location $location, int $limitReadings = 50): array
    {
        $superficialSensor = $location->sensors()
            ->where('depth', 0)
            ->where('is_active', true)
            ->first();

        $deepSensor = $location->sensors()
            ->where('depth', '>', 0)
            ->where('is_active', true)
            ->first();

        if (!$superficialSensor || !$deepSensor) {
            return [
                'success' => false,
                'message' => 'No se encontraron ambos sensores en esta ubicación',
            ];
        }

        $readingsSuperficial = $superficialSensor->readings()
            ->latest('recorded_at')
            ->limit($limitReadings)
            ->get()
            ->reverse()
            ->values();

        $readingsProfundo = $deepSensor->readings()
            ->latest('recorded_at')
            ->limit($limitReadings)
            ->get()
            ->reverse()
            ->values();

        return [
            'success' => true,
            'superficial' => [
                'sensor_id' => $superficialSensor->id,
                'code' => $superficialSensor->code,
                'depth' => $superficialSensor->depth,
                'readings' => $readingsSuperficial->map(fn($r) => [
                    'id' => $r->id,
                    'conductivity' => $r->conductivity,
                    'humidity' => $r->humidity,
                    'temperature' => $r->temperature,
                    'recorded_at' => $r->recorded_at->toIso8601String(),
                ]),
            ],
            'profundo' => [
                'sensor_id' => $deepSensor->id,
                'code' => $deepSensor->code,
                'depth' => $deepSensor->depth,
                'readings' => $readingsProfundo->map(fn($r) => [
                    'id' => $r->id,
                    'conductivity' => $r->conductivity,
                    'humidity' => $r->humidity,
                    'temperature' => $r->temperature,
                    'recorded_at' => $r->recorded_at->toIso8601String(),
                ]),
            ],
            'labels' => $readingsSuperficial->map(fn($r) => $r->recorded_at->format('H:i:s')),
        ];
    }
}
