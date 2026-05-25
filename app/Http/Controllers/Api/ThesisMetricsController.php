<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ThesisMetric;
use App\Models\SystemTest;
use App\Services\ThesisMetrics\ThesisMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * ThesisMetricsController
 * 
 * API endpoints para acceder a indicadores de tesis:
 * - TAR (Tiempo de Alerta de Riesgo)
 * - PDS (Precisión del Diagnóstico del Sistema)
 * - NCES (Nivel de Conductividad Eléctrica en Suelo)
 */
class ThesisMetricsController extends Controller
{
    /**
     * GET /api/thesis-metrics/latest
     * 
     * Obtener últimas métricas de tesis para una ubicación
     */
    public function getLatest(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id parameter required'
            ], 422);
        }

        $location = Location::find($locationId);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found'
            ], 404);
        }

        $metric = ThesisMetric::where('location_id', $locationId)
            ->orderBy('period_end_date', 'desc')
            ->first();

        if (!$metric) {
            return response()->json([
                'status' => 'no_data',
                'message' => 'No thesis metrics available for this location'
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'location' => $location->only(['id', 'name']),
                'tar' => [
                    'minutes' => round($metric->tar_minutes ?? 0, 2),
                    'formatted' => $this->formatTAR($metric->tar_minutes),
                    'sample_count' => $metric->tar_sample_count,
                    'calculated_at' => $metric->tar_calculated_at,
                ],
                'pds' => [
                    'percentage' => round($metric->pds_percentage ?? 0, 2),
                    'total_tests' => $metric->pds_total_tests,
                    'correct' => $metric->pds_correct_detections,
                    'false_positives' => $metric->pds_false_positives,
                    'false_negatives' => $metric->pds_false_negatives,
                    'calculated_at' => $metric->pds_calculated_at,
                ],
                'nces' => [
                    'control_avg' => round($metric->nces_control_avg ?? 0, 2),
                    'experimental_avg' => round($metric->nces_experimental_avg ?? 0, 2),
                    'difference' => round($metric->nces_difference ?? 0, 2),
                    'control_samples' => $metric->nces_control_samples,
                    'experimental_samples' => $metric->nces_experimental_samples,
                    'calculated_at' => $metric->nces_calculated_at,
                ],
                'period' => [
                    'start' => $metric->period_start_date->format('Y-m-d'),
                    'end' => $metric->period_end_date->format('Y-m-d'),
                ],
            ]
        ]);
    }

    /**
     * GET /api/thesis-metrics/summary
     * 
     * Obtener resumen completo con interpretaciones
     */
    public function getSummary(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        $days = $request->query('days', 30);

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id parameter required'
            ], 422);
        }

        $location = Location::find($locationId);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found'
            ], 404);
        }

        $periodStart = Carbon::now()->subDays($days);
        $periodEnd = Carbon::now();

        try {
            $service = new ThesisMetricsService($location, $periodStart, $periodEnd);
            $summary = $service->getSummary();

            return response()->json([
                'status' => 'success',
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/thesis-metrics/evolution
     * 
     * Obtener evolución de indicadores en el tiempo
     */
    public function getEvolution(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        $periods = $request->query('periods', 12);

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id parameter required'
            ], 422);
        }

        $location = Location::find($locationId);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found'
            ], 404);
        }

        try {
            $service = new ThesisMetricsService($location);
            $evolution = $service->getEvolution($periods);

            return response()->json([
                'status' => 'success',
                'data' => $evolution
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/thesis-metrics/validate-data
     * 
     * Validar integridad de datos para cálculos
     */
    public function validateData(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id parameter required'
            ], 422);
        }

        $location = Location::find($locationId);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found'
            ], 404);
        }

        $service = new ThesisMetricsService($location);
        $validation = $service->validateData();

        return response()->json([
            'status' => 'success',
            'data' => $validation
        ]);
    }

    /**
     * GET /api/thesis-metrics/pds-report
     * 
     * Obtener reporte detallado de PDS (Precisión)
     */
    public function getPDSReport(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');
        $days = $request->query('days', 30);

        if (!$locationId) {
            return response()->json([
                'status' => 'error',
                'message' => 'location_id parameter required'
            ], 422);
        }

        $location = Location::find($locationId);
        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found'
            ], 404);
        }

        $periodStart = Carbon::now()->subDays($days);
        $periodEnd = Carbon::now();

        // Obtener estadísticas de pruebas
        $tests = SystemTest::where('location_id', $locationId)
            ->where('included_in_pds', true)
            ->whereBetween('validated_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->get();

        $tp = $tests->filter(fn($t) => $t->isTruePositive())->count();
        $tn = $tests->filter(fn($t) => $t->isTrueNegative())->count();
        $fp = $tests->filter(fn($t) => $t->isFalsePositive())->count();
        $fn = $tests->filter(fn($t) => $t->isFalseNegative())->count();

        $total = $tests->count();
        $correct = $tp + $tn;

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => [
                    'start' => $periodStart->format('Y-m-d'),
                    'end' => $periodEnd->format('Y-m-d'),
                ],
                'metrics' => [
                    'accuracy' => $total > 0 ? round(($correct / $total) * 100, 2) : 0,
                    'sensitivity' => ($tp + $fn > 0) ? round(($tp / ($tp + $fn)) * 100, 2) : 0,
                    'specificity' => ($tn + $fp > 0) ? round(($tn / ($tn + $fp)) * 100, 2) : 0,
                ],
                'confusion_matrix' => [
                    'true_positives' => $tp,
                    'true_negatives' => $tn,
                    'false_positives' => $fp,
                    'false_negatives' => $fn,
                    'total_tests' => $total,
                ],
                'distribution' => [
                    'correct_detections' => [
                        'value' => $correct,
                        'percentage' => $total > 0 ? round(($correct / $total) * 100, 2) : 0
                    ],
                    'incorrect_detections' => [
                        'value' => $total - $correct,
                        'percentage' => $total > 0 ? round((($total - $correct) / $total) * 100, 2) : 0
                    ]
                ]
            ]
        ]);
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
}
