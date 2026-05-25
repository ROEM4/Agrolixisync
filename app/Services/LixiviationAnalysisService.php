<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\Alert;
use App\Models\Location;
use App\Models\Lote;
use App\Models\Reading;
use App\Models\Sensor;
use App\Models\Setting;
use Carbon\Carbon;

class LixiviationAnalysisService
{
    /**
     * Umbral por defecto para lixiviación (µS/cm)
     * Se puede personalizar por ubicación o cultivo
     */
    private float $defaultThreshold = 100.0;

    public function __construct()
    {
        // Obtener umbral de configuración
        $this->defaultThreshold = (float) Setting::getByKey('lixiviation_threshold', 100.0);
    }

    /**
     * Realizar análisis de lixiviación para una ubicación
     * Compara el sensor superficial con el sensor profundo
     */
    public function analyzeLocation(Location $location, ?Carbon $timestamp = null): ?Analysis
    {
        $timestamp = $timestamp ?? now();

        // Obtener sensores de la ubicación
        $superficialSensor = $location->superficialSensors()->first();
        $deepSensor = $location->deepSensors()->first();

        if (!$superficialSensor || !$deepSensor) {
            return null; // No se puede hacer análisis sin ambos sensores
        }

        // Obtener últimas lecturas
        $readingSuperficial = $superficialSensor->readings()
            ->where('recorded_at', '<=', $timestamp)
            ->orderByDesc('recorded_at')
            ->first();

        $readingProfundo = $deepSensor->readings()
            ->where('recorded_at', '<=', $timestamp)
            ->orderByDesc('recorded_at')
            ->first();

        if (!$readingSuperficial || !$readingProfundo) {
            return null; // Ambas lecturas necesarias
        }

        return $this->performAnalysis(
            $location,
            $superficialSensor,
            $deepSensor,
            $readingSuperficial,
            $readingProfundo,
            $timestamp
        );
    }

    /**
     * Realizar análisis usando dos sensores específicos
     */
    public function analyzeSensors(
        Sensor $superficialSensor,
        Sensor $deepSensor,
        ?Carbon $timestamp = null
    ): ?Analysis {
        $timestamp = $timestamp ?? now();

        // Validar que sean de la misma ubicación
        if ($superficialSensor->location_id !== $deepSensor->location_id) {
            throw new \InvalidArgumentException(
                'Los sensores deben estar en la misma ubicación'
            );
        }

        // Validar profundidades
        if ($superficialSensor->depth >= $deepSensor->depth) {
            throw new \InvalidArgumentException(
                'El sensor superficial debe tener profundidad menor que el profundo'
            );
        }

        $readingSuperficial = $superficialSensor->readings()
            ->where('recorded_at', '<=', $timestamp)
            ->orderByDesc('recorded_at')
            ->first();

        $readingProfundo = $deepSensor->readings()
            ->where('recorded_at', '<=', $timestamp)
            ->orderByDesc('recorded_at')
            ->first();

        if (!$readingSuperficial || !$readingProfundo) {
            return null;
        }

        return $this->performAnalysis(
            $superficialSensor->location,
            $superficialSensor,
            $deepSensor,
            $readingSuperficial,
            $readingProfundo,
            $timestamp
        );
    }

    /**
     * Realizar análisis completo de lixiviación
     */
    private function performAnalysis(
        Location $location,
        Sensor $superficialSensor,
        Sensor $deepSensor,
        Reading $readingSuperficial,
        Reading $readingProfundo,
        Carbon $timestamp
    ): Analysis {
        // Extraer conductividad
        $condSuperficial = $readingSuperficial->conductivity ?? 0;
        $condProfundo = $readingProfundo->conductivity ?? 0;

        // Calcular delta
        $deltaConductivity = $condProfundo - $condSuperficial;

        // Obtener umbral (puede personalizarse por lote)
        $threshold = $this->getThreshold($location->lote);

        // Determinar si hay lixiviación
        $lixiviationDetected = $deltaConductivity > $threshold;

        // Calcular nivel de riesgo
        $riskData = $this->calculateRiskLevel($deltaConductivity, $threshold);

        // Crear registro de análisis
        $analysis = Analysis::create([
            'lote_id' => $location->lote_id,
            'location_id' => $location->id,
            'sensor_superficial_id' => $superficialSensor->id,
            'sensor_profundo_id' => $deepSensor->id,
            'reading_superficial_id' => $readingSuperficial->id,
            'reading_profundo_id' => $readingProfundo->id,
            'conductivity_superficial' => $condSuperficial,
            'conductivity_profundo' => $condProfundo,
            'delta_conductivity' => $deltaConductivity,
            'threshold_used' => $threshold,
            'lixiviation_detected' => $lixiviationDetected,
            'risk_level' => $riskData['level'],
            'risk_percentage' => $riskData['percentage'],
            'analyzed_at' => $timestamp,
        ]);

        // Generar alerta si se detectó lixiviación
        if ($lixiviationDetected) {
            $this->generateAlert($analysis, $riskData);
        }

        return $analysis;
    }

    /**
     * Calcular nivel de riesgo basado en delta de conductividad
     */
    private function calculateRiskLevel(float $deltaConductivity, float $threshold): array
    {
        // Si no hay lixiviación, riesgo bajo
        if ($deltaConductivity <= $threshold) {
            return [
                'level' => 'bajo',
                'percentage' => max(0, min(33, ($deltaConductivity / $threshold) * 33)),
            ];
        }

        // Calcular porcentaje relativo al umbral
        $riskPercentage = min(100, ($deltaConductivity / $threshold) * 100);

        // Clasificar nivel
        if ($riskPercentage <= 50) {
            $level = 'medio';
        } elseif ($riskPercentage <= 80) {
            $level = 'alto';
        } else {
            $level = 'crítico';
        }

        return [
            'level' => $level,
            'percentage' => $riskPercentage,
        ];
    }

    /**
     * Generar alerta de lixiviación
     */
    private function generateAlert(Analysis $analysis, array $riskData): Alert
    {
        $descriptions = [
            'bajo' => 'Lixiviación ligera detectada. Conductividad profunda ligeramente superior.',
            'medio' => 'Lixiviación moderada detectada. Riesgo de pérdida de nutrientes.',
            'alto' => 'Lixiviación severa detectada. Acción inmediata recomendada.',
            'crítico' => 'Lixiviación crítica. Intervención urgente necesaria.',
        ];

        $recommendations = [
            'bajo' => 'Monitorear continuamente. Aplicar riego moderado.',
            'medio' => 'Reducir riego. Considerar aplicación de enmiendas.',
            'alto' => 'Suspender riego inmediatamente. Revisar sistema de drenaje.',
            'crítico' => 'Intervención agrícola urgente. Posible daño significativo de cultivo.',
        ];

        $level = $riskData['level'];

        return Alert::create([
            'analysis_id' => $analysis->id,
            'lote_id' => $analysis->lote_id,
            'location_id' => $analysis->location_id,
            'type' => 'lixiviacion',
            'level' => $level,
            'description' => $descriptions[$level] ?? 'Lixiviación detectada.',
            'recommendation' => $recommendations[$level] ?? 'Revisar sistema de riego.',
        ]);
    }

    /**
     * Obtener umbral de lixiviación
     */
    public function getThreshold(Lote $lote): float
    {
        // Aquí se podría personalizar por cultivo, época, etc.
        return $this->defaultThreshold;
    }

    /**
     * Actualizar umbral
     */
    public function setDefaultThreshold(float $threshold): void
    {
        Setting::updateByKey('lixiviation_threshold', (string) $threshold);
        $this->defaultThreshold = $threshold;
    }

    /**
     * Realizar análisis automático para todos los lotes
     * (útil para correr como scheduled task)
     */
    public function analyzeAllLocations(): array
    {
        $analyses = [];
        $locations = Location::with('lote')->get();

        foreach ($locations as $location) {
            $analysis = $this->analyzeLocation($location);
            if ($analysis) {
                $analyses[] = $analysis;
            }
        }

        return $analyses;
    }

    /**
     * Obtener histórico de análisis para una ubicación
     */
    public function getLocationHistory(Location $location, $days = 30): array
    {
        $startDate = now()->subDays($days);

        return Analysis::where('location_id', $location->id)
            ->where('analyzed_at', '>=', $startDate)
            ->orderByDesc('analyzed_at')
            ->get()
            ->toArray();
    }

    /**
     * Obtener resumen de lixiviación para un lote
     */
    public function getLixiviationSummary(Lote $lote, $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totalAnalysis = Analysis::where('lote_id', $lote->id)
            ->where('analyzed_at', '>=', $startDate)
            ->count();

        $lixiviationDetected = Analysis::where('lote_id', $lote->id)
            ->where('analyzed_at', '>=', $startDate)
            ->where('lixiviation_detected', true)
            ->count();

        $averageDelta = Analysis::where('lote_id', $lote->id)
            ->where('analyzed_at', '>=', $startDate)
            ->average('delta_conductivity');

        $highestAlert = Analysis::where('lote_id', $lote->id)
            ->where('analyzed_at', '>=', $startDate)
            ->orderByDesc('delta_conductivity')
            ->first();

        return [
            'total_analysis' => $totalAnalysis,
            'lixiviation_events' => $lixiviationDetected,
            'detection_rate' => $totalAnalysis > 0 ? ($lixiviationDetected / $totalAnalysis) * 100 : 0,
            'average_delta' => $averageDelta ?? 0,
            'highest_delta' => $highestAlert?->delta_conductivity ?? 0,
            'highest_risk_level' => $highestAlert?->risk_level ?? 'bajo',
        ];
    }
}
