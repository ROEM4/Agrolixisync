<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Location;
use App\Models\Alert;
use App\Models\DetectionTimeRecord;
use Carbon\Carbon;

// 15 fechas desde 19/04/2026, saltando 1 día entre cada una
$fechas = [];
$start = Carbon::parse('2026-04-19');
for ($i = 0; $i < 15; $i++) {
    $fechas[] = $start->copy()->addDays($i * 2);
}
echo "Fechas a usar:\n";
foreach ($fechas as $f) { echo "  " . $f->format('d/m/Y') . "\n"; }

$controlLoc = Location::where('experimental_group', 'control')->first();
$experimentalLoc = Location::where('experimental_group', 'experimental')->first();
echo "\nControl: ID=" . $controlLoc->id . ", Lote=" . $controlLoc->lote_id . "\n";
echo "Experimental: ID=" . $experimentalLoc->id . ", Lote=" . $experimentalLoc->lote_id . "\n\n";

// Limpiar detection_time_records en ese rango de fechas
$fechaStrings = array_map(fn($f) => $f->format('Y-m-d'), $fechas);
DetectionTimeRecord::whereIn('fecha', $fechaStrings)->delete();
// Limpiar alertas de seed anteriores en ese rango
Alert::where('description', 'LIKE', 'seed -%')->delete();

// Eventos estratégicos (algunos se repiten, no crecen acumulativos)
$eventosControl      = [3, 2, 4, 3, 2, 5, 3, 2, 4, 3, 5, 2, 3, 4, 3];
$eventosExperimental = [4, 5, 3, 6, 4, 3, 5, 4, 6, 3, 4, 5, 3, 4, 6];

// Tiempos promedio CONTROL (manual/tradicional): 60-180 s
$tiemposControl = [95, 120, 75, 110, 85, 160, 100, 70, 130, 90, 145, 65, 105, 115, 80];
// Tiempos promedio EXPERIMENTAL (IoT): más rápidos 15-70 s
$tiemposExp     = [35, 28, 52, 22, 45, 30, 58, 18, 40, 33, 55, 25, 48, 20, 42];

// ===== GRUPO CONTROL =====
echo "=== Insertando 15 registros GRUPO CONTROL ===\n";
foreach ($fechas as $idx => $fecha) {
    $num        = $idx + 1;
    $subparcela = 'S' . $num;
    $avgSeconds = $tiemposControl[$idx];
    $eventos    = $eventosControl[$idx];

    $hora = rand(7, 9);
    $min  = rand(5, 55);
    $sec  = rand(0, 59);
    $ti   = Carbon::parse($fecha->format('Y-m-d') . " " . $hora . ":" . $min . ":" . $sec);
    $tf   = $ti->copy()->addSeconds($avgSeconds); // Siempre Ti < Tf => diff positivo

    Alert::create([
        'location_id'   => $controlLoc->id,
        'lote_id'       => $controlLoc->lote_id,
        'type'          => 'lixiviacion',
        'severity'      => 'MEDIO',
        'level'         => 'medio',
        'status'        => 'RESOLVED',
        'is_resolved'   => true,
        'resolved_at'   => $tf,
        'description'   => 'seed - control ' . $subparcela,
        'tiempo_alerta' => $ti,
        'tiempo_riesgo' => $tf,
        'tar'           => $avgSeconds,
        'subparcela'    => $subparcela,
    ]);

    DetectionTimeRecord::create([
        'fecha'                    => $fecha,
        'location_id'              => $controlLoc->id,
        'lote_id'                  => $controlLoc->lote_id,
        'subparcela'               => $subparcela,
        'tiempo_promedio_segundos' => $avgSeconds,
        'cantidad_eventos'         => $eventos,
        'suma_tiempos_segundos'    => $avgSeconds * $eventos,
        'tipo_entrada'             => 'manual',
    ]);

    echo "  [D" . $num . "] " . $fecha->format('d/m/Y') . " | " . $subparcela . " | Ti:" . $ti->format('H:i:s') . " | Tf:" . $tf->format('H:i:s') . " | " . $avgSeconds . "s | " . $eventos . " ev\n";
}

// ===== GRUPO EXPERIMENTAL =====
echo "\n=== Insertando 15 registros GRUPO EXPERIMENTAL ===\n";
foreach ($fechas as $idx => $fecha) {
    $num        = $idx + 1;
    $subparcela = 'S' . $num;
    $avgSeconds = $tiemposExp[$idx];
    $eventos    = $eventosExperimental[$idx];

    $hora = rand(7, 9);
    $min  = rand(5, 55);
    $sec  = rand(0, 59);
    $ti   = Carbon::parse($fecha->format('Y-m-d') . " " . $hora . ":" . $min . ":" . $sec);
    $tf   = $ti->copy()->addSeconds($avgSeconds);

    Alert::create([
        'location_id'   => $experimentalLoc->id,
        'lote_id'       => $experimentalLoc->lote_id,
        'type'          => 'lixiviacion',
        'severity'      => 'BAJO',
        'level'         => 'bajo',
        'status'        => 'RESOLVED',
        'is_resolved'   => true,
        'resolved_at'   => $tf,
        'description'   => 'seed - experimental ' . $subparcela,
        'tiempo_alerta' => $ti,
        'tiempo_riesgo' => $tf,
        'tar'           => $avgSeconds,
        'subparcela'    => $subparcela,
    ]);

    DetectionTimeRecord::create([
        'fecha'                    => $fecha,
        'location_id'              => $experimentalLoc->id,
        'lote_id'                  => $experimentalLoc->lote_id,
        'subparcela'               => $subparcela,
        'tiempo_promedio_segundos' => $avgSeconds,
        'cantidad_eventos'         => $eventos,
        'suma_tiempos_segundos'    => $avgSeconds * $eventos,
        'tipo_entrada'             => 'automatico',
    ]);

    echo "  [D" . $num . "] " . $fecha->format('d/m/Y') . " | " . $subparcela . " | Ti:" . $ti->format('H:i:s') . " | Tf:" . $tf->format('H:i:s') . " | " . $avgSeconds . "s | " . $eventos . " ev\n";
}

echo "\n=== RESUMEN FINAL ===\n";
echo "Control records: " . DetectionTimeRecord::where('location_id', $controlLoc->id)->count() . "\n";
echo "Experimental records: " . DetectionTimeRecord::where('location_id', $experimentalLoc->id)->count() . "\n";
echo "Total: " . DetectionTimeRecord::count() . "\n";
echo "\nLISTO!\n";
