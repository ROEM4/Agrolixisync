<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Database\Seeder;

class SensorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = Location::all();

        if ($locations->isEmpty()) {
            $this->command->info('No hay ubicaciones. Ejecuta primero: php artisan db:seed --class=LocationSeeder');
            return;
        }

        $multiParamType = SensorType::where('name', 'Sensor Multi-parámetro')->first();

        if (!$multiParamType) {
            $this->command->error('Ejecuta primero: php artisan db:seed --class=SensorTypeSeeder');
            return;
        }

        $counter = 1;

        foreach ($locations as $location) {
            // Sensor superficial (profundidad = 0)
            Sensor::firstOrCreate(
                [
                    'code' => 'ESP32_LOC' . $location->id . '_SUP',
                ],
                [
                    'name' => $location->name . ' - Superficial',
                    'sensor_type_id' => $multiParamType->id,
                    'location_id' => $location->id,
                    'depth' => 0, // Superficie
                    'is_active' => true,
                    'status' => 'active',
                    'notes' => 'Sensor de monitoreo superficial',
                ]
            );

            // Sensor profundo (profundidad = 30 cm)
            Sensor::firstOrCreate(
                [
                    'code' => 'ESP32_LOC' . $location->id . '_PROF',
                ],
                [
                    'name' => $location->name . ' - Profundo',
                    'sensor_type_id' => $multiParamType->id,
                    'location_id' => $location->id,
                    'depth' => 30, // 30 cm de profundidad
                    'is_active' => true,
                    'status' => 'active',
                    'notes' => 'Sensor de monitoreo a 30 cm de profundidad',
                ]
            );

            $counter += 2;
        }

        $this->command->info("Se crearon sensores para {$locations->count()} ubicaciones.");
    }
}
