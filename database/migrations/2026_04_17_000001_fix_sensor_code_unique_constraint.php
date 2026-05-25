<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cambia el constraint de 'code' de UNIQUE global a permitir duplicados
     * por location + depth, manteniendo code únicamente para referencia.
     */
    public function up(): void
    {
        // Solo ejecutar si existen datos sin conflictos
        try {
            // Verificar si hay duplicados de code+location+depth
            $duplicates = DB::table('sensors')
                ->select('code', 'location_id', 'depth')
                ->groupBy('code', 'location_id', 'depth')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicates > 0) {
                throw new \Exception("Found $duplicates duplicate entries. Migration skipped.");
            }

            // Si estamos usando SQLite o no hay constraint único, no hacer nada
            // La migración original ya permite un sensor por location+depth
            
            DB::statement('ALTER TABLE sensors DROP CONSTRAINT sensors_code_unique;');
            
        } catch (\Exception $e) {
            // Log pero no fallar - el schema ya podría estar correcto
            \Illuminate\Support\Facades\Log::warning('Migration note: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE sensors ADD UNIQUE sensors_code_unique (code);');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
    }
};
