<?php

require_once 'bootstrap/app.php';
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$alertCount = \App\Models\Alert::whereNotNull('tiempo_alerta')->whereNotNull('tiempo_riesgo')->count();
$firstAlert = \App\Models\Alert::whereNotNull('tiempo_alerta')->orderBy('tiempo_alerta')->first();
$lastAlert = \App\Models\Alert::whereNotNull('tiempo_alerta')->orderByDesc('tiempo_alerta')->first();

echo "=== VERIFICACIÓN DE ALERTAS ===\n";
echo "Total de alertas válidas: $alertCount\n";

if ($firstAlert) {
    echo "\nPrimer registro:\n";
    echo "  - ID: {$firstAlert->id}\n";
    echo "  - Ubicación: {$firstAlert->location->name}\n";
    echo "  - Ti (tiempo_alerta): {$firstAlert->tiempo_alerta}\n";
    echo "  - Tf (tiempo_riesgo): {$firstAlert->tiempo_riesgo}\n";
    echo "  - TAR: {$firstAlert->tar} segundos\n";
}

if ($lastAlert) {
    echo "\nÚltimo registro:\n";
    echo "  - ID: {$lastAlert->id}\n";
    echo "  - Ubicación: {$lastAlert->location->name}\n";
    echo "  - Ti (tiempo_alerta): {$lastAlert->tiempo_alerta}\n";
    echo "  - Tf (tiempo_riesgo): {$lastAlert->tiempo_riesgo}\n";
    echo "  - TAR: {$lastAlert->tar} segundos\n";
}

// Agrupar por día para verificar el cálculo
$groupedByDate = \App\Models\Alert::whereNotNull('tiempo_alerta')
    ->whereNotNull('tiempo_riesgo')
    ->get()
    ->groupBy(function ($alert) {
        return $alert->tiempo_alerta->format('Y-m-d');
    });

echo "\n=== DATOS AGRUPADOS POR DÍA ===\n";
foreach ($groupedByDate as $date => $alerts) {
    $sumaSegundos = $alerts->sum('tar');
    $cantidad = count($alerts);
    $promedio = $cantidad > 0 ? $sumaSegundos / $cantidad : 0;
    echo "Fecha $date: $cantidad alertas, Promedio: " . round($promedio, 2) . " segundos\n";
}
