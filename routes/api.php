<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SensorController as ApiSensorController;
use App\Http\Controllers\Api\ComparisonController;
use App\Http\Controllers\Api\ReadingController;
use App\Modules\Historian\HistorianService;
use App\Modules\Storage\SdIngestionService;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\ExportController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ═══════════════════════════════════════════════════════════════════════════
// 🚀 API PARA ESP32 - TIEMPO REAL CON WEBSOCKETS + VALIDACIÓN INDUSTRIAL
// ═══════════════════════════════════════════════════════════════════════════

// Recibir datos del ESP32 (NUEVO: Validado, con detección de lixiviación, eventos WebSocket)
Route::post('/sensor/data', [ReadingController::class, 'recordReading'])
    ->middleware(['api', 'throttle:100,1'])
    ->name('api.sensor.data');

// Obtener datos para gráficos (AJAX)
Route::get('/sensor/data', [ApiSensorController::class, 'getData']);

// ═══════════════════════════════════════════════════════════════════════════
// 📊 NUEVOS ENDPOINTS - TIEMPO REAL + ANÁLISIS
// ═══════════════════════════════════════════════════════════════════════════

// Obtener última lectura
Route::get('/readings/latest', [ReadingController::class, 'getLatest'])
    ->name('api.readings.latest');

// Historial de lecturas para dashboard (pares sup+prof por timestamp)
Route::get('/readings/history', [ReadingController::class, 'getHistory'])
    ->name('api.readings.history');

// Obtener análisis más reciente
Route::get('/analysis/latest', [ReadingController::class, 'getLatestAnalysis'])
    ->name('api.analysis.latest');

// Datos completos para dashboard (instantáneo + histórico)
Route::get('/dashboard/data', [ReadingController::class, 'getDashboardData'])
    ->name('api.dashboard.data');

// ═══════════════════════════════════════════════════════════════════════════
// 📍 GESTIÓN DE UBICACIONES/LOTES
// ═══════════════════════════════════════════════════════════════════════════

// Crear lote (con location por defecto)
Route::post('/lotes', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'crop_type' => 'nullable|string|max:255',
        'description' => 'nullable|string',
    ]);

    $validated['crop_type'] = $validated['crop_type'] ?? 'palta';
    $validated['user_id'] = auth()->id() ?? 1; // Admin por defecto

    $lote = \App\Models\Lote::create($validated);
    
    // Crear location por defecto en el lote
    $location = $lote->locations()->create([
        'name' => $validated['name'],
        'latitude' => -25.2637,
        'longitude' => -57.5759,
        'is_active' => true,
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Lote creado correctamente',
        'lote' => $lote,
        'location' => $location
    ], 201);
});

// Obtener lotes del usuario
Route::get('/lotes', function (Request $request) {
    $lotes = \App\Models\Lote::where('user_id', auth()->id() ?? 1)
        ->with('locations')
        ->get();
    return response()->json($lotes);
});

// Obtener todas las ubicaciones (para selector)
Route::get('/locations', function () {
    return response()->json(\App\Models\Location::with('lote')->get());
});

// ═══════════════════════════════════════════════════════════════════════════
// 📊 HISTORIAN — Datos históricos diarios (Historian module)
// ═══════════════════════════════════════════════════════════════════════════

// GET /api/historian/daily?location_id=&days=7
// Devuelve promedios diarios de CE, humedad y temperatura desde readings_daily.
// Humedad y temperatura son módulo analítico dentro de la capa de lecturas.
Route::get('/historian/daily', function (Request $request, HistorianService $historian) {
    $location_id = (int) $request->query('location_id');
    $days        = max(1, min(90, (int) $request->query('days', 7)));
    if (!$location_id) return response()->json(['error' => 'location_id requerido'], 400);

    // Auto-aggregate to ensure readings_daily is up to date without cron
    $historian->aggregateRange($location_id, $days);

    $grouped = $historian->getDaily($location_id, $days);
    
    // Flatten by day for the frontend
    $flat = [];
    foreach ($grouped['superficial'] as $sup) {
        $flat[$sup['day']] = [
            'day'          => $sup['day'],
            'ce_sup_avg'   => $sup['ce']['avg'],
            'hum_sup_avg'  => $sup['humidity']['avg'],
            'temp_sup_avg' => $sup['temp']['avg'],
            'n'            => $sup['n']
        ];
    }
    foreach ($grouped['profundo'] as $prof) {
        $day = $prof['day'];
        if (!isset($flat[$day])) $flat[$day] = ['day' => $day, 'n' => 0];
        $flat[$day]['ce_prof_avg']   = $prof['ce']['avg'];
        $flat[$day]['hum_prof_avg']  = $prof['humidity']['avg'];
        $flat[$day]['temp_prof_avg'] = $prof['temp']['avg'];
        $flat[$day]['n']            += $prof['n'];
    }
    ksort($flat);

    return response()->json([
        'status'      => 'success',
        'location_id' => $location_id,
        'days'        => $days,
        'data'        => array_values($flat),
    ]);
})->name('api.historian.daily');

// GET /api/historian/range?location_id=&from=2026-04-01&to=2026-04-20
Route::get('/historian/range', function (Request $request, HistorianService $historian) {
    $location_id = (int) $request->query('location_id');
    $from        = $request->query('from');
    $to          = $request->query('to', now()->toDateString());
    if (!$location_id || !$from) return response()->json(['error' => 'location_id y from requeridos'], 400);

    return response()->json([
        'status'      => 'success',
        'location_id' => $location_id,
        'from'        => $from,
        'to'          => $to,
        'data'        => $historian->getRange($location_id, $from, $to),
    ]);
})->name('api.historian.range');

// POST /api/historian/aggregate?location_id=&days=1  (on-demand, sin cron)
Route::post('/historian/aggregate', function (Request $request, HistorianService $historian) {
    $location_id = (int) $request->query('location_id');
    $days        = max(1, min(30, (int) $request->query('days', 1)));
    if (!$location_id) return response()->json(['error' => 'location_id requerido'], 400);

    $results = $historian->aggregateRange($location_id, $days);
    return response()->json(['status' => 'success', 'aggregated' => $results]);
})->name('api.historian.aggregate');

// Importar CSV desde microSD (usa SdIngestionService del módulo Storage)
Route::post('/import/csv', function (Request $request, SdIngestionService $sdIngestion) {
    $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
    $result = $sdIngestion->processFile($request->file('file')->getRealPath());
    return response()->json(['status' => 'success', ...$result]);
});

// Exportar datos a CSV
Route::get('/export/csv', function (Request $request) {
    $locationId = $request->query('location_id');

    if (!$locationId) {
        return response()->json(['error' => 'Se requiere location_id'], 400);
    }

    $location = \App\Models\Location::with('lote')->find($locationId);
    if (!$location) {
        return response()->json(['error' => 'Ubicación no encontrada'], 404);
    }

    // Obtener sensores separados por profundidad
    $sensors = \App\Models\Sensor::where('location_id', $locationId)->get()
        ->keyBy(fn($s) => (int) $s->depth);
    $sensor_sup  = $sensors->get(20);
    $sensor_prof = $sensors->get(60);

    if (!$sensor_sup && !$sensor_prof) {
        return response()->json(['error' => 'No hay sensores para esta ubicación'], 404);
    }

    // Obtener timestamps únicos con sus lecturas de ambos sensores
    $sensorIds = $sensors->pluck('id');
    $timestamps = \App\Models\Reading::whereIn('sensor_id', $sensorIds)
        ->orderBy('recorded_at')
        ->pluck('recorded_at')
        ->unique()
        ->values();

    if ($timestamps->isEmpty()) {
        return response()->json(['error' => 'No hay datos para exportar'], 404);
    }

    $loteName = $location->lote->name ?? $location->name;
    $filename = 'AgroLixiSync_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $loteName)
                . '_' . now()->format('Y-m-d_Hi') . '.csv';

    $headers = [
        'Content-Type'        => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ];

    $callback = function () use ($timestamps, $sensor_sup, $sensor_prof, $location, $loteName) {
        $file = fopen('php://output', 'w');
        // BOM para Excel
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($file, [
            'Fecha/Hora (RTC)',
            'Lote',
            'Ubicación',
            'CE Superficial 20cm (dS/m)',
            'CE Profundo 60cm (dS/m)',
            'ILx (CE_p / CE_s)  [PRINCIPAL]',
            'ΔCE (Sup - Prof)    [SECUNDARIO]',
            'Estado ILx',
            'Humedad Sup (%)',
            'Temp Sup (°C)',
            'Humedad Prof (%)',
            'Temp Prof (°C)',
            'Lixiviación detectada',
            'Nivel de riesgo',
            'Estado dispositivo',
        ]);

        foreach ($timestamps as $ts) {
            $r_sup  = $sensor_sup  ? \App\Models\Reading::where('sensor_id', $sensor_sup->id)
                ->where('recorded_at', $ts)->first() : null;
            $r_prof = $sensor_prof ? \App\Models\Reading::where('sensor_id', $sensor_prof->id)
                ->where('recorded_at', $ts)->first() : null;

            if (!$r_sup && !$r_prof) continue;

            // Conductividad con precisión exacta (6 decimales, sin ceros finales)
            $ce_s_raw = $r_sup  ? rtrim(rtrim(number_format((float)$r_sup->conductivity,  6, '.', ''), '0'), '.') : '';
            $ce_p_raw = $r_prof ? rtrim(rtrim(number_format((float)$r_prof->conductivity, 6, '.', ''), '0'), '.') : '';

            // Convertir raw strings a float para cálculos
            $ce_s = $ce_s_raw !== '' ? (float)$ce_s_raw : null;
            $ce_p = $ce_p_raw !== '' ? (float)$ce_p_raw : null;

            // ── ILx: indicador principal ───────────────────────────────────
            $ilx_val = ($ce_s !== null && $ce_p !== null && $ce_s > 0)
                ? $ce_p / $ce_s : null;
            $ilx_str = $ilx_val !== null
                ? rtrim(rtrim(number_format($ilx_val, 6, '.', ''), '0'), '.') : '';

            // ── ΔCE: indicador secundario (complementario) ───────────────
            $delta = ($ce_s !== null && $ce_p !== null)
                ? rtrim(rtrim(number_format($ce_s - $ce_p, 6, '.', ''), '0'), '.') : '';

            // ── Estado ILx (clasificación agronómica) ──────────────────────
            $ilx_estado  = '';
            $lixiviation = '';
            $risk        = '';
            if ($ilx_val !== null) {
                if ($ilx_val > 1.20)       { $ilx_estado = 'LIXIVIACIÓN ALTA'; $lixiviation = 'SÍ'; $risk = 'ALTO'; }
                elseif ($ilx_val > 1.05)   { $ilx_estado = 'LIXIVIACIÓN';      $lixiviation = 'SÍ'; $risk = 'MEDIO'; }
                elseif ($ilx_val >= 0.90)  { $ilx_estado = 'EQUILIBRIO';        $lixiviation = 'NO'; $risk = 'BAJO'; }
                elseif ($ilx_val >= 0.70)  { $ilx_estado = 'RETENCIÓN';        $lixiviation = 'NO'; $risk = 'BAJO'; }
                else                        { $ilx_estado = 'ACUMULACIÓN';       $lixiviation = 'SÍ'; $risk = 'MEDIO'; }
            }

            $recorded = ($r_sup ?? $r_prof)->recorded_at->format('Y-m-d H:i:s');
            $estado   = $r_sup->device_estado ?? $r_prof->device_estado ?? '';

            fputcsv($file, [
                $recorded,
                $loteName,
                $location->name,
                $ce_s_raw,
                $ce_p_raw,
                $ilx_str,       // ILx [PRINCIPAL]
                $delta,         // ΔCE [SECUNDARIO]
                $ilx_estado,
                $r_sup  ? number_format((float)$r_sup->humidity,    2, '.', '') : '',
                $r_sup  ? number_format((float)$r_sup->temperature,  2, '.', '') : '',
                $r_prof ? number_format((float)$r_prof->humidity,   2, '.', '') : '',
                $r_prof ? number_format((float)$r_prof->temperature, 2, '.', '') : '',
                $lixiviation,
                $risk,
                $estado,
            ]);
        }

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
});

// ═══════════════════════════════════════════════════════════════════════════
// 📊 MÓDULO ANALÍTICO — Humedad y Temperatura (agrupado desde readings)
// GET /api/readings/analytics?location_id=&days=7
//
// Devuelve por sensor (sup/prof):
//   - promedio diario de humedad y temperatura (para gráfico de tendencia)
//   - min/max del período
//   - última lectura
// No es una tabla separada: todo viene de sensor_readings agrupado en backend.
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/readings/analytics', function (Request $request) {
    $location_id = (int) $request->query('location_id');
    $days        = max(1, min(90, (int) $request->query('days', 7)));

    if (!$location_id) {
        return response()->json(['error' => 'location_id requerido'], 400);
    }

    $sensors = \App\Models\Sensor::where('location_id', $location_id)
        ->get()
        ->keyBy(fn($s) => (int) $s->depth);

    $since = now()->subDays($days);

    $buildAnalytics = function ($sensor) use ($since, $days) {
        if (!$sensor) return null;

        // Promedio diario agrupado en MySQL — sin cargar todas las filas en PHP
        $daily = \Illuminate\Support\Facades\DB::table('readings')
            ->where('sensor_id', $sensor->id)
            ->where('recorded_at', '>=', $since)
            ->whereNotNull('humidity')
            ->selectRaw('DATE(recorded_at) as day, AVG(humidity) as avg_hum, AVG(temperature) as avg_temp, COUNT(*) as n')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $stats = \Illuminate\Support\Facades\DB::table('readings')
            ->where('sensor_id', $sensor->id)
            ->where('recorded_at', '>=', $since)
            ->whereNotNull('humidity')
            ->selectRaw('
                MIN(humidity)    as hum_min,
                MAX(humidity)    as hum_max,
                AVG(humidity)    as hum_avg,
                MIN(temperature) as temp_min,
                MAX(temperature) as temp_max,
                AVG(temperature) as temp_avg,
                COUNT(*)         as total
            ')
            ->first();

        $last = \App\Models\Reading::where('sensor_id', $sensor->id)
            ->whereNotNull('humidity')
            ->orderByDesc('recorded_at')
            ->first();

        return [
            'sensor_id'  => $sensor->id,
            'depth'      => (int) $sensor->depth,
            'label'      => (int) $sensor->depth === 20 ? 'Superficial 20cm' : 'Profundo 60cm',
            'period_days'=> $days,
            'stats' => [
                'hum_min'  => $stats ? round((float)$stats->hum_min,  2) : null,
                'hum_max'  => $stats ? round((float)$stats->hum_max,  2) : null,
                'hum_avg'  => $stats ? round((float)$stats->hum_avg,  2) : null,
                'temp_min' => $stats ? round((float)$stats->temp_min, 2) : null,
                'temp_max' => $stats ? round((float)$stats->temp_max, 2) : null,
                'temp_avg' => $stats ? round((float)$stats->temp_avg, 2) : null,
                'total_readings' => $stats ? (int)$stats->total : 0,
            ],
            'daily_trend' => $daily->map(fn($r) => [
                'day'      => $r->day,
                'hum_avg'  => round((float)$r->avg_hum,  2),
                'temp_avg' => round((float)$r->avg_temp, 2),
                'n'        => (int)$r->n,
            ])->values(),
            'last' => $last ? [
                'humidity'    => $last->humidity    !== null ? round((float)$last->humidity,    2) : null,
                'temperature' => $last->temperature !== null ? round((float)$last->temperature, 2) : null,
                'recorded_at' => $last->recorded_at->toIso8601String(),
            ] : null,
        ];
    };

    return response()->json([
        'status'     => 'success',
        'location_id'=> $location_id,
        'days'       => $days,
        'superficial'=> $buildAnalytics($sensors->get(20)),
        'profundo'   => $buildAnalytics($sensors->get(60)),
    ]);
})->name('api.readings.analytics');

// Métricas y validación manual eliminadas a favor del sistema automático AnalisisService

// Listar alertas (para vista dashboard/alertas)
Route::get('/alerts/list', function (\Illuminate\Http\Request $request) {
    $q = \App\Models\Alert::with(['location.lote'])
        ->orderByDesc('created_at');

    if ($request->location_id) $q->where('location_id', $request->location_id);
    if ($request->risk_level)  $q->where('severity', $request->risk_level);
    if ($request->status === 'resolved')  $q->where('is_resolved', true);
    if ($request->status === 'open')      $q->where('is_resolved', false);

    $limit = $request->limit === 'all' ? 10000 : (int)($request->limit ?? 200);
    $alerts = $q->limit($limit)->get()
        ->map(function($a) {
            // Obtener el TPD del día correspondiente
            $tpd = null;
            if ($a->tiempo_alerta && $a->location_id) {
                $detectionRecord = \App\Models\DetectionTimeRecord::where('fecha', $a->tiempo_alerta->format('Y-m-d'))
                    ->where('location_id', $a->location_id)
                    ->first();
                $tpd = $detectionRecord ? $detectionRecord->tiempo_promedio_segundos : null;
            }
            
            return array_merge($a->toArray(), [
                'risk_level' => strtoupper($a->severity ?? $a->level ?? 'BAJO'),
                'tpd' => $tpd,
            ]);
        });

    return response()->json(['status' => 'success', 'data' => $alerts]);
})->name('api.alerts.list');

Route::post('/alerts/{alert}/resolve', function (\App\Models\Alert $alert, \Illuminate\Http\Request $request) {
    $alert->update([
        'is_resolved' => true,
        'resolved_at' => now(),
        'status' => 'RESOLVED'
    ]);
    
    // Notificar a Telegram
    try {
        $telegram = resolve(\App\Services\TelegramService::class);
        $loteName = $alert->location->lote->name ?? $alert->location->name;
        $telegram->sendMessage("✅ <b>ALERTA RESUELTA</b>\n\n" .
            "La alerta en <b>{$loteName}</b> ha sido marcada como resuelta.\n" .
            "Se detendrán las notificaciones automáticas para este evento.");
    } catch (\Exception $e) {
        \Log::error('Error notificando resolución Telegram: ' . $e->getMessage());
    }

    return response()->json(['status' => 'success']);
})->name('api.alerts.resolve');

Route::post('/locations/{location}/settings', function (\App\Models\Location $location, \Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'lixiviacion_alta' => 'required|boolean',
        'lixiviacion'      => 'required|boolean',
        'acumulacion'      => 'required|boolean',
    ]);

    $location->update(['alert_settings' => $validated]);

    return response()->json(['status' => 'success', 'settings' => $location->alert_settings]);
})->name('api.locations.settings.update');

// Comparación específica de ubicación
Route::get('/comparison/location/{location}', [ComparisonController::class, 'getLocationComparison']);
Route::get('/comparison/location/{location}/stats', [ComparisonController::class, 'getLocationStats']);
Route::get('/comparison/location/{location}/recent-analysis', [ComparisonController::class, 'getRecentAnalysis']);

// Endpoint legacy (mantener compatibilidad)
Route::post('/readings', function (Request $request) {
    return redirect('/api/sensor/data')->with($request->all());
});

// ========================================
// RUTAS AUTENTICADAS - Nueva API v2
// ========================================

Route::middleware('auth:sanctum')->group(function () {
    
    // ========================================
    // Sensores y Lecturas
    // ========================================
    Route::prefix('sensors')->group(function () {
        Route::get('/', [SensorController::class, 'index']);
        Route::post('/', [SensorController::class, 'store']);
        Route::get('/{sensor}', [SensorController::class, 'show']);
        Route::put('/{sensor}', [SensorController::class, 'update']);
        Route::post('/{sensor}/readings', [SensorController::class, 'recordReading']);
        Route::get('/{sensor}/readings', [SensorController::class, 'getReadings']);
        Route::get('/{sensor}/statistics', [SensorController::class, 'getStatistics']);
        Route::post('/{sensor}/deactivate', [SensorController::class, 'deactivate']);
        Route::post('/{sensor}/activate', [SensorController::class, 'activate']);
    });

    // Salud de sensores por ubicación
    Route::get('/locations/{location}/health', [SensorController::class, 'getLocationHealth']);

    // ========================================
    // Análisis Comparativo Multi-Sensor
    // ========================================
    Route::prefix('comparison')->group(function () {
        Route::post('/location/{location}/analyze', [ComparisonController::class, 'analyzeLocation']);
    });

    // ========================================
    // Análisis de Lixiviación
    // ========================================
    Route::prefix('analysis')->group(function () {
        Route::get('/', [AnalysisController::class, 'index']);
        Route::get('/{analysis}', [AnalysisController::class, 'show']);
        Route::post('/locations/{location}/analyze', [AnalysisController::class, 'analyzeLocation']);
        Route::get('/lotes/{lote}/summary', [AnalysisController::class, 'getLoteSummary']);
        Route::get('/locations/{location}/history', [AnalysisController::class, 'getLocationHistory']);
        Route::get('/alerts/lixiviation', [AnalysisController::class, 'getLixiviationAlerts']);
    });

    // Ejecutar análisis automático (solo admin)
    Route::post('/analysis/run-auto', [AnalysisController::class, 'runAutoAnalysis']);

    // ========================================
    // Exportación de Datos
    // ========================================
    Route::prefix('export')->group(function () {
        Route::post('/readings/csv', [ExportController::class, 'exportReadingsCSV']);
        Route::post('/analysis/csv', [ExportController::class, 'exportAnalysisCSV']);
        Route::post('/analysis/report-html', [ExportController::class, 'generateAnalysisReport']);
        Route::post('/analysis/report-pdf', [ExportController::class, 'generateAnalysisPDF']);
        Route::post('/sensors/comparison-csv', [ExportController::class, 'exportSensorComparison']);
    });

    // ========================================
    // Métricas de Tesis Académica
    // ========================================
    Route::prefix('thesis-metrics')->group(function () {
        // Obtener últimas métricas
        Route::get('/latest', [\App\Http\Controllers\Api\ThesisMetricsController::class, 'getLatest']);

        // Resumen completo con interpretaciones
        Route::get('/summary', [\App\Http\Controllers\Api\ThesisMetricsController::class, 'getSummary']);

        // Evolución de indicadores
        Route::get('/evolution', [\App\Http\Controllers\Api\ThesisMetricsController::class, 'getEvolution']);

        // Validar integridad de datos
        Route::get('/validate-data', [\App\Http\Controllers\Api\ThesisMetricsController::class, 'validateData']);

        // Reporte detallado de PDS
        Route::get('/pds-report', [\App\Http\Controllers\Api\ThesisMetricsController::class, 'getPDSReport']);
    });
});

