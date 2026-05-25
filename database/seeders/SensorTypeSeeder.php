<?php

namespace Database\Seeders;

use App\Models\SensorType;
use Illuminate\Database\Seeder;

class SensorTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Sensor de Conductividad Eléctrica',
                'description' => 'Mide la conductividad del suelo en µS/cm',
                'unit' => 'µS/cm',
                'model' => 'EC-4P',
            ],
            [
                'name' => 'Sensor de Temperatura',
                'description' => 'Mide la temperatura del suelo',
                'unit' => '°C',
                'model' => 'DHT22',
            ],
            [
                'name' => 'Sensor de Humedad',
                'description' => 'Mide la humedad relativa del aire',
                'unit' => '%',
                'model' => 'DHT22',
            ],
            [
                'name' => 'Sensor de Humedad del Suelo',
                'description' => 'Mide el contenido de agua en el suelo',
                'unit' => '%',
                'model' => 'Capacitivo',
            ],
            [
                'name' => 'Sensor Multi-parámetro',
                'description' => 'Combina mediciones de temperatura, humedad y conductividad',
                'unit' => 'Múltiple',
                'model' => 'ESP32-Multi',
            ],
        ];

        foreach ($types as $type) {
            SensorType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        $this->command->info('Tipos de sensores creados/verificados.');
    }
}
