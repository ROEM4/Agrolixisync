<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Lecturas ===\n";
echo "Total: " . App\Models\Lectura::count() . "\n\n";

echo "=== Sensores ===\n";
$sensores = App\Models\Sensor::all();
foreach ($sensores as $s) {
    echo "Sensor #{$s->id} | ubicacion_id={$s->ubicacion_id} | profundidad={$s->profundidad}cm | codigo={$s->codigo}\n";
}

echo "\n=== Ubicaciones ===\n";
$ubis = App\Models\Ubicacion::all();
foreach ($ubis as $u) {
    echo "Ubicacion #{$u->id} | planta_id={$u->planta_id} | nombre={$u->nombre} | dispositivo={$u->codigo_dispositivo}\n";
}

echo "\n=== Lecturas por sensor (últimas 5 por sensor) ===\n";
foreach ($sensores as $s) {
    $count = App\Models\Lectura::where('sensor_id', $s->id)->count();
    echo "Sensor #{$s->id} ({$s->codigo}, {$s->profundidad}cm): {$count} lecturas\n";
}
