<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder crea un usuario admin con credenciales funcionales.
     * Email: admin@agrolixi.com
     * Password: Admin@2025
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@agrolixi.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin@2025'),  // ✅ Bcrypt hasheada
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}