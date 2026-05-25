<?php

namespace App\Services\ThesisMetrics;

use App\Models\Location;
use App\Models\SystemTest;
use App\Models\ThesisMetric;
use Illuminate\Support\Carbon;

/**
 * Servicio para calcular PDS (Precisión del Diagnóstico del Sistema)
 * 
 * PDS = (Total coincidencias "Sí" / Total pruebas) * 100
 * 
 * Mide la exactitud del sistema comparando:
 * - Detección automática del sistema
 * - Validación manual del evento real
 * 
 * Métricas:
 * - Verdaderos Positivos (TP): Sistema detectó, realidad confirmó ✓
 * - Verdaderos Negativos (TN): Sistema no detectó, realidad confirmó ✓
 * - Falsos Positivos (FP): Sistema detectó, pero no existía ✗
 * - Falsos Negativos (FN): Sistema no detectó, pero existía ✗
 */
class PDSCalculator
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
     * Calcular PDS (Precisión en porcentaje)
     * 
     * PDS = (TP + TN) / (TP + TN + FP + FN) * 100
     */
    public function calculate(): ?float
    {
        $tests = $this->getTests();

        if ($tests->isEmpty()) {
            return null;
        }

        $correctCount = $tests->filter(fn($t) => $t->isCorrect())->count();
        $totalCount = $tests->count();

        return ($correctCount / $totalCount) * 100;
    }

    /**
     * Obtener todas las pruebas del período
     */
    private function getTests()
    {
        return SystemTest::where('location_id', $this->location->id)
            ->where('included_in_pds', true)
            ->whereNotNull('match_result')
            ->whereBetween('validated_at', [
                $this->periodStart->startOfDay(),
                $this->periodEnd->endOfDay()
            ])
            ->get();
    }

    /**
     * Obtener estadísticas detalladas
     */
    public function getStatistics(): array
    {
        $tests = $this->getTests();

        if ($tests->isEmpty()) {
            return [
                'pds_percentage' => null,
                'total_tests' => 0,
                'true_positives' => 0,
                'true_negatives' => 0,
                'false_positives' => 0,
                'false_negatives' => 0,
                'sensitivity' => null,
                'specificity' => null,
                'status' => 'No tests available',
            ];
        }

        $tp = $tests->filter(fn($t) => $t->isTruePositive())->count();
        $tn = $tests->filter(fn($t) => $t->isTrueNegative())->count();
        $fp = $tests->filter(fn($t) => $t->isFalsePositive())->count();
        $fn = $tests->filter(fn($t) => $t->isFalseNegative())->count();

        $totalCorrect = $tp + $tn;
        $totalTests = $tests->count();

        // Sensibilidad = TP / (TP + FN) - Capacidad de detectar positivos reales
        $sensitivity = ($tp + $fn > 0) ? ($tp / ($tp + $fn)) * 100 : null;

        // Especificidad = TN / (TN + FP) - Capacidad de detectar negativos reales
        $specificity = ($tn + $fp > 0) ? ($tn / ($tn + $fp)) * 100 : null;

        return [
            'pds_percentage' => ($totalCorrect / $totalTests) * 100,
            'total_tests' => $totalTests,
            'true_positives' => $tp,
            'true_negatives' => $tn,
            'false_positives' => $fp,
            'false_negatives' => $fn,
            'sensitivity' => $sensitivity,
            'specificity' => $specificity,
            'accuracy' => ($totalCorrect / $totalTests) * 100,
            'status' => 'calculated',
        ];
    }

    /**
     * Guardar PDS en thesis_metrics
     */
    public function save(): ThesisMetric
    {
        $pds = $this->calculate();
        $stats = $this->getStatistics();

        $metric = ThesisMetric::updateOrCreate(
            [
                'location_id' => $this->location->id,
                'period_start_date' => $this->periodStart->toDateString(),
                'period_end_date' => $this->periodEnd->toDateString(),
            ],
            [
                'pds_percentage' => $pds,
                'pds_total_tests' => $stats['total_tests'],
                'pds_correct_detections' => $stats['true_positives'] + $stats['true_negatives'],
                'pds_false_positives' => $stats['false_positives'],
                'pds_false_negatives' => $stats['false_negatives'],
                'pds_calculated_at' => now(),
                'calculated_by' => 'system',
            ]
        );

        return $metric;
    }

    /**
     * Obtener interpretación de PDS
     */
    public function getInterpretation(): string
    {
        $pds = $this->calculate();

        if (!$pds) return "No hay datos suficientes";
        
        if ($pds >= 95) return "Excelente: Precisión muy alta (≥95%)";
        if ($pds >= 85) return "Muy Bueno: Precisión alta (85-95%)";
        if ($pds >= 75) return "Bueno: Precisión adecuada (75-85%)";
        if ($pds >= 70) return "Aceptable: Precisión moderada (70-75%)";
        return "Deficiente: Precisión baja (<70%) - Revisar calibración";
    }

    /**
     * Generar reporte de diagnóstico
     */
    public function generateDiagnosticReport(): array
    {
        $stats = $this->getStatistics();
        $interpretation = $this->getInterpretation();

        return [
            'period' => [
                'start' => $this->periodStart->format('Y-m-d'),
                'end' => $this->periodEnd->format('Y-m-d'),
            ],
            'summary' => [
                'accuracy_percentage' => round($stats['pds_percentage'] ?? 0, 2),
                'sensitivity_percentage' => round($stats['sensitivity'] ?? 0, 2),
                'specificity_percentage' => round($stats['specificity'] ?? 0, 2),
                'interpretation' => $interpretation,
            ],
            'details' => [
                'total_tests' => $stats['total_tests'],
                'true_positives' => $stats['true_positives'],
                'true_negatives' => $stats['true_negatives'],
                'false_positives' => $stats['false_positives'],
                'false_negatives' => $stats['false_negatives'],
            ],
            'recommendations' => $this->getRecommendations($stats),
        ];
    }

    /**
     * Obtener recomendaciones basadas en las métricas
     */
    private function getRecommendations(array $stats): array
    {
        $recommendations = [];

        if (($stats['false_positives'] ?? 0) > ($stats['total_tests'] ?? 0) * 0.1) {
            $recommendations[] = "Hay muchos falsos positivos. Revisar umbral de sensibilidad.";
        }

        if (($stats['false_negatives'] ?? 0) > ($stats['total_tests'] ?? 0) * 0.1) {
            $recommendations[] = "Hay muchos falsos negativos. Aumentar sensibilidad del sistema.";
        }

        if (($stats['sensitivity'] ?? 0) < 80) {
            $recommendations[] = "Baja sensibilidad. El sistema pierde eventos reales.";
        }

        if (($stats['specificity'] ?? 0) < 80) {
            $recommendations[] = "Baja especificidad. El sistema genera muchas falsas alarmas.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "El sistema funciona correctamente. Mantener calibración actual.";
        }

        return $recommendations;
    }
}
