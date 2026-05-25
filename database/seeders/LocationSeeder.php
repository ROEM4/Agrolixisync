<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Lote;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener los lotes existentes
        $lotes = Lote::all();

        if ($lotes->isEmpty()) {
            $this->command->info('No hay lotes. Ejecuta primero: php artisan db:seed --class=CreateAdminUserSeeder');
            return;
        }

        foreach ($lotes as $lote) {
            // Crear 2-3 ubicaciones por lote
            $locations = [
                [
                    'name' => 'Zona A - Sector Centro',
                    'description' => 'Sector central del lote, óptimo para monitoreo',
                    'latitude' => -33.8688 + (rand(-100, 100) / 10000),
                    'longitude' => -56.1645 + (rand(-100, 100) / 10000),
                ],
                [
                    'name' => 'Zona B - Sector Norte',
                    'description' => 'Sector norte, terreno elevado',
                    'latitude' => -33.8600 + (rand(-100, 100) / 10000),
                    'longitude' => -56.1600 + (rand(-100, 100) / 10000),
                ],
                [
                    'name' => 'Zona C - Sector Sur',
                    'description' => 'Sector sur, zona baja con mayor humedad',
                    'latitude' => -33.8700 + (rand(-100, 100) / 10000),
                    'longitude' => -56.1700 + (rand(-100, 100) / 10000),
                ],
            ];

            foreach ($locations as $location) {
                Location::firstOrCreate(
                    [
                        'lote_id' => $lote->id,
                        'name' => $location['name'],
                    ],
                    $location
                );
            }
        }

        $this->command->info('Ubicaciones creadas exitosamente.');
    }
}
