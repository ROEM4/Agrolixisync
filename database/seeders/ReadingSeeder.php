<?php

namespace Database\Seeders;

use App\Models\Reading;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReadingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Genera datos de prueba para los últimos 30 días
     */
    public function run(): void
    {
        $sensors = Sensor::all();

        if ($sensors->isEmpty()) {
            $this->command->info('No hay sensores. Ejecuta primero: php artisan db:seed --class=SensorSeeder');
            return;
        }

        $this->command->info('Generando lecturas de prueba...');

        $startDate = now()->subDays(30);
        $readingCount = 0;

        foreach ($sensors as $sensor) {
            // Generar lecturas cada 15 minutos durante 30 días
            $currentDate = $startDate->copy();

            while ($currentDate->lessThan(now())) {
                // Valores base según profundidad
                $isSurface = $sensor->depth == 0;

                // Temperatura: varía entre 15-35°C
                $baseTemp = rand(150, 350) / 10;
                $temperature = $baseTemp + (rand(-20, 20) / 10);

                // Humedad: varía entre 30-90%
                $baseHumidity = rand(300, 900) / 10;
                $humidity = $baseHumidity + (rand(-50, 50) / 10);

                // Conductividad: varía más en profundidad (indicador de lixiviación)
                if ($isSurface) {
                    $baseConductivity = rand(150, 350);
                } else {
                    // Sensor profundo: puede tener mayor conductividad
                    $baseConductivity = rand(200, 500);
                }
                $conductivity = $baseConductivity + (rand(-50, 50));

                // Humedad del suelo: 20-70%
                $basesoilMoisture = rand(200, 700) / 10;
                $soilMoisture = $basesoilMoisture + (rand(-30, 30) / 10);

                Reading::create([
                    'sensor_id' => $sensor->id,
                    'temperature' => $temperature,
                    'humidity' => $humidity,
                    'conductivity' => $conductivity,
                    'soil_moisture' => $soilMoisture,
                    'recorded_at' => $currentDate,
                ]);

                $readingCount++;
                $currentDate->addMinutes(15);
            }
        }

        $this->command->info("Se crearon {$readingCount} lecturas de prueba.");
    }
}
