<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\SensorController as ApiSensorController;
use App\Http\Controllers\ExportController;

/*
|--------------------------------------------------------------------------
| AUTH USER
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| SENSOR DATA (ESP32 / IoT)
|--------------------------------------------------------------------------
*/

// Recibir datos del ESP32 — usa IngestionService + LixiviationService
Route::post('/sensor/data', function (Request $request) {
    $validated = $request->validate([
        'device' => 'required|string',
        'ts'     => 'nullable|string',
        'ce_s'   => 'required|numeric',
        'ce_p'   => 'required|numeric',
        'temp_s' => 'nullable|numeric',
        'temp_p' => 'nullable|numeric',
        'hum_s'  => 'nullable|numeric',
        'hum_p'  => 'nullable|numeric',
    ]);

    // Normalizar timestamp
    $validated['ts'] = $validated['ts']
        ? \Carbon\Carbon::parse($validated['ts'])->toIso8601String()
        : now()->toIso8601String();

    try {
        // 1. Ingestar lecturas (auto-provisiona sensores si no existen)
        $ingestion = resolve(\App\Modules\SensorRealtime\IngestionService::class);
        $dto       = \App\Modules\SensorRealtime\SensorPayloadDTO::fromValidated($validated);
        $result    = $ingestion->ingest($dto);

        if ($result['status'] === 'duplicate') {
            return response()->json(['ok' => true, 'status' => 'duplicate'], 200);
        }

        // 2. Analizar lixiviación (calcula ILx, genera alertas, notifica Telegram)
        $sensors = resolve(\App\Services\IoTAutoProvisioningService::class)
                       ->resolveSensors($validated['device']);

        $lixService = resolve(\App\Modules\AnalyticsEngine\LixiviationService::class);
        $lixService->analyze($sensors['superficial'], $sensors['profundo']);

        return response()->json([
            'ok'           => true,
            'status'       => 'success',
            'device'       => $validated['device'],
            'sup_id'       => $result['sup_id'],
            'prof_id'      => $result['prof_id'],
            'location_id'  => $result['location_id'],
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['error' => $e->errors()], 422);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('ESP32 ingestion error: ' . $e->getMessage(), [
            'device' => $validated['device'] ?? 'unknown',
            'trace'  => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }
})->middleware('throttle:120,1');

// Obtener datos para gráficos (Ajax)
Route::get('/sensor/data', [ApiSensorController::class, 'getData']);

// Última lectura rápida
Route::get('/sensor/latest', [ApiSensorController::class, 'getLatest']);

/*
|--------------------------------------------------------------------------
| READINGS (LECTURAS)
|--------------------------------------------------------------------------
*/

// Últimas lecturas de una ubicación
Route::get('/readings/latest', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');

    if (!$ubicacion_id) {
        return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
    }

    $lecturas = \App\Models\Lectura::with('sensor')
        ->whereHas('sensor', fn($q) => $q->where('ubicacion_id', $ubicacion_id))
        ->orderByDesc('fecha_registro')
        ->limit(10)
        ->get();

    $sup  = $lecturas->first(fn($r) => $r->sensor->profundidad <= 20);
    $prof = $lecturas->first(fn($r) => $r->sensor->profundidad > 20);

    $readings = [];
    foreach ([$sup, $prof] as $r) {
        if ($r) {
            $readings[] = [
                'id'               => $r->id,
                'sensor_id'        => $r->sensor_id,
                'conductividad'    => (float) $r->conductividad,
                'humedad'          => (float) $r->humedad,
                'temperatura'      => (float) $r->temperatura,
                'fecha_registro'   => $r->fecha_registro?->toIso8601String(),
                'sensor'           => [
                    'id'          => $r->sensor->id,
                    'codigo'      => $r->sensor->codigo,
                    'nombre'      => $r->sensor->nombre,
                    'profundidad' => (int) $r->sensor->profundidad,
                    // Alias JS
                    'depth'       => (int) $r->sensor->profundidad,
                    'code'        => $r->sensor->codigo,
                ],
                // Alias JS legacy
                'conductivity'     => (float) $r->conductividad,
                'conductivity_raw' => (float) $r->conductividad,
                'humidity'         => (float) $r->humedad,
                'temperature'      => (float) $r->temperatura,
                'recorded_at'      => $r->fecha_registro?->toIso8601String(),
            ];
        }
    }

    // Incluir el último análisis de lixiviación para que el frontend use ILx del backend
    $analisis = \App\Models\AnalisisLixiviacion::where('ubicacion_id', $ubicacion_id)
        ->orderByDesc('fecha_analisis')
        ->first();

    return response()->json([
        'status' => 'success',
        'data'   => [
            'readings' => $readings,
            'analysis' => $analisis ? [
                'ilx'          => $analisis->ilx,
                'ilx_estado'   => $analisis->ilx_estado ?? $analisis->estado_ilx,
                'nivel_riesgo' => $analisis->nivel_riesgo,
            ] : null,
        ],
    ]);
});

// Historial de lecturas (pares SUP+PROF)
Route::get('/readings/history', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');
    $limit        = min((int) $request->query('limit', 50), 200);

    if (!$ubicacion_id) {
        return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
    }

    $lecturas = \App\Models\Lectura::with('sensor')
        ->whereHas('sensor', fn($q) => $q->where('ubicacion_id', $ubicacion_id))
        ->orderByDesc('fecha_registro')
        ->limit($limit)
        ->get();

    $grouped = $lecturas->groupBy(fn($r) => $r->fecha_registro?->format('Y-m-d H:i'));

    $pairs = $grouped->map(function ($group) {
        $sup  = $group->first(fn($r) => $r->sensor->profundidad <= 20);
        $prof = $group->first(fn($r) => $r->sensor->profundidad > 20);

        $fmt = fn($r) => $r ? [
            'id'               => $r->id,
            'conductivity'     => (float) $r->conductividad,
            'conductivity_raw' => (float) $r->conductividad,
            'humidity'         => (float) $r->humedad,
            'temperature'      => (float) $r->temperatura,
            'recorded_at'      => $r->fecha_registro?->toIso8601String(),
            'sensor'           => [
                'code'  => $r->sensor->codigo,
                'depth' => (int) $r->sensor->profundidad,
            ],
        ] : null;

        return ['sup' => $fmt($sup), 'prof' => $fmt($prof)];
    })->values();

    return response()->json(['status' => 'success', 'data' => $pairs]);
});

// Último análisis de lixiviación
Route::get('/analysis/latest', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');

    $q = \App\Models\AnalisisLixiviacion::orderByDesc('fecha_analisis');
    if ($ubicacion_id) {
        $q->where('ubicacion_id', $ubicacion_id);
    }
    $analisis = $q->first();

    if (!$analisis) {
        return response()->json(['status' => 'error', 'message' => 'Sin datos'], 404);
    }

    return response()->json(['status' => 'success', 'data' => $analisis]);
});

// Dashboard data
Route::get('/dashboard/data', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');

    $analisis = \App\Models\AnalisisLixiviacion::when($ubicacion_id, fn($q) => $q->where('ubicacion_id', $ubicacion_id))
        ->orderByDesc('fecha_analisis')
        ->first();

    $alertas = \App\Models\Alerta::when($ubicacion_id, fn($q) => $q->where('ubicacion_id', $ubicacion_id))
        ->where('estado', 'ABIERTA')
        ->count();

    return response()->json([
        'status'           => 'success',
        'analisis'         => $analisis,
        'alertas_abiertas' => $alertas,
    ]);
});

/*
|--------------------------------------------------------------------------
| PLANTAS / UBICACIONES
|--------------------------------------------------------------------------
*/

Route::get('/plantas',    fn() => \App\Models\Planta::with('ubicaciones')->orderBy('numero_planta')->get());
Route::get('/ubicaciones',fn() => \App\Models\Ubicacion::with('planta')->orderBy('nombre')->get());

/*
|--------------------------------------------------------------------------
| ALERTAS
|--------------------------------------------------------------------------
*/

Route::get('/alerts', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');
    $estado       = $request->query('estado', 'ABIERTA');

    $alertas = \App\Models\Alerta::with(['ubicacion.planta'])
        ->when($ubicacion_id, fn($q) => $q->where('ubicacion_id', $ubicacion_id))
        ->when($estado !== 'all', fn($q) => $q->where('estado', strtoupper($estado)))
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();

    return response()->json(['status' => 'success', 'data' => $alertas]);
});

Route::get('/alerts/list', fn(Request $request) => redirect('/api/alerts?' . $request->getQueryString()));

Route::post('/alerts/{alerta}/resolve', function (\App\Models\Alerta $alerta, Request $request) {
    $alerta->update([
        'estado'           => 'RESUELTA',
        'resuelta'         => true,
        'fecha_resolucion' => now(),
        'notas_resolucion' => $request->input('notas') ?? 'Resuelta manualmente.',
    ]);

    return response()->json(['status' => 'success', 'message' => 'Alerta resuelta.']);
});

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE UBICACIÓN (preferencias alertas)
|--------------------------------------------------------------------------
*/

Route::post('/locations/{ubicacion}/settings', function (
    Request $request,
    \App\Models\Ubicacion $ubicacion
) {
    $validated = $request->validate([
        'lixiviacion_alta'  => 'sometimes|boolean',
        'lixiviacion_media' => 'sometimes|boolean',
        'lixiviacion_baja'  => 'sometimes|boolean',
    ]);

    $ubicacion->update(['configuracion_alertas' => $validated]);

    return response()->json([
        'status'   => 'success',
        'message'  => 'Configuración guardada correctamente',
        'settings' => $ubicacion->configuracion_alertas,
    ]);
});

/*
|--------------------------------------------------------------------------
| HISTORIAN / ANALYTICS (usa tabla lecturas)
|--------------------------------------------------------------------------
*/

Route::get('/historian/daily', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');
    $days         = (int) $request->query('days', 30);

    if (!$ubicacion_id) {
        return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
    }

    $since = now()->subDays($days);

    $lecturas = \App\Models\Lectura::with('sensor')
        ->whereHas('sensor', fn($q) => $q->where('ubicacion_id', $ubicacion_id))
        ->where('fecha_registro', '>=', $since)
        ->orderBy('fecha_registro')
        ->get()
        ->groupBy(fn($r) => $r->fecha_registro->format('Y-m-d'))
        ->map(function ($dayReadings, $date) {
            $sup  = $dayReadings->filter(fn($r) => $r->sensor->profundidad <= 20);
            $prof = $dayReadings->filter(fn($r) => $r->sensor->profundidad > 20);

            return [
                'day'           => $date,
                'ce_sup_avg'    => $sup->avg('conductividad'),
                'ce_prof_avg'   => $prof->avg('conductividad'),
                'hum_sup_avg'   => $sup->avg('humedad'),
                'hum_prof_avg'  => $prof->avg('humedad'),
                'temp_sup_avg'  => $sup->avg('temperatura'),
                'temp_prof_avg' => $prof->avg('temperatura'),
                'n'             => $dayReadings->count(),
            ];
        })
        ->values();

    return response()->json(['status' => 'success', 'data' => $lecturas, 'days' => $days]);
});

Route::get('/readings/analytics', function (Request $request) {
    $ubicacion_id = $request->query('location_id') ?? $request->query('ubicacion_id');
    $days         = (int) $request->query('days', 30);

    if (!$ubicacion_id) {
        return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
    }

    $since    = now()->subDays($days);
    $sensores  = \App\Models\Sensor::where('ubicacion_id', $ubicacion_id)->get();
    $supSensor  = $sensores->first(fn($s) => $s->profundidad <= 20);
    $profSensor = $sensores->first(fn($s) => $s->profundidad > 20);

    $stats = fn($sensor) => $sensor
        ? \App\Models\Lectura::where('sensor_id', $sensor->id)
            ->where('fecha_registro', '>=', $since)
            ->selectRaw('AVG(conductividad) as ce_avg, AVG(humedad) as hum_avg, AVG(temperatura) as temp_avg,
                         MIN(humedad) as hum_min, MAX(humedad) as hum_max,
                         MIN(temperatura) as temp_min, MAX(temperatura) as temp_max,
                         COUNT(*) as total_readings')
            ->first()
        : null;

    $trend = fn($sensor) => $sensor
        ? \App\Models\Lectura::where('sensor_id', $sensor->id)
            ->where('fecha_registro', '>=', $since)
            ->selectRaw('DATE(fecha_registro) as day, AVG(humedad) as hum_avg, AVG(temperatura) as temp_avg')
            ->groupBy('day')
            ->get()
        : [];

    return response()->json([
        'status'      => 'success',
        'days'        => $days,
        'superficial' => ['stats' => $stats($supSensor),  'daily_trend' => $trend($supSensor)],
        'profundo'    => ['stats' => $stats($profSensor), 'daily_trend' => $trend($profSensor)],
    ]);
});

/*
|--------------------------------------------------------------------------
| EXPORT MODULE
|--------------------------------------------------------------------------
*/

Route::prefix('export')->group(function () {
    Route::get('/csv',  [ExportController::class, 'exportReadingsCSV']);
    Route::post('/csv', [ExportController::class, 'exportReadingsCSV']);
});