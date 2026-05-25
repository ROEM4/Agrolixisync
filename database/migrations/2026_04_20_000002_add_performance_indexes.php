<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de rendimiento para escala IoT.
 *
 * Con lecturas cada 5 min por sensor:
 *   - 2 sensores × 288 lecturas/día = 576 filas/día
 *   - 1 año = ~210.000 filas en readings
 *
 * Sin índices, las queries del dashboard (getHistory, getLatest)
 * hacen full table scan en cada polling de 3s → colapso en semanas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // readings: el índice más crítico del sistema
        // Cubre: WHERE sensor_id = ? ORDER BY recorded_at DESC
        // y:     WHERE sensor_id = ? AND recorded_at = ?  (deduplicación)
        Schema::table('readings', function (Blueprint $table) {
            if (!$this->indexExists('readings', 'idx_readings_sensor_recorded')) {
                $table->index(['sensor_id', 'recorded_at'], 'idx_readings_sensor_recorded');
            }
        });

        // analysis: para getLatestAnalysis y TAR
        Schema::table('analysis', function (Blueprint $table) {
            if (!$this->indexExists('analysis', 'idx_analysis_location_analyzed')) {
                $table->index(['location_id', 'analyzed_at'], 'idx_analysis_location_analyzed');
            }
            if (!$this->indexExists('analysis', 'idx_analysis_lixiviation')) {
                $table->index(['location_id', 'lixiviation_detected', 'analyzed_at'], 'idx_analysis_lixiviation');
            }
        });

        // sensors: para auto-provisioning (busca por location_id + depth)
        Schema::table('sensors', function (Blueprint $table) {
            if (!$this->indexExists('sensors', 'idx_sensors_location_depth')) {
                $table->index(['location_id', 'depth'], 'idx_sensors_location_depth');
            }
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropIndex('idx_readings_sensor_recorded');
        });
        Schema::table('analysis', function (Blueprint $table) {
            $table->dropIndex('idx_analysis_location_analyzed');
            $table->dropIndex('idx_analysis_lixiviation');
        });
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropIndex('idx_sensors_location_depth');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(\DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
