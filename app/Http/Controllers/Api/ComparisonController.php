<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Sensor;
use App\Services\LixiviationComparativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    protected LixiviationComparativeService $comparativeService;

    public function __construct(LixiviationComparativeService $comparativeService)
    {
        $this->comparativeService = $comparativeService;
    }

    /**
     * Obtiene datos comparativos entre sensores superficial y profundo
     * GET /api/comparison/location/{location_id}
     */
    public function getLocationComparison(Location $location, Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 50), 100);

        $data = $this->comparativeService->getComparativeData($location, $limit);

        if (!$data['success']) {
            return response()->json($data, 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Obtiene estadísticas de una ubicación
     * GET /api/comparison/location/{location_id}/stats
     */
    public function getLocationStats(Location $location): JsonResponse
    {
        $stats = $this->comparativeService->getLocationStatistics($location);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Fuerza un análisis de lixiviación para una ubicación
     * POST /api/comparison/location/{location_id}/analyze
     */
    public function analyzeLocation(Location $location): JsonResponse
    {
        $analysis = $this->comparativeService->analyzeLocationPair($location);

        if (!$analysis) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo realizar el análisis. Verifica que existan ambos sensores.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Análisis completado',
            'data' => $analysis->load([
                'sensorSuperficial',
                'sensorProfundo',
                'readingSuperficial',
                'readingProfundo',
                'alerts',
            ]),
        ]);
    }

    /**
     * Obtiene los últimos análisis de una ubicación
     * GET /api/comparison/location/{location_id}/recent-analysis
     */
    public function getRecentAnalysis(Location $location, Request $request): JsonResponse
    {
        $hours = min($request->input('hours', 24), 720); // Max 30 días
        $analyses = $this->comparativeService->getRecentAnalysis($location, $hours);

        return response()->json([
            'success' => true,
            'count' => $analyses->count(),
            'data' => $analyses,
        ]);
    }

    /**
     * Obtiene datos para dashboard en tiempo real
     * GET /api/dashboard/comparison-data
     * Retorna datos de todos los sensores del usuario en formato compatible con dashboard
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            // Si no hay autenticación, obtener primer lote (modo legacy)
            $locations = Location::active()->limit(1)->get();
        } else {
            // Obtener locations del usuario autenticado
            $locations = Location::whereHas('lote', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->active()->get();
        }

        $dashboardData = [];

        foreach ($locations as $location) {
            $comparative = $this->comparativeService->getComparativeData($location, 50);
            $stats = $this->comparativeService->getLocationStatistics($location);

            if ($comparative['success']) {
                $dashboardData[] = [
                    'location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'description' => $location->description,
                    ],
                    'comparative' => $comparative,
                    'stats' => $stats,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($dashboardData),
            'data' => $dashboardData,
        ]);
    }
}
