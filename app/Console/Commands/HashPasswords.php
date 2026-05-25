<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class HashPasswords extends Command
{
    protected $signature = 'app:hash-passwords';
    protected $description = 'Hashear contraseñas en texto plano';

    public function handle()
    {
        $users = DB::table('users')->get();
        $updated = 0;

        foreach ($users as $user) {
            if (!str_starts_with($user->password, '$2y$')) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => Hash::make($user->password)]);
                $updated++;
            }
        }

        $this->info("Se hashearon {$updated} contraseñas.");
    }
}
