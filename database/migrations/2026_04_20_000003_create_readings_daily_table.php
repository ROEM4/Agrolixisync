<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * readings_daily — Tabla de agregación histórica (Historian module)
 *
 * Una fila por sensor por día. Se llena mediante HistorianService::aggregateDay()
 * que se ejecuta como cron diario o on-demand.
 *
 * Ventaja industrial:
 *   - El dashboard histórico (7/30/90 días) consulta esta tabla en lugar de
 *     escanear millones de filas en readings.
 *   - readings sigue siendo la fuente de verdad para tiempo real.
 *   - Esta tabla es derivada y regenerable en cualquier momento.
 *
 * Humedad y temperatura NO son tablas separadas: son columnas aquí,
 * agrupadas desde readings por el HistorianService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings_daily', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sensor_id')
                  ->constrained('sensors')
                  ->cascadeOnDelete();

            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->cascadeOnDelete();

            $table->date('day')->index();

            // Cantidad de lecturas que componen este agregado
            $table->unsignedSmallInteger('n')->default(0);

            // Conductividad eléctrica — DECIMAL(10,6) igual que readings
            $table->decimal('ce_avg',  10, 6)->nullable();
            $table->decimal('ce_min',  10, 6)->nullable();
            $table->decimal('ce_max',  10, 6)->nullable();

            // Humedad — módulo analítico dentro de la capa de lecturas
            $table->decimal('hum_avg', 5, 2)->nullable();
            $table->decimal('hum_min', 5, 2)->nullable();
            $table->decimal('hum_max', 5, 2)->nullable();

            // Temperatura
            $table->decimal('temp_avg', 5, 2)->nullable();
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_max', 5, 2)->nullable();

            $table->timestamps();

            // Clave única: un agregado por sensor por día
            $table->unique(['sensor_id', 'day'], 'uq_readings_daily_sensor_day');

            // Índice para queries del Historian por location + rango de fechas
            $table->index(['location_id', 'day'], 'idx_readings_daily_location_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings_daily');
    }
};
