<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Extender tabla `analysis` para soportar métricas de tesis.
     * Agrega timestamps críticos para cálculo de TAR (Tiempo de Alerta de Riesgo).
     */
    public function up(): void
    {
        Schema::table('analysis', function (Blueprint $table) {
            // Timestamps para cálculo de TAR
            $table->timestamp('event_detected_at')->nullable()->comment('Momento exacto de detección del evento crítico');
            $table->timestamp('alert_generated_at')->nullable()->comment('Momento exacto de generación de alerta');
            
            // Campo para indicador de tipo de evento (para clasificación)
            $table->enum('event_type', ['LIXIVIATION', 'NUTRIENT_EXCESS', 'pH_ANOMALY', 'OTHER'])
                  ->default('LIXIVIATION')
                  ->comment('Tipo de evento detectado');
            
            // Validación manual del evento (para PDS)
            $table->boolean('is_validated')->default(false)->comment('¿Fue validado manualmente?');
            $table->timestamp('validated_at')->nullable()->comment('Cuándo fue validado');
            $table->string('validated_by')->nullable()->comment('Usuario que validó');
            
            // Confianza del análisis (0-100)
            $table->integer('confidence_level')->default(100)->comment('Nivel de confianza del análisis %');
            
            // Notas para análisis académico
            $table->text('academic_notes')->nullable()->comment('Notas para análisis académico');
            
            // Índices para performance
            $table->index('event_detected_at');
            $table->index('alert_generated_at');
            $table->index('is_validated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis', function (Blueprint $table) {
            $table->dropColumn([
                'event_detected_at',
                'alert_generated_at',
                'event_type',
                'is_validated',
                'validated_at',
                'validated_by',
                'confidence_level',
                'academic_notes',
            ]);
        });
    }
};
