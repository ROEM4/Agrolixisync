<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\DetectionTimeRecord;
use App\Models\Alert;

echo "=== ANTES de simular saveDetectionTimeRecords ===\n";
echo "DetectionTimeRecords: " . DetectionTimeRecord::count() . "\n";
echo "Alerts totales: " . Alert::whereNotNull('tiempo_alerta')->whereNotNull('tiempo_riesgo')->count() . "\n\n";

echo "=== Detalle registros actuales ===\n";
$records = DetectionTimeRecord::with('location')->orderBy('location_id')->orderBy('fecha')->get();
foreach ($records as $r) {
    $group = $r->location ? $r->location->experimental_group : '?';
    echo "ID:{$r->id} | {$r->fecha->format('d/m/Y')} | Loc:{$r->location_id}({$group}) | Sub:{$r->subparcela} | Avg:{$r->tiempo_promedio_segundos}s | Ev:{$r->cantidad_eventos}\n";
}

echo "\n=== Alerts por fecha y location ===\n";
$alerts = Alert::whereNotNull('tiempo_alerta')->whereNotNull('tiempo_riesgo')->orderBy('location_id')->orderBy('tiempo_alerta')->get();
$grouped = $alerts->groupBy(fn($a) => $a->tiempo_alerta->format('Y-m-d') . '|' . $a->location_id);
echo "Grupos únicos (fecha|location): " . $grouped->count() . "\n";
foreach ($grouped as $key => $group) {
    $first = $group->first();
    echo "  {$key} => {$group->count()} alertas, subparcela:{$first->subparcela}\n";
}
