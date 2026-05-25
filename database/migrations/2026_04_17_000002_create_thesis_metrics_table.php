<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Nueva tabla: thesis_metrics
     * 
     * Almacena valores calculados de:
     * - TAR (Tiempo de Alerta de Riesgo)
     * - PDS (Precisión del Diagnóstico del Sistema)
     * - NCES (Nivel de Conductividad Eléctrica en Suelo)
     */
    public function up(): void
    {
        Schema::create('thesis_metrics', function (Blueprint $table) {
            $table->id();
            
            // Relación con ubicación
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            
            // ═══════════════════════════════════════════════════════
            // TAR - TIEMPO DE ALERTA DE RIESGO (minutos)
            // ═══════════════════════════════════════════════════════
            $table->decimal('tar_minutes', 8, 2)->nullable()
                  ->comment('TAR: Promedio(Hora Alerta - Hora Evento) en minutos');
            $table->integer('tar_sample_count')->default(0)
                  ->comment('Número de eventos usados para calcular TAR');
            $table->timestamp('tar_calculated_at')->nullable()
                  ->comment('Cuándo se calculó TAR');
            
            // ═══════════════════════════════════════════════════════
            // PDS - PRECISIÓN DEL DIAGNÓSTICO DEL SISTEMA (%)
            // ═══════════════════════════════════════════════════════
            $table->decimal('pds_percentage', 5, 2)->nullable()
                  ->comment('PDS: (Coincidencias / Total Pruebas) * 100');
            $table->integer('pds_total_tests')->default(0)
                  ->comment('Total de pruebas realizadas');
            $table->integer('pds_correct_detections')->default(0)
                  ->comment('Número de detecciones correctas (coincidencias)');
            $table->integer('pds_false_positives')->default(0)
                  ->comment('Falsos positivos del sistema');
            $table->integer('pds_false_negatives')->default(0)
                  ->comment('Falsos negativos del sistema');
            $table->timestamp('pds_calculated_at')->nullable()
                  ->comment('Cuándo se calculó PDS');
            
            // ═══════════════════════════════════════════════════════
            // NCES - NIVEL DE CONDUCTIVIDAD ELÉCTRICA EN SUELO
            // ═══════════════════════════════════════════════════════
            $table->decimal('nces_control_avg', 8, 2)->nullable()
                  ->comment('NCES: Promedio CE en lote control');
            $table->decimal('nces_experimental_avg', 8, 2)->nullable()
                  ->comment('NCES: Promedio CE en lote experimental');
            $table->decimal('nces_difference', 8, 2)->nullable()
                  ->comment('NCES: Diferencia (Control - Experimental)');
            $table->integer('nces_control_samples')->default(0)
                  ->comment('Muestras en grupo control');
            $table->integer('nces_experimental_samples')->default(0)
                  ->comment('Muestras en grupo experimental');
            $table->timestamp('nces_calculated_at')->nullable()
                  ->comment('Cuándo se calculó NCES');
            
            // ═══════════════════════════════════════════════════════
            // PERÍODO DE CÁLCULO
            // ═══════════════════════════════════════════════════════
            $table->date('period_start_date')->comment('Inicio del período analizado');
            $table->date('period_end_date')->comment('Fin del período analizado');
            
            // ═══════════════════════════════════════════════════════
            // METADATOS
            // ═══════════════════════════════════════════════════════
            $table->text('notes')->nullable()->comment('Notas sobre el cálculo');
            $table->string('calculated_by')->default('system')->comment('Sistema o usuario que calculó');
            $table->boolean('is_verified')->default(false)->comment('¿Fue verificado manualmente?');
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('location_id');
            $table->index('period_start_date');
            $table->index('period_end_date');
            $table->index('tar_calculated_at');
            $table->index('pds_calculated_at');
            $table->index('nces_calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_metrics');
    }
};
