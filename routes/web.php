<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\LixiviacionController;
use App\Http\Controllers\DetectionTimeController;
use App\Http\Controllers\PFRecordController;

use App\Http\Middleware\VerifiedAgricultor;

// ═══════════════════════════════════════════════════════════════════════════
// 🌐 RUTAS PÚBLICAS
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/monitor', [DashboardController::class, 'realtime'])->name('monitor');
Route::get('/dashboard', [DashboardController::class, 'realtime'])->name('dashboard');
Route::get('/realtime', [DashboardController::class, 'realtime'])->name('realtime');

// Login público
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);

Route::post('/logout', function () {
    Auth::logout();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// ═══════════════════════════════════════════════════════════════════════════
// 👤 PERFIL + EXPORT (protegido)
// ═══════════════════════════════════════════════════════════════════════════

Route::middleware([VerifiedAgricultor::class])->group(function () {

    Route::get('/lotes', [LoteController::class, 'index'])->name('lotes.index');
    Route::get('/lotes/crear', [LoteController::class, 'create'])->name('lotes.create');
    Route::post('/lotes', [LoteController::class, 'store'])->name('lotes.store');

    // EDITAR
    Route::get('/lotes/{lote}/editar', [LoteController::class, 'edit'])
        ->name('lotes.edit');

    // ACTUALIZAR
    Route::put('/lotes/{lote}', [LoteController::class, 'update'])
        ->name('lotes.update');

});

// ═══════════════════════════════════════════════════════════════════════════
// 👥 USUARIOS (admin)
// ═══════════════════════════════════════════════════════════════════════════

Route::middleware([VerifiedAgricultor::class])->group(function () {

    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');

});

// ═══════════════════════════════════════════════════════════════════════════
// 🌱 LOTES
// ═══════════════════════════════════════════════════════════════════════════

Route::middleware([VerifiedAgricultor::class])->group(function () {

    Route::get('/lotes', [LoteController::class, 'index'])->name('lotes.index');
    Route::get('/lotes/crear', [LoteController::class, 'create'])->name('lotes.create');
    Route::post('/lotes', [LoteController::class, 'store'])->name('lotes.store');

});

// ═══════════════════════════════════════════════════════════════════════════
// 🔔 ALERTAS
// ═══════════════════════════════════════════════════════════════════════════

// Vista principal de alertas
Route::get('/alertas', function (Request $request) {
    $locations = \App\Models\Location::with('lote')->orderBy('name')->get();
    $alertId = $request->query('alert_id');
    $location_id = $request->query('location_id');
    $filter = $request->query('filter', 'all');
    
    // 🆕 Cargar plantas del GE
    $lotesGE = \App\Models\Lote::where('experimental_group', 'experimental')
        ->orderBy('plant_number')
        ->get();
    
    // 🆕 Calcular PDS% desde AlertEvaluation
    $vp = \App\Models\AlertEvaluation::where('label', 'VP')->count();
    $fp = \App\Models\AlertEvaluation::where('label', 'FP')->count();
    $fn = \App\Models\AlertEvaluation::where('label', 'FN')->count();
    $total = $vp + $fp + $fn;
    $pds = $total > 0 ? round(($vp / $total) * 100, 1) : 0;
    
    $pdsData = [
        'vp' => $vp,
        'fp' => $fp,
        'fn' => $fn,
        'pds_percentage' => $pds,
    ];
    
    return view('dashboard.alertas', compact(
        'locations', 
        'alertId', 
        'location_id', 
        'filter', 
        'lotesGE',
        'pdsData'
    ));
})->name('alertas');


// ⚡ QUICK RESOLVE (Telegram)
Route::get('/alertas/{alert}/quick-resolve', function (\App\Models\Alert $alert) {

    $alert->update([
        'is_resolved' => true,
        'resolved_at' => now(),
        'status' => 'RESOLVED',
        'resolution_notes' => 'Resuelta rápidamente desde Telegram'
    ]);

    try {
        $telegram = resolve(\App\Services\TelegramService::class);

        $loteName = $alert->location->lote->name ?? $alert->location->name;
        $locationName = $alert->location->name ?? 'N/A';

        $telegram->sendMessage(
            "✅ <b>ALERTA RESUELTA</b>\n" .
            "───────────────────\n" .
            "📍 <b>Lote:</b> {$loteName}\n" .
            "📍 <b>Ubicación:</b> {$locationName}\n" .
            "⚠️ <b>Nivel:</b> " . strtoupper($alert->severity ?? 'BAJO') . "\n" .
            "🕐 <b>Resuelta:</b> " . now()->format('d/m/Y H:i') . "\n\n" .
            "<i>Se detendrán las notificaciones automáticas.</i>"
        );

    } catch (\Exception $e) {
        \Log::error('Error notificando Telegram: ' . $e->getMessage());
    }

    return redirect()
        ->route('alertas')
        ->with('success', "✅ Alerta #{$alert->id} marcada como resuelta.");

})->name('alertas.quick-resolve');


// 🧹 CERRAR DÍA (NUEVO)
Route::post('/alertas/cerrar-dia', function (Request $request) {

    $today = now()->toDateString();

    $updated = \App\Models\AlertEvaluation::whereDate('created_at', $today)
        ->where('consolidated', false)
        ->update([
            'consolidated' => true,
            'consolidated_at' => now()
        ]);

    return response()->json([
        'status' => 'success',
        'message' => "Día cerrado. Se consolidaron {$updated} evaluaciones.",
        'count' => $updated
    ]);

})->name('alertas.cerrar_dia');

// ═══════════════════════════════════════════════════════════════════════════
// 📊 HISTÓRICO / ANÁLISIS / LIXIVIACIÓN
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/historico', function (Request $request) {
    $location_id = $request->query('location_id');
    
    // Solo cargar plantas del Grupo Experimental (tienen sensores IoT)
    $lotesGE = \App\Models\Lote::where('experimental_group', 'experimental')
        ->orderBy('plant_number')
        ->get();
    
    return view('dashboard.historico', compact('location_id', 'lotesGE'));
})->name('historico');

Route::get('/analisis', [AnalisisController::class, 'index'])->name('analisis');
Route::get('/analisis/export', [AnalisisController::class, 'export'])->name('analisis.export');
Route::post('/analisis/pf-manual', [AnalisisController::class, 'pfManual'])->name('analisis.pf_manual');

// 🆕 RUTAS PARA EVALUACIÓN DE ALERTAS
Route::post('/analisis/evaluar-alerta/{alert}', [AnalisisController::class, 'evaluarAlerta'])->name('analisis.evaluar_alerta');
Route::post('/analisis/cerrar-dia', [AnalisisController::class, 'cerrarDia'])->name('analisis.cerrar_dia');

Route::get('/lixiviacion', [LixiviacionController::class, 'index'])->name('lixiviacion');
Route::get('/lixiviacion/export', [LixiviacionController::class, 'export'])->name('lixiviacion.export');
Route::post('/lixiviacion/manual', [LixiviacionController::class, 'storeManual'])->name('lixiviacion.store_manual');

// ═══════════════════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════════════════
// ⏱ DETECCIÓN
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/detection-time', [DetectionTimeController::class, 'index'])->name('detection_time');
Route::get('/detection-time/export', [DetectionTimeController::class, 'export'])->name('detection_time.export');
Route::post('/detection-time/manual', [DetectionTimeController::class, 'storeManual'])->name('detection_time.store_manual');
// 🆕 AGREGAR ESTA LÍNEA:
Route::put('/detection-time/manual/{record}', [DetectionTimeController::class, 'updateManual'])->name('detection_time.update_manual');
// ═══════════════════════════════════════════════════════════════════════════
// 📱 TEST TELEGRAM
// ═══════════════════════════════════════════════════════════════════════════

Route::get('/test-telegram', function () {

    try {
        $telegram = resolve(\App\Services\TelegramService::class);

        $success = $telegram->sendMessage(
            "✅ <b>AgroLixiSync:</b> Conexión exitosa"
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'OK' : 'Error',
            'config' => [
                'token_present' => !empty(env('TELEGRAM_BOT_TOKEN')),
                'chat_id' => env('TELEGRAM_CHAT_ID')
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }

})->middleware('auth');

// ═══════════════════════════════════════════════════════════════════════════
// 🔁 ROOT
// ═══════════════════════════════════════════════════════════════════════════

Route::redirect('/', '/login');