<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Orden importante: Usuarios y configuración primero
        $this->call([
            // Usuarios y autenticación
            CreateAdminUserSeeder::class,
            
            // Configuraciones del sistema
            SettingSeeder::class,
            
            // Tipos de sensores (datos maestros)
            SensorTypeSeeder::class,
            
            // Ubicaciones (asociadas a lotes)
            LocationSeeder::class,
            
            // Sensores (asociados a ubicaciones)
            SensorSeeder::class,
            
            // Lecturas (datos históricos)
            ReadingSeeder::class,
            
            // Alertas (análisis de tiempo de detección)
            AlertSeeder::class,
        ]);
    }
}
