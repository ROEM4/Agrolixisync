<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoteController;
use App\Http\Middleware\VerifiedAgricultor;

// Rutas PÚBLICAS (Sin autenticación para pruebas del IoT)
Route::get('/monitor', [DashboardController::class, 'realtime'])->name('monitor');

// Dashboard PÚBLICO (sin autenticación) - ACTUALIZADO A dashboard_realtime
Route::get('/dashboard', [DashboardController::class, 'realtime'])->name('dashboard');

// Dashboard Real-Time (alias para acceso directo)
Route::get('/realtime', [DashboardController::class, 'realtime'])->name('realtime');

// Login público
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', function () {
    Auth::logout();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// Rutas protegidas (admin + agricultores)
Route::middleware([VerifiedAgricultor::class])->group(function () {
    Route::get('/perfil', fn() => view('perfil.index'))->name('perfil.index');
    Route::put('/perfil', [DashboardController::class, 'updatePerfil']);
    Route::get('/exportar', [DashboardController::class, 'export'])->name('lecturas.export');
});

// Solo admin: Gestión de usuarios
Route::middleware([VerifiedAgricultor::class])->group(function () {
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
});

// Gestión de lotes (todos los usuarios autenticados)
Route::middleware([VerifiedAgricultor::class])->group(function () {
    Route::get('/lotes', [LoteController::class, 'index'])->name('lotes.index');
    Route::get('/lotes/crear', [LoteController::class, 'create'])->name('lotes.create');
    Route::post('/lotes', [LoteController::class, 'store'])->name('lotes.store');
});

// Alertas e Histórico
Route::get('/alertas', function () {
    $locations = \App\Models\Location::with('lote')->orderBy('name')->get();
    return view('dashboard.alertas', compact('locations'));
})->name('alertas');

Route::get('/historico', function () {
    $locations = \App\Models\Location::with('lote')->orderBy('name')->get();
    return view('dashboard.historico', compact('locations'));
})->name('historico');

Route::get('/analisis', [\App\Http\Controllers\AnalisisController::class, 'index'])->name('analisis');
Route::get('/analisis/export', [\App\Http\Controllers\AnalisisController::class, 'export'])->name('analisis.export');
Route::get('/lixiviacion', [\App\Http\Controllers\LixiviacionController::class, 'index'])->name('lixiviacion');
Route::get('/lixiviacion/export', [\App\Http\Controllers\LixiviacionController::class, 'export'])->name('lixiviacion.export');

// Tiempo de Detección
Route::get('/detection-time', [\App\Http\Controllers\DetectionTimeController::class, 'index'])->name('detection_time');
Route::get('/detection-time/export', [\App\Http\Controllers\DetectionTimeController::class, 'export'])->name('detection_time.export');
Route::post('/detection-time/manual', [\App\Http\Controllers\DetectionTimeController::class, 'storeManual'])->name('detection_time.store_manual');

// Ficha PF - registro y visualización
Route::middleware([VerifiedAgricultor::class])->group(function () {
    Route::get('/pf-ficha', [\App\Http\Controllers\PFRecordController::class, 'index'])->name('pf.ficha.index');
    Route::get('/pf-ficha/export', [\App\Http\Controllers\PFRecordController::class, 'export'])->name('pf.ficha.export');
    Route::post('/pf-ficha', [\App\Http\Controllers\PFRecordController::class, 'store'])->name('pf.ficha.store');
});

// Ruta de prueba para Telegram
Route::get('/test-telegram', function () {
    try {
        $telegram = resolve(\App\Services\TelegramService::class);
        $success = $telegram->sendMessage("✅ <b>AgroLixiSync:</b> Conexión exitosa. Tu sistema de alertas está listo.");
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Mensaje enviado correctamente' : 'Error al enviar mensaje. Revisa los logs.',
            'config' => [
                'token_present' => !empty(env('TELEGRAM_BOT_TOKEN')),
                'chat_id' => env('TELEGRAM_CHAT_ID')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
})->middleware('auth');

// Redirección raíz → login
Route::redirect('/', '/login');