<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-admin {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina todos los usuarios y crea un nuevo admin con Bcrypt correcto';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando limpieza de usuarios...');

        // Desactivar foreign keys
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Eliminar todos los usuarios
        User::truncate();
        $this->info('✅ Todos los usuarios eliminados');

        // Reactivar foreign keys
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Crear nuevo admin
        $password = $this->option('password') ?? 'Admin@2025';
        
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@agrolixi.com',
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info('✅ Admin creado correctamente');
        $this->line('');
        $this->info('📋 Datos de acceso:');
        $this->line("   Email: <fg=green>{$admin->email}</>");
        $this->line("   Contraseña: <fg=green>{$password}</>");
        $this->line('');
        $this->warn('⚠️  Guarda estas credenciales en un lugar seguro');

        return Command::SUCCESS;
    }
}
