<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateDefaultUsersSeeder extends Seeder
{
    /**
     * Crear usuarios por defecto con contraseñas correctamente hasheadas
     */
    public function run(): void
    {
        // Usuario Administrador
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@agrolixisync.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        // Usuario de Prueba
        User::create([
            'name' => 'Usuario Demo',
            'email' => 'demo@agrolixisync.com',
            'password' => Hash::make('demo123'),
            'role' => 'user',
        ]);

        // Usuario Test
        User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'user',
        ]);

        $this->command->info('✅ Usuarios creados exitosamente:');
        $this->command->info('   📧 admin@agrolixisync.com / admin123');
        $this->command->info('   📧 demo@agrolixisync.com / demo123');
        $this->command->info('   📧 test@test.com / 12345678');
    }
}
