<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Reading;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\Lote;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestSensorDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear tipo de sensor si no existe
        $sensorType = SensorType::firstOrCreate(
            ['name' => 'Humedad-Temperatura-CE'],
            ['description' => 'Sensor multiparámetro para monitoreo de suelo']
        );

        // Obtener o crear lote
        $lote = Lote::firstOrCreate(
            ['name' => 'Lote Principal'],
            ['user_id' => 1, 'description' => 'Lote de prueba para monitoreo']
        );

        // Crear o obtener ubicación
        $location = Location::firstOrCreate(
            ['lote_id' => $lote->id, 'name' => 'Sector A'],
            [
                'description' => 'Sector principal para monitoreo comparativo',
                'latitude' => -12.1234,
                'longitude' => -76.5678,
                'is_active' => true,
            ]
        );

        // Crear sensor superficial (profundidad = 0)
        $superficialSensor = Sensor::updateOrCreate(
            ['code' => 'ESP32_SUPERFICIAL', 'location_id' => $location->id],
            [
                'name' => 'Sensor Superficial - Sector A',
                'sensor_type_id' => $sensorType->id,
                'depth' => 0,
                'is_active' => true,
                'status' => 'active',
                'notes' => 'Sensor a nivel de suelo',
            ]
        );

        // Crear sensor profundo (profundidad = 20 cm)
        $deepSensor = Sensor::updateOrCreate(
            ['code' => 'ESP32_PROFUNDO', 'location_id' => $location->id],
            [
                'name' => 'Sensor Profundo - Sector A',
                'sensor_type_id' => $sensorType->id,
                'depth' => 20,
                'is_active' => true,
                'status' => 'active',
                'notes' => 'Sensor a 20 cm de profundidad',
            ]
        );

        // Generar datos de prueba (últimas 48 horas con intervalo de 30 minutos)
        $now = Carbon::now();
        $readings_count = 0;
        
        for ($hoursBack = 48; $hoursBack >= 0; $hoursBack -= 0.5) {
            $timestamp = $now->copy()->subHours($hoursBack);

            // Simular datos realistas
            $baseHumidity = 60 + rand(-20, 20);
            $baseTemperature = 22 + rand(-5, 5);
            $baseConductivity = 180 + rand(-50, 100);

            // Lectura superficial
            Reading::create([
                'sensor_id' => $superficialSensor->id,
                'conductivity' => $baseConductivity,
                'humidity' => min(100, max(0, $baseHumidity + rand(-10, 10))),
                'temperature' => $baseTemperature + rand(-2, 2),
                'soil_moisture' => $baseHumidity,
                'recorded_at' => $timestamp,
            ]);

            // Lectura profunda (mayor conductividad simulando lixiviación)
            $deepConductivity = $baseConductivity + 50 + rand(-30, 50); // 50 µS/cm promedio más alto
            
            Reading::create([
                'sensor_id' => $deepSensor->id,
                'conductivity' => $deepConductivity,
                'humidity' => min(100, max(0, $baseHumidity + rand(-5, 5))),
                'temperature' => $baseTemperature - 1 + rand(-1, 1),
                'soil_moisture' => $baseHumidity,
                'recorded_at' => $timestamp,
            ]);

            $readings_count++;
        }

        // Actualizar last_reading_at de los sensores
        $superficialSensor->update(['last_reading_at' => now()]);
        $deepSensor->update(['last_reading_at' => now()]);

        $this->command->info("✅ Datos de prueba creados: $readings_count lecturas por sensor");
        $this->command->info("   - Ubicación: {$location->name}");
        $this->command->info("   - Sensor Superficial: {$superficialSensor->code}");
        $this->command->info("   - Sensor Profundo: {$deepSensor->code}");
    }
}
