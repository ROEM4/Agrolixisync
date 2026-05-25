<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Sensor;
use App\Models\Reading;
use App\Services\SensorDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SensorController extends Controller
{
    protected SensorDataService $sensorService;

    public function __construct(SensorDataService $sensorService)
    {
        $this->sensorService = $sensorService;
    }

    /**
     * Obtener todos los sensores del usuario
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Sensor::whereIn('location_id',
            Location::whereIn('lote_id', $user->lotes()->pluck('id'))->pluck('id')
        );

        // Filtros
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sensors = $query->with(['sensorType', 'location', 'lastReading'])
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $sensors,
        ]);
    }

    /**
     * Obtener sensor específico con detalles
     */
    public function show(Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $sensor->load(['sensorType', 'location', 'lastReading']),
        ]);
    }

    /**
     * Crear nuevo sensor
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|unique:sensors,code',
            'name' => 'nullable|string',
            'sensor_type_id' => 'required|exists:sensor_types,id',
            'location_id' => 'required|exists:locations,id',
            'depth' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Verificar que la ubicación pertenezca al usuario
        $location = Location::findOrFail($validated['location_id']);
        if ($location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $sensor = $this->sensorService->createSensor($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sensor creado exitosamente',
                'data' => $sensor->load(['sensorType', 'location']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Actualizar sensor
     */
    public function update(Request $request, Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $sensor->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sensor actualizado',
            'data' => $sensor,
        ]);
    }

    /**
     * Registrar lectura de sensor
     */
    public function recordReading(Request $request, Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric',
            'conductivity' => 'nullable|numeric',
            'soil_moisture' => 'nullable|numeric',
            'recorded_at' => 'nullable|date',
        ]);

        try {
            $reading = $this->sensorService->recordReading($sensor, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Lectura registrada',
                'data' => $reading,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obtener lecturas de un sensor
     */
    public function getReadings(Request $request, Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $query = $sensor->readings();

        // Filtros por fecha
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('recorded_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        if ($request->has('minutes')) {
            $query->recent($request->minutes);
        }

        $limit = $request->get('limit', 100);
        $readings = $query->latest('recorded_at')->limit($limit)->get();

        return response()->json([
            'success' => true,
            'sensor' => $sensor->only('id', 'code', 'name'),
            'readings_count' => $readings->count(),
            'data' => $readings,
        ]);
    }

    /**
     * Obtener estadísticas de un sensor
     */
    public function getStatistics(Request $request, Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $days = $request->get('days', 7);
        $statistics = $this->sensorService->getSensorStatistics($sensor, $days);

        return response()->json([
            'success' => true,
            'sensor' => $sensor->only('id', 'code', 'name'),
            'period_days' => $days,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Desactivar sensor
     */
    public function deactivate(Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $this->sensorService->deactivateSensor($sensor);

        return response()->json([
            'success' => true,
            'message' => 'Sensor desactivado',
            'data' => $sensor,
        ]);
    }

    /**
     * Activar sensor
     */
    public function activate(Sensor $sensor): JsonResponse
    {
        // Verificar permiso
        if ($sensor->location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $this->sensorService->activateSensor($sensor);

        return response()->json([
            'success' => true,
            'message' => 'Sensor activado',
            'data' => $sensor,
        ]);
    }

    /**
     * Obtener salud de sensores en una ubicación
     */
    public function getLocationHealth(Location $location): JsonResponse
    {
        // Verificar permiso
        if ($location->lote->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $health = $this->sensorService->getSensorHealth($location);

        return response()->json([
            'success' => true,
            'location' => $location->only('id', 'name'),
            'sensors_health' => $health,
        ]);
    }
}
