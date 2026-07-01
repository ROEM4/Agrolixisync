<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$l1 = App\Models\Lectura::create(['sensor_id' => 31, 'conductividad' => 1.0, 'fecha_registro' => now()]);
$l2 = App\Models\Lectura::create(['sensor_id' => 32, 'conductividad' => 1.5, 'fecha_registro' => now()]);

$sensor1 = App\Models\Sensor::find(31);
$sensor2 = App\Models\Sensor::find(32);

echo "Lecturas insertadas. Analizando...\n";
resolve(App\Modules\AnalyticsEngine\LixiviationService::class)->analyze($sensor1, $sensor2);
echo "Finalizado.\n";
