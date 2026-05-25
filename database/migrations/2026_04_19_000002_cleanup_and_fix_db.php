<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Eliminar tablas obsoletas ──────────────────────────────────────
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('ec_readings');

        // ── 2. Eliminar sensor_type_id de sensors (sensor_types ya no se usa) ─
        // Primero quitar FK, luego la columna
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropForeign(['sensor_type_id']);
            $table->dropColumn('sensor_type_id');
            // Agregar group_type directamente en sensors para NCES (más simple)
            $table->enum('group_type', ['CONTROL', 'EXPERIMENTAL'])
                  ->default('EXPERIMENTAL')
                  ->after('depth')
                  ->comment('Grupo para cálculo NCES');
        });

        Schema::dropIfExists('sensor_types');

        // ── 3. Corregir alerts: agregar columna severity que falta ────────────
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('severity')->default('BAJO')->after('type')
                  ->comment('BAJO, MEDIO, ALTO');
            $table->string('status')->default('OPEN')->after('severity')
                  ->comment('OPEN, RESOLVED');
        });

        // ── 4. Llenar event_detected_at y alert_generated_at en analysis ──────
        // Para registros existentes: event_detected_at = analyzed_at
        // alert_generated_at = analyzed_at (mismo momento, datos históricos)
        DB::statement('UPDATE analysis SET event_detected_at = analyzed_at WHERE event_detected_at IS NULL');
        DB::statement('UPDATE analysis SET alert_generated_at = analyzed_at WHERE alert_generated_at IS NULL');

        // ── 5. Ajustar precisión NCES en thesis_metrics a DECIMAL(10,6) ───────
        DB::statement('ALTER TABLE thesis_metrics MODIFY nces_control_avg DECIMAL(10,6) NULL');
        DB::statement('ALTER TABLE thesis_metrics MODIFY nces_experimental_avg DECIMAL(10,6) NULL');
        DB::statement('ALTER TABLE thesis_metrics MODIFY nces_difference DECIMAL(10,6) NULL');
        DB::statement('ALTER TABLE thesis_metrics MODIFY tar_minutes DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE thesis_metrics MODIFY pds_percentage DECIMAL(6,4) NULL');
    }

    public function down(): void
    {
        // No reversible (datos eliminados)
    }
};
