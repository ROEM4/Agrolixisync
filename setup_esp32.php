<?php

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * SCRIPT DE CONFIGURACIÓN Y VERIFICACIÓN - ESP32-LOTE-01
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Este script:
 * 1. Verifica la base de datos
 * 2. Registra el dispositivo ESP32-LOTE-01
 * 3. Valida la configuración
 * 4. Muestra instrucciones de uso
 * 
 * USO: php setup_esp32.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Lote;
use App\Models\Location;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  CONFIGURACIÓN ESP32-LOTE-01 - AgroLixiSync\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "\n";

// PASO 1: Verificar conexión a base de datos
echo "🔍 PASO 1: Verificando conexión a base de datos...\n";
try {
    DB::connection()->getPdo();
    echo "   ✅ Conexión exitosa\n\n";
} catch (\Exception $e) {
    echo "   ❌ Error de conexión: " . $e->getMessage() . "\n";
    echo "   Verifica tu archivo .env\n\n";
    exit(1);
}

// PASO 2: Verificar tipos de sensores
echo "🔍 PASO 2: Verificando tipos de sensores...\n";
$sensorType = SensorType::where('name', 'Sensor Multi-parámetro')->first();

if (!$sensorType) {
    echo "   ⚠️  No se encontró el tipo de sensor. Ejecutando seeder...\n";
    Artisan::call('db:seed', ['--class' => 'SensorTypeSeeder']);
    $sensorType = SensorType::where('name', 'Sensor Multi-parámetro')->first();
}

if ($sensorType) {
    echo "   ✅ Tipo de sensor encontrado (ID: {$sensorType->id})\n\n";
} else {
    echo "   ❌ No se pudo crear el tipo de sensor\n\n";
    exit(1);
}

// PASO 3: Crear/Verificar Lote
echo "🔍 PASO 3: Configurando lote...\n";
$lote = Lote::firstOrCreate(
    ['name' => 'LOTE-01'],
    [
        'crop_type' => 'palta',
        'description' => 'Lote de prueba para ESP32-LOTE-01',
        'user_id' => 1,
    ]
);
echo "   ✅ Lote: {$lote->name} (ID: {$lote->id})\n\n";

// PASO 4: Crear/Verificar Location
echo "🔍 PASO 4: Configurando ubicación...\n";
$location = Location::firstOrCreate(
    ['name' => 'ESP32-LOTE-01'],
    [
        'lote_id' => $lote->id,
        'description' => 'Ubicación para dispositivo ESP32-LOTE-01',
        'latitude' => -25.2637,
        'longitude' => -57.5759,
        'is_active' => true,
    ]
);
echo "   ✅ Location: {$location->name} (ID: {$location->id})\n\n";

// PASO 5: Crear/Verificar Sensores
echo "🔍 PASO 5: Configurando sensores...\n";

// Sensor Superficial (20cm)
$sensorSup = Sensor::firstOrCreate(
    [
        'code' => 'ESP32-LOTE-01-SUP',
        'location_id' => $location->id,
    ],
    [
        'name' => 'ESP32-LOTE-01 - Superficial (20cm)',
        'sensor_type_id' => $sensorType->id,
        'depth' => 20,
        'is_active' => true,
        'status' => 'active',
        'notes' => 'Sensor superficial a 20cm de profundidad',
    ]
);
echo "   ✅ Sensor Superficial: {$sensorSup->code} (ID: {$sensorSup->id}, Depth: 20cm)\n";

// Sensor Profundo (60cm)
$sensorProf = Sensor::firstOrCreate(
    [
        'code' => 'ESP32-LOTE-01-PROF',
        'location_id' => $location->id,
    ],
    [
        'name' => 'ESP32-LOTE-01 - Profundo (60cm)',
        'sensor_type_id' => $sensorType->id,
        'depth' => 60,
        'is_active' => true,
        'status' => 'active',
        'notes' => 'Sensor profundo a 60cm de profundidad',
    ]
);
echo "   ✅ Sensor Profundo: {$sensorProf->code} (ID: {$sensorProf->id}, Depth: 60cm)\n\n";

// PASO 6: Verificar lecturas existentes
echo "🔍 PASO 6: Verificando lecturas existentes...\n";
$readingsCount = DB::table('readings')
    ->where('sensor_id', $sensorSup->id)
    ->orWhere('sensor_id', $sensorProf->id)
    ->count();

if ($readingsCount > 0) {
    echo "   ✅ Se encontraron {$readingsCount} lecturas existentes\n\n";
} else {
    echo "   ⚠️  No hay lecturas aún. Esperando datos del ESP32...\n\n";
}

// RESUMEN FINAL
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "  ✅ CONFIGURACIÓN COMPLETADA EXITOSAMENTE\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "\n";
echo "📋 RESUMEN DE CONFIGURACIÓN:\n";
echo "   • Lote ID: {$lote->id} ({$lote->name})\n";
echo "   • Location ID: {$location->id} ({$location->name})\n";
echo "   • Sensor Superficial ID: {$sensorSup->id} (20cm)\n";
echo "   • Sensor Profundo ID: {$sensorProf->id} (60cm)\n";
echo "   • Lecturas existentes: {$readingsCount}\n";
echo "\n";
echo "🚀 PRÓXIMOS PASOS:\n";
echo "\n";
echo "1. FIRMWARE ESP32:\n";
echo "   • Abre Arduino IDE\n";
echo "   • Carga: docs/AGROlixisync_Firmware_v2_Industrial.ino\n";
echo "   • Verifica que DEVICE_CODE = \"ESP32-LOTE-01\"\n";
echo "   • Configura WiFi: SSID y PASSWORD\n";
echo "   • Configura API_URL: http://192.168.1.51:8000/api/sensor/data\n";
echo "   • Sube el código al ESP32\n";
echo "\n";
echo "2. DASHBOARD:\n";
echo "   • Abre: http://localhost:8000/dashboard/realtime\n";
echo "   • Selecciona: \"LOTE-01 — ESP32-LOTE-01\"\n";
echo "   • Los datos aparecerán automáticamente cada 5 minutos\n";
echo "\n";
echo "3. VERIFICAR COMUNICACIÓN:\n";
echo "   • Monitor Serial Arduino IDE (115200 baud)\n";
echo "   • Logs Laravel: tail -f storage/logs/laravel.log\n";
echo "   • Endpoint API: http://localhost:8000/api/readings/latest?location_id={$location->id}\n";
echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "\n";
