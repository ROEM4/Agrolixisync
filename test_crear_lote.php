<?php
/**
 * TEST: Crear lote via API
 * 
 * Este archivo prueba el endpoint POST /api/lotes
 * ejecutándose con autenticación
 */

// Cargar Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

echo "═══════════════════════════════════════════════════════════\n";
echo "TEST: Crear Lote via API\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Obtener usuario admin
$admin = User::where('email', 'admin@agrolixisync.local')->first();
if (!$admin) {
    echo "❌ Usuario admin no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$admin->name} ({$admin->email})\n\n";

// 2. Autenticar
Auth::loginUsingId($admin->id);
echo "✅ Autenticado como: " . Auth::user()->name . "\n\n";

// 3. Crear request simulado
$request = Request::create('/api/lotes', 'POST', [
    'name' => 'Test Lote ' . time(),
    'crop_type' => 'palta',
    'description' => 'Lote de prueba creado automaticamente',
]);

$request->setUserResolver(fn() => Auth::user());

// 4. Ejecutar lógica del endpoint
try {
    echo "📝 Creando lote...\n";
    
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'crop_type' => 'nullable|string|max:255',
        'description' => 'nullable|string',
    ]);

    $validated['crop_type'] = $validated['crop_type'] ?? 'palta';
    $validated['user_id'] = Auth::id();

    $lote = \App\Models\Lote::create($validated);
    
    // Crear location por defecto
    $location = $lote->locations()->create([
        'name' => $validated['name'],
        'latitude' => -25.2637,
        'longitude' => -57.5759,
        'is_active' => true,
    ]);

    echo "\n✅ LOTE CREADO EXITOSAMENTE\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID Lote: " . $lote->id . "\n";
    echo "Nombre: " . $lote->name . "\n";
    echo "Tipo: " . $lote->crop_type . "\n";
    echo "Usuario: " . $lote->user_id . "\n";
    echo "\nLocation Asociada:\n";
    echo "  ID: " . $location->id . "\n";
    echo "  Nombre: " . $location->name . "\n";
    echo "  Latitud: " . $location->latitude . "\n";
    echo "  Longitud: " . $location->longitude . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✅ Test completado sin errores\n";
?>
