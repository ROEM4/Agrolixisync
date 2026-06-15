<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| CONTROLLERS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\SensorController as ApiSensorController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\AlertController as ApiAlertController;
use App\Http\Controllers\Api\ComparisonController;
use App\Http\Controllers\AnalysisController;
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
| SENSOR DATA (ESP32)
|--------------------------------------------------------------------------
*/
Route::post('/sensor/data', [ReadingController::class, 'recordReading'])
    ->middleware('throttle:100,1');

Route::get('/sensor/data', [ApiSensorController::class, 'getData']);

Route::get('/readings/latest', [ReadingController::class, 'getLatest']);
Route::get('/readings/history', [ReadingController::class, 'getHistory']);
Route::get('/analysis/latest', [ReadingController::class, 'getLatestAnalysis']);
Route::get('/dashboard/data', [ReadingController::class, 'getDashboardData']);

/*
|--------------------------------------------------------------------------
| LOTES / LOCATIONS
|--------------------------------------------------------------------------
*/
Route::post('/lotes', function (Request $request) {

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'crop_type' => 'nullable|string|max:255',
        'description' => 'nullable|string',
    ]);

    $lote = \App\Models\Lote::create([
        ...$validated,
        'crop_type' => $validated['crop_type'] ?? 'palta',
        'user_id' => auth()->id() ?? 1,
    ]);

    $location = $lote->locations()->create([
        'name' => $validated['name'],
        'latitude' => -12.0,
        'longitude' => -76.0,
        'is_active' => true,
    ]);

    return response()->json([
        'success' => true,
        'lote' => $lote,
        'location' => $location
    ], 201);
});

Route::get('/lotes', fn() =>
    \App\Models\Lote::with('locations')->get()
);

Route::get('/locations', fn() =>
    \App\Models\Location::with('lote')->get()
);

/*
|--------------------------------------------------------------------------
| ALERTS (FIXED + COMPATIBLE)
|--------------------------------------------------------------------------
*/

// 🔥 COMPATIBILIDAD FRONTEND (IMPORTANTE)
Route::get('/alerts', [ApiAlertController::class, 'index']);
Route::get('/alerts/list', [ApiAlertController::class, 'index']);

// Resolver alerta (FIX MODEL BINDING CORRECTO)
Route::post('/alerts/{alert}/resolve', [ApiAlertController::class, 'resolve']);

/*
|--------------------------------------------------------------------------
| LOCATION SETTINGS (SIN AUTH PARA FRONTEND)
|--------------------------------------------------------------------------
*/
Route::post('/locations/{location}/settings', function (
    Request $request,
    \App\Models\Location $location
) {
    // ✅ ACEPTAR LOS CAMPOS CORRECTOS DEL FRONTEND
    $validated = $request->validate([
        'lixiviacion_alta'  => 'sometimes|boolean',
        'lixiviacion_media' => 'sometimes|boolean',
        'lixiviacion_baja'  => 'sometimes|boolean',
    ]);

    $location->update([
        'alert_settings' => $validated
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Configuración guardada correctamente',
        'settings' => $location->alert_settings
    ]);
}); // ← SIN middleware('auth:sanctum')

/*
|--------------------------------------------------------------------------
| HISTORIAN / ANALYTICS (CORREGIDO)
|--------------------------------------------------------------------------
*/

// Histórico diario - USANDO CONSULTA DIRECTA
Route::get('/historian/daily', function (Request $request) {
    $location_id = $request->query('location_id');
    $days = (int) $request->query('days', 30);
    
    if (!$location_id) {
        return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
    }
    
    $since = now()->subDays($days);
    
    // Obtener readings agrupados por día
    $readings = \App\Models\Reading::with('sensor')
        ->whereHas('sensor', fn($q) => $q->where('location_id', $location_id))
        ->where('recorded_at', '>=', $since)
        ->orderBy('recorded_at')
        ->get()
        ->groupBy(fn($r) => $r->recorded_at->format('Y-m-d'))
        ->map(function ($dayReadings, $date) {
            $sup = $dayReadings->filter(fn($r) => $r->sensor->depth == 20);
            $prof = $dayReadings->filter(fn($r) => $r->sensor->depth == 60);
            
            return [
                'day' => $date,
                'ce_sup_avg' => $sup->avg('conductivity'),
                'ce_prof_avg' => $prof->avg('conductivity'),
                'hum_sup_avg' => $sup->avg('humidity'),
                'hum_prof_avg' => $prof->avg('humidity'),
                'temp_sup_avg' => $sup->avg('temperature'),
                'temp_prof_avg' => $prof->avg('temperature'),
                'n' => $dayReadings->count(),
            ];
        })
        ->values();
    
    return response()->json([
        'status' => 'success',
        'data' => $readings,
        'days' => $days
    ]);
});

// Analytics - USANDO CONSULTA DIRECTA
Route::get('/readings/analytics', function (Request $request) {
    $location_id = $request->query('location_id');
    $days = (int) $request->query('days', 30);
    
    if (!$location_id) {
        return response()->json(['status' => 'error', 'message' => 'location_id requerido'], 400);
    }
    
    $since = now()->subDays($days);
    
    $sensors = \App\Models\Sensor::where('location_id', $location_id)->get();
    $supSensor = $sensors->firstWhere('depth', 20);
    $profSensor = $sensors->firstWhere('depth', 60);
    
    $supStats = $supSensor ? \App\Models\Reading::where('sensor_id', $supSensor->id)
        ->where('recorded_at', '>=', $since)
        ->selectRaw('AVG(conductivity) as ce_avg, AVG(humidity) as hum_avg, AVG(temperature) as temp_avg, MIN(humidity) as hum_min, MAX(humidity) as hum_max, MIN(temperature) as temp_min, MAX(temperature) as temp_max, COUNT(*) as total_readings')
        ->first() : null;
    
    $profStats = $profSensor ? \App\Models\Reading::where('sensor_id', $profSensor->id)
        ->where('recorded_at', '>=', $since)
        ->selectRaw('AVG(conductivity) as ce_avg, AVG(humidity) as hum_avg, AVG(temperature) as temp_avg, MIN(humidity) as hum_min, MAX(humidity) as hum_max, MIN(temperature) as temp_min, MAX(temperature) as temp_max, COUNT(*) as total_readings')
        ->first() : null;
    
    return response()->json([
        'status' => 'success',
        'days' => $days,
        'superficial' => [
            'stats' => $supStats,
            'daily_trend' => \App\Models\Reading::where('sensor_id', $supSensor?->id)
                ->where('recorded_at', '>=', $since)
                ->selectRaw('DATE(recorded_at) as day, AVG(humidity) as hum_avg, AVG(temperature) as temp_avg')
                ->groupBy('day')
                ->get()
        ],
        'profundo' => [
            'stats' => $profStats,
            'daily_trend' => \App\Models\Reading::where('sensor_id', $profSensor?->id)
                ->where('recorded_at', '>=', $since)
                ->selectRaw('DATE(recorded_at) as day, AVG(humidity) as hum_avg, AVG(temperature) as temp_avg')
                ->groupBy('day')
                ->get()
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| ANALYSIS MODULE
|--------------------------------------------------------------------------
*/
Route::prefix('analysis')->group(function () {
    Route::get('/', [AnalysisController::class, 'index']);
    Route::post('/locations/{location}', [AnalysisController::class, 'analyzeLocation']);
});

/*
|--------------------------------------------------------------------------
| EXPORT MODULE
|--------------------------------------------------------------------------
*/
Route::prefix('export')->group(function () {
    Route::post('/csv', [ExportController::class, 'exportReadingsCSV']);
});

/*
|--------------------------------------------------------------------------
| COMPARISON MODULE
|--------------------------------------------------------------------------
*/
Route::get('/comparison/location/{location}', [ComparisonController::class, 'getLocationComparison']);