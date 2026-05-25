<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Lote;
use App\Models\Location;
use App\Services\LixiviationAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalysisController extends Controller
{
    protected LixiviationAnalysisService $analysisService;

    public function __construct(LixiviationAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Obtener todos los análisis del usuario
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Analysis::whereIn('lote_id', $user->lotes()->pluck('id'));

        // Filtros
        if ($request->has('lote_id')) {
            $query->where('lote_id', $request->lote_id);
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('lixiviation_detected')) {
            $query->where('lixiviation_detected', $request->boolean('lixiviation_detected'));
        }

        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('analyzed_at', [
                \Carbon\Carbon::parse($request->start_date)->startOfDay(),
                \Carbon\Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        $perPage = $request->get('per_page', 20);
        $analyses = $query->latest('analyzed_at')->paginate($perPage);

        // Fallback: si no hay análisis en la tabla pero existen sensores/lecturas,
        // realizar análisis en memoria para no devolver un resultado vacío.
        if ($analyses->total() === 0) {
            $computed = [];
            $loteIds = $user->lotes()->pluck('id')->toArray();
            $locations = Location::whereIn('lote_id', $loteIds)->get();

            foreach ($locations as $loc) {
                $analysis = $this->analysisService->analyzeLocation($loc);
                if ($analysis) {
                    $computed[] = $analysis->load(['lote', 'location', 'alerts'])->toArray();
                }
            }

            if (!empty($computed)) {
                $page = (int) $request->get('page', 1);
                $offset = ($page - 1) * $perPage;
                $itemsForPage = array_slice($computed, $offset, $perPage);

                $analyses = new \Illuminate\Pagination\LengthAwarePaginator(
                    $itemsForPage,
                    count($computed),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $analyses,
        ]);
    }

    /**
     * Obtener análisis específico
     */
    public function show(Analysis $analysis): JsonResponse
    {
        // Verificar permiso
        if ($analysis->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $analysis->load([
                'lote',
                'location',
                'sensorSuperficial',
                'sensorProfundo',
                'readingSuperficial',
                'readingProfundo',
                'alerts',
            ]),
        ]);
    }

    /**
     * Forzar análisis de una ubicación
     */
    public function analyzeLocation(Location $location): JsonResponse
    {
        // Verificar permiso
        if ($location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $analysis = $this->analysisService->analyzeLocation($location);

            if (!$analysis) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo completar el análisis. Verifica que ambos sensores tengan datos.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Análisis completado',
                'data' => $analysis->load([
                    'lote',
                    'location',
                    'sensorSuperficial',
                    'sensorProfundo',
                    'alerts',
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener resumen de lixiviación de un lote
     */
    public function getLoteSummary(Lote $lote, Request $request): JsonResponse
    {
        // Verificar permiso
        if ($lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $days = $request->get('days', 7);
        $summary = $this->analysisService->getLixiviationSummary($lote, $days);

        return response()->json([
            'success' => true,
            'lote' => $lote->only('id', 'name'),
            'period_days' => $days,
            'summary' => $summary,
        ]);
    }

    /**
     * Obtener análisis de una ubicación (histórico)
     */
    public function getLocationHistory(Location $location, Request $request): JsonResponse
    {
        // Verificar permiso
        if ($location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $days = $request->get('days', 30);
        $history = $this->analysisService->getLocationHistory($location, $days);

        return response()->json([
            'success' => true,
            'location' => $location->only('id', 'name'),
            'period_days' => $days,
            'analyses_count' => count($history),
            'analyses' => $history,
        ]);
    }

    /**
     * Obtener análisis con lixiviación (alertas activas)
     */
    public function getLixiviationAlerts(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $alerts = Analysis::whereIn('lote_id', $user->lotes()->pluck('id'))
            ->where('lixiviation_detected', true)
            ->where('analyzed_at', '>=', now()->subDays($request->get('days', 7)))
            ->with(['lote', 'location', 'alerts'])
            ->latest('analyzed_at')
            ->paginate($request->get('per_page', 20));

        // Fallback: si no hay registros persistentes pero existen sensores/lecturas,
        // ejecutar análisis en memoria y devolver los que tengan lixiviación detectada.
        if ($alerts->total() === 0) {
            $computed = [];
            $loteIds = $user->lotes()->pluck('id')->toArray();
            $locations = Location::whereIn('lote_id', $loteIds)->get();

            foreach ($locations as $loc) {
                $analysis = $this->analysisService->analyzeLocation($loc);
                if ($analysis && $analysis->lixiviation_detected) {
                    $computed[] = $analysis->load(['lote', 'location', 'alerts'])->toArray();
                }
            }

            if (!empty($computed)) {
                $page = (int) request()->query('page', 1);
                $perPage = request()->get('per_page', 20);
                $offset = ($page - 1) * $perPage;
                $items = array_slice($computed, $offset, $perPage);

                $alerts = new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    count($computed),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Ejecutar análisis automático para todas las ubicaciones
     * (típicamente es un comando artisan o job, pero puede exponerse como endpoint admin)
     */
    public function runAutoAnalysis(): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $analyses = $this->analysisService->analyzeAllLocations();

        return response()->json([
            'success' => true,
            'message' => 'Análisis automático completado',
            'analyses_count' => count($analyses),
        ]);
    }
}
