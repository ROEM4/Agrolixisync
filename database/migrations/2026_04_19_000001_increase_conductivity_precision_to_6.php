<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * DECIMAL(10,6) permite guardar valores como 0.000700 sin pérdida.
     * El ESP32 envía CE en dS/m con hasta 6 decimales significativos.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE readings MODIFY conductivity DECIMAL(10,6) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_superficial DECIMAL(10,6) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_profundo DECIMAL(10,6) NULL');
        DB::statement('ALTER TABLE analysis MODIFY delta_conductivity DECIMAL(10,6) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE analysis MODIFY threshold_used DECIMAL(10,6) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE readings MODIFY conductivity DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_superficial DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_profundo DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE analysis MODIFY delta_conductivity DECIMAL(10,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE analysis MODIFY threshold_used DECIMAL(10,4) NOT NULL DEFAULT 0');
    }
};
