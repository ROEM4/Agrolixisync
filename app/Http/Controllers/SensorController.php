<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use App\Models\Sensor;
use App\Models\Lectura;
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

        $query = Sensor::whereIn('ubicacion_id',
            Ubicacion::whereIn('planta_id', $user->plantas()->pluck('id'))->pluck('id')
        );

        // Filtros
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('ubicacion_id')) {
            $query->where('ubicacion_id', $request->ubicacion_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $sensors = $query->with(['ubicacion', 'ultimaLectura'])
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $sensor->load(['ubicacion', 'ultimaLectura']),
        ]);
    }

    /**
     * Crear nuevo sensor
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|unique:sensores,codigo',
            'nombre' => 'nullable|string',
            'ubicacion_id' => 'required|exists:ubicaciones,id',
            'profundidad' => 'required|numeric|min:0',
            'notas' => 'nullable|string',
        ]);

        // Verificar que la ubicación pertenezca al usuario
        $location = Ubicacion::findOrFail($validated['ubicacion_id']);
        if ($location->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        try {
            $sensor = $this->sensorService->createSensor($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sensor creado exitosamente',
                'data' => $sensor->load(['ubicacion']),
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'nullable|string',
            'notas' => 'nullable|string',
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'temperatura' => 'nullable|numeric',
            'humedad' => 'nullable|numeric',
            'conductividad' => 'nullable|numeric',
            'fecha_registro' => 'nullable|date',
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $query = $sensor->lecturas();

        // Filtros por fecha
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('fecha_registro', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        if ($request->has('minutes')) {
            $query->where('fecha_registro', '>=', now()->subMinutes($request->integer('minutes')));
        }

        $limit = $request->get('limit', 100);
        $readings = $query->latest('fecha_registro')->limit($limit)->get();

        return response()->json([
            'success' => true,
            'sensor' => $sensor->only('id', 'codigo', 'nombre'),
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $days = $request->get('days', 7);
        $statistics = $this->sensorService->getSensorStatistics($sensor, $days);

        return response()->json([
            'success' => true,
            'sensor' => $sensor->only('id', 'codigo', 'nombre'),
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
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
        if ($sensor->ubicacion->planta->usuario_id !== auth()->id()) {
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
    public function getLocationHealth(Ubicacion $location): JsonResponse
    {
        // Verificar permiso
        if ($location->planta->usuario_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $health = $this->sensorService->getSensorHealth($location);

        return response()->json([
            'success' => true,
            'location' => $location->only('id', 'nombre'),
            'sensors_health' => $health,
        ]);
    }
}
