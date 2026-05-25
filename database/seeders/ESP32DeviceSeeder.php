<?php

namespace Database\Seeders;

use App\Models\Lote;
use App\Models\Location;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ESP32DeviceSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener el tipo de sensor multi-parámetro
        $sensorType = SensorType::where('name', 'Sensor Multi-parámetro')->first();

        if (!$sensorType) {
            $this->command->error('Ejecuta primero: php artisan db:seed --class=SensorTypeSeeder');
            return;
        }

        // Crear lote
        $lote = Lote::firstOrCreate(
            ['name' => 'LOTE-01'],
            ['crop_type' => 'palta', 'user_id' => 1]
        );

        // Crear location con nombre = DEVICE_CODE del ESP32
        $location = Location::firstOrCreate(
            ['name' => 'ESP32-LOTE-01'],
            ['lote_id' => $lote->id, 'latitude' => -25.2637, 'longitude' => -57.5759, 'is_active' => true]
        );

        // Sensor superficial depth=20
        Sensor::firstOrCreate(
            ['location_id' => $location->id, 'depth' => 20],
            ['code' => 'ESP32-LOTE-01-SUP', 'name' => 'Superficial 20cm', 'sensor_type_id' => $sensorType->id, 'is_active' => true, 'status' => 'active']
        );

        // Sensor profundo depth=60
        Sensor::firstOrCreate(
            ['location_id' => $location->id, 'depth' => 60],
            ['code' => 'ESP32-LOTE-01-PROF', 'name' => 'Profundo 60cm', 'sensor_type_id' => $sensorType->id, 'is_active' => true, 'status' => 'active']
        );

        $this->command->info('ESP32-LOTE-01 registrado. Location ID: ' . $location->id);
    }
}
