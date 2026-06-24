<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PlantaController;
use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\LixiviacionController;
use App\Http\Controllers\DetectionTimeController;
use App\Http\Controllers\PFRecordController;

use App\Http\Middleware\VerifiedAgricultor;

// ═══════════════════════════════════════════════════════════════════════════
// 🌐 RUTAS PÚBLICAS
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/monitor',   [DashboardController::class, 'realtime'])->name('monitor');
Route::get('/dashboard', [DashboardController::class, 'realtime'])->name('dashboard');
Route::get('/realtime',  [DashboardController::class, 'realtime'])->name('realtime');

// Login público
Route::get('/login',  [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);

Route::post('/logout', function () {
    Auth::logout();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// ═══════════════════════════════════════════════════════════════════════════
// 👤 USUARIOS (admin)
// ═══════════════════════════════════════════════════════════════════════════

Route::middleware([VerifiedAgricultor::class])->group(function () {
    Route::get('/usuarios',       [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios',      [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{id}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->name('usuarios.update');
});

// ═══════════════════════════════════════════════════════════════════════════
// 🌱 LOTES (plantas)
// ═══════════════════════════════════════════════════════════════════════════

Route::middleware([VerifiedAgricultor::class])->group(function () {
    Route::get('/plantas',                       [PlantaController::class, 'index'])->name('plantas.index');
    Route::get('/plantas/crear',                 [PlantaController::class, 'create'])->name('plantas.create');
    Route::post('/plantas',                      [PlantaController::class, 'store'])->name('plantas.store');
    Route::get('/plantas/{planta}/editar',       [PlantaController::class, 'edit'])->name('plantas.edit');
    Route::put('/plantas/{planta}',              [PlantaController::class, 'update'])->name('plantas.update');
    Route::delete('/plantas/{planta}',           [PlantaController::class, 'destroy'])->name('plantas.destroy');
});

// ═══════════════════════════════════════════════════════════════════════════
// 🔔 ALERTAS
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/alertas', function (Request $request) {
    $alertId    = $request->query('alert_id');
    $location_id = $request->query('location_id');
    $filter     = $request->query('filter', 'all');

    $ubicaciones = \App\Models\Ubicacion::with('planta')->orderBy('nombre')->get();

    // Plantas del grupo experimental
    $plantasGE = \App\Models\Planta::where('grupo_experimental', 'experimental')
        ->with('ubicaciones')
        ->orderBy('numero_planta')
        ->get();

    // ✅ CALCULAR PDS% DESDE evaluaciones_alerta (filtrado por ubicación si existe)
    $evalsQuery = \App\Models\EvaluacionAlerta::query();
    
    if ($location_id) {
        $evalsQuery->where('ubicacion_id', $location_id);
    }
    
    $vp = (clone $evalsQuery)->where('etiqueta', 'VP')->count();
    $fp = (clone $evalsQuery)->where('etiqueta', 'FP')->count();
    $total = $vp + $fp;
    $pds = $total > 0 ? round(($vp / $total) * 100, 1) : 0;

    $pdsData = [
        'vp'             => $vp,
        'fp'             => $fp,
        'total'          => $total,
        'pds_percentage' => $pds,
    ];

    return view('dashboard.alertas', compact(
        'ubicaciones',
        'alertId',
        'location_id',
        'filter',
        'plantasGE',
        'pdsData'
    ));
})->name('alertas');


// ⚡ QUICK RESOLVE (desde Telegram)
Route::get('/alertas/{alerta}/quick-resolve', function (\App\Models\Alerta $alerta) {

    $alerta->update([
        'resuelta'         => true,
        'fecha_resolucion' => now(),
        'estado'           => 'RESUELTA',
        'notas_resolucion' => 'Resuelta rápidamente desde Telegram',
    ]);

    try {
        $telegram      = resolve(\App\Services\TelegramService::class);
        $plantaNombre  = $alerta->ubicacion?->planta?->nombre ?? 'N/A';
        $ubicNombre    = $alerta->ubicacion?->nombre ?? 'N/A';

        $telegram->sendMessage(
            "✅ <b>ALERTA RESUELTA</b>\n" .
            "───────────────────\n" .
            "📍 <b>Planta:</b> {$plantaNombre}\n" .
            "📍 <b>Ubicación:</b> {$ubicNombre}\n" .
            "⚠️ <b>Nivel:</b> " . strtoupper($alerta->severidad ?? 'BAJO') . "\n" .
            "🕐 <b>Resuelta:</b> " . now()->format('d/m/Y H:i') . "\n\n" .
            "<i>Se detendrán las notificaciones automáticas.</i>"
        );
    } catch (\Exception $e) {
        \Log::error('Error notificando Telegram: ' . $e->getMessage());
    }

    return redirect()
        ->route('alertas')
        ->with('success', "✅ Alerta #{$alerta->id} marcada como resuelta.");

})->name('alertas.quick-resolve');


// 🧹 CERRAR DÍA
Route::post('/alertas/cerrar-dia', function (Request $request) {

    $today = now()->toDateString();

    $updated = \App\Models\ObservacionCampo::whereDate('created_at', $today)
        ->update(['consolidado' => true]);

    return response()->json([
        'status'  => 'success',
        'message' => "Día cerrado. Se consolidaron {$updated} observaciones.",
        'count'   => $updated,
    ]);

})->name('alertas.cerrar_dia');

// ═══════════════════════════════════════════════════════════════════════════
// 📊 HISTÓRICO / ANÁLISIS / LIXIVIACIÓN
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/historico', function (Request $request) {
    $location_id = $request->query('location_id');

    // Solo plantas del Grupo Experimental (tienen sensores IoT)
    $plantasGE = \App\Models\Planta::where('grupo_experimental', 'experimental')
        ->with('ubicaciones')
        ->orderBy('numero_planta')
        ->get();

    return view('dashboard.historico', compact('location_id', 'plantasGE'));
})->name('historico');

Route::get('/analisis',             [AnalisisController::class, 'index'])->name('analisis');
Route::get('/analisis/export',      [AnalisisController::class, 'export'])->name('analisis.export');
Route::post('/analisis/pf-manual',  [AnalisisController::class, 'pfManual'])->name('analisis.pf_manual');

Route::get('/analisis/ubicaciones-disponibles', [AnalisisController::class, 'ubicacionesDisponibles'])
    ->name('analisis.ubicaciones_disponibles');

// Evaluación de alertas (VP/FP/FN)
Route::post('/analisis/evaluar-alerta/{alerta}', [AnalisisController::class, 'evaluarAlerta'])->name('analisis.evaluar_alerta');
Route::post('/analisis/cerrar-dia',              [AnalisisController::class, 'cerrarDia'])->name('analisis.cerrar_dia');

Route::get('/lixiviacion',          [LixiviacionController::class, 'index'])->name('lixiviacion');
Route::get('/lixiviacion/export',   [LixiviacionController::class, 'export'])->name('lixiviacion.export');
Route::post('/lixiviacion/manual',  [LixiviacionController::class, 'storeManual'])->name('lixiviacion.store_manual');

// ═══════════════════════════════════════════════════════════════════════════
// ⏱ DETECCIÓN
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/detection-time',                         [DetectionTimeController::class, 'index'])->name('detection_time');
Route::get('/detection-time/export',                  [DetectionTimeController::class, 'export'])->name('detection_time.export');
Route::post('/detection-time/manual',                 [DetectionTimeController::class, 'storeManual'])->name('detection_time.store_manual');
Route::put('/detection-time/manual/{record}',         [DetectionTimeController::class, 'updateManual'])->name('detection_time.update_manual');

// Ficha PF
Route::get('/pf-ficha',          [PFRecordController::class, 'index'])->name('pf.ficha.index');
Route::post('/pf-ficha',         [PFRecordController::class, 'store'])->name('pf.ficha.store');
Route::get('/pf-ficha/export',   [PFRecordController::class, 'export'])->name('pf.ficha.export');

// ═══════════════════════════════════════════════════════════════════════════
// 📡 APIs PARA LECTURAS Y ALERTAS
// ═══════════════════════════════════════════════════════════════════════════

// Lecturas (usadas por realtime.blade.php y historico.blade.php)
Route::get('/api/readings/latest', [App\Http\Controllers\Api\LecturaController::class, 'latest'])->name('api.readings.latest');
Route::get('/api/readings/history', [App\Http\Controllers\Api\LecturaController::class, 'history'])->name('api.readings.history');
Route::get('/api/readings/analytics', [App\Http\Controllers\Api\LecturaController::class, 'analytics'])->name('api.readings.analytics');
Route::get('/api/historian/daily', [App\Http\Controllers\Api\LecturaController::class, 'daily'])->name('api.historian.daily');
Route::post('/api/readings/record', [App\Http\Controllers\Api\LecturaController::class, 'recordReading'])->name('api.readings.record');

// Alertas
Route::get('/api/alerts/list', [App\Http\Controllers\Api\AlertaController::class, 'index'])->name('api.alerts.list');
Route::get('/api/alerts/{alerta}/resolve', [App\Http\Controllers\Api\AlertaController::class, 'resolve'])->name('api.alerts.resolve');

// ═══════════════════════════════════════════════════════════════════════════
// 🔄 SINCRONIZACIÓN ENTRE VISTAS (realtime ↔ analisis ↔ alertas ↔ historico)
// ═══════════════════════════════════════════════════════════════════════════
Route::post('/api/set-location', function (Request $request) {
    $request->validate([
        'location_id' => 'nullable|exists:ubicaciones,id'
    ]);
    
    if ($request->location_id) {
        session(['agro_loc' => $request->location_id]);
    } else {
        session()->forget('agro_loc');
    }
    
    return response()->json(['success' => true]);
})->middleware('auth')->name('api.set_location');

// ═══════════════════════════════════════════════════════════════════════════
// 📱 TEST TELEGRAM
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/test-telegram', function () {
    try {
        $telegram = resolve(\App\Services\TelegramService::class);
        $success  = $telegram->sendMessage("✅ <b>AgroLixiSync:</b> Conexión exitosa");
        return response()->json([
            'success' => $success,
            'message' => $success ? 'OK' : 'Error',
            'config'  => [
                'token_present' => !empty(env('TELEGRAM_BOT_TOKEN')),
                'chat_id'       => env('TELEGRAM_CHAT_ID'),
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
})->middleware('auth');

// ═══════════════════════════════════════════════════════════════════════════
// 🔁 ROOT
// ═══════════════════════════════════════════════════════════════════════════

Route::redirect('/', '/login');