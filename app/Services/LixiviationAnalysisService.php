<?php

namespace App\Services;

use App\Models\AnalisisLixiviacion;
use App\Models\Alerta;
use App\Models\Ubicacion;
use App\Models\Planta;
use App\Models\Lectura;
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
        try {
            $this->defaultThreshold = (float) Setting::getByKey('lixiviation_threshold', 100.0);
        } catch (\Exception $e) {
            $this->defaultThreshold = 100.0;
        }
    }

    /**
     * Realizar análisis de lixiviación para una ubicación
     * Compara el sensor superficial con el sensor profundo
     */
    public function analyzeLocation(Ubicacion $location, ?Carbon $timestamp = null): ?AnalisisLixiviacion
    {
        $timestamp = $timestamp ?? now();

        // Obtener sensores de la ubicación
        $superficialSensor = $location->sensoresSuperficiales()->first();
        $deepSensor = $location->sensoresProfundos()->first();

        if (!$superficialSensor || !$deepSensor) {
            return null; // No se puede hacer análisis sin ambos sensores
        }

        // Obtener últimas lecturas
        $readingSuperficial = $superficialSensor->lecturas()
            ->where('fecha_registro', '<=', $timestamp)
            ->orderByDesc('fecha_registro')
            ->first();

        $readingProfundo = $deepSensor->lecturas()
            ->where('fecha_registro', '<=', $timestamp)
            ->orderByDesc('fecha_registro')
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
    ): ?AnalisisLixiviacion {
        $timestamp = $timestamp ?? now();

        // Validar que sean de la misma ubicación
        if ($superficialSensor->ubicacion_id !== $deepSensor->ubicacion_id) {
            throw new \InvalidArgumentException(
                'Los sensores deben estar en la misma ubicación'
            );
        }

        // Validar profundidades
        if ($superficialSensor->profundidad >= $deepSensor->profundidad) {
            throw new \InvalidArgumentException(
                'El sensor superficial debe tener profundidad menor que el profundo'
            );
        }

        $readingSuperficial = $superficialSensor->lecturas()
            ->where('fecha_registro', '<=', $timestamp)
            ->orderByDesc('fecha_registro')
            ->first();

        $readingProfundo = $deepSensor->lecturas()
            ->where('fecha_registro', '<=', $timestamp)
            ->orderByDesc('fecha_registro')
            ->first();

        if (!$readingSuperficial || !$readingProfundo) {
            return null;
        }

        return $this->performAnalysis(
            $superficialSensor->ubicacion,
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
        Ubicacion $location,
        Sensor $superficialSensor,
        Sensor $deepSensor,
        Lectura $readingSuperficial,
        Lectura $readingProfundo,
        Carbon $timestamp
    ): AnalisisLixiviacion {
        // Extraer conductividad
        $condSuperficial = $readingSuperficial->conductividad ?? 0;
        $condProfundo = $readingProfundo->conductividad ?? 0;

        // Calcular delta
        $deltaConductivity = $condProfundo - $condSuperficial;

        // Obtener umbral (puede personalizarse por planta)
        $threshold = $this->getThreshold($location->planta);

        // Determinar si hay lixiviación
        $lixiviationDetected = $deltaConductivity > $threshold;

        // Calcular nivel de riesgo
        $riskData = $this->calculateRiskLevel($deltaConductivity, $threshold);

        // Crear registro de análisis
        $analysis = AnalisisLixiviacion::create([
            'planta_id' => $location->planta_id,
            'ubicacion_id' => $location->id,
            'sensor_superficial_id' => $superficialSensor->id,
            'sensor_profundo_id' => $deepSensor->id,
            'lectura_superficial_id' => $readingSuperficial->id,
            'lectura_profundo_id' => $readingProfundo->id,
            'conductividad_superficial' => $condSuperficial,
            'conductividad_profundo' => $condProfundo,
            'delta_conductividad' => $deltaConductivity,
            'umbral_usado' => $threshold,
            'lixiviacion_detectada' => $lixiviationDetected,
            'nivel_riesgo' => $riskData['level'],
            'porcentaje_riesgo' => $riskData['percentage'],
            'fecha_analisis' => $timestamp,
        ]);

        // Generar alerta si se detectó lixiviación
        if ($lixiviationDetected) {
            $this->generateAlert($analysis, $riskData, $condSuperficial, $condProfundo, $deltaConductivity);
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
    private function generateAlert(AnalisisLixiviacion $analysis, array $riskData, float $condSuperficial, float $condProfundo, float $deltaCE): Alerta
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
        $desc = $descriptions[$level] ?? 'Lixiviación detectada.';
        $rec = $recommendations[$level] ?? 'Revisar sistema de riego.';
        $finalDescription = $desc . " Recomendación: " . $rec;

        return Alerta::create([
            'analisis_lixiviacion_id' => $analysis->id,
            'planta_id' => $analysis->planta_id,
            'ubicacion_id' => $analysis->ubicacion_id,
            'tipo' => 'lixiviacion',
            'severidad' => strtoupper($level),
            'nivel' => strtoupper($level),
            'estado' => 'ABIERTA',
            'descripcion' => $finalDescription,
            'ce_actual' => $condProfundo,
            'ce_anterior' => $condSuperficial,
            'delta_ce' => $deltaCE,
            'tiempo_alerta' => now(),
            'resuelta' => false,
        ]);
    }

    /**
     * Obtener umbral de lixiviación
     */
    public function getThreshold(Planta $planta): float
    {
        // Aquí se podría personalizar por cultivo, época, etc.
        return $this->defaultThreshold;
    }

    /**
     * Actualizar umbral
     */
    public function setDefaultThreshold(float $threshold): void
    {
        try {
            Setting::updateByKey('lixiviation_threshold', (string) $threshold);
        } catch (\Exception $e) {
            // ignore
        }
        $this->defaultThreshold = $threshold;
    }

    /**
     * Realizar análisis automático para todas los lotes
     */
    public function analyzeAllLocations(): array
    {
        $analyses = [];
        $locations = Ubicacion::with('planta')->get();

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
    public function getLocationHistory(Ubicacion $location, $days = 30): array
    {
        $startDate = now()->subDays($days);

        return AnalisisLixiviacion::where('ubicacion_id', $location->id)
            ->where('fecha_analisis', '>=', $startDate)
            ->orderByDesc('fecha_analisis')
            ->get()
            ->toArray();
    }

    /**
     * Obtener resumen de lixiviación para un lote
     */
    public function getLixiviationSummary(Planta $planta, $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totalAnalysis = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->where('fecha_analisis', '>=', $startDate)
            ->count();

        $lixiviationDetected = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->where('fecha_analisis', '>=', $startDate)
            ->where('lixiviacion_detectada', true)
            ->count();

        $averageDelta = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->where('fecha_analisis', '>=', $startDate)
            ->average('delta_conductividad');

        $highestAlert = AnalisisLixiviacion::where('planta_id', $planta->id)
            ->where('fecha_analisis', '>=', $startDate)
            ->orderByDesc('delta_conductividad')
            ->first();

        return [
            'total_analysis' => $totalAnalysis,
            'lixiviation_events' => $lixiviationDetected,
            'detection_rate' => $totalAnalysis > 0 ? ($lixiviationDetected / $totalAnalysis) * 100 : 0,
            'average_delta' => $averageDelta ?? 0,
            'highest_delta' => $highestAlert?->delta_conductividad ?? 0,
            'highest_risk_level' => $highestAlert?->nivel_riesgo ?? 'bajo',
        ];
    }
}
