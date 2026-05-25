<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Nueva tabla: system_tests
     * 
     * Registro de pruebas del sistema para validación de PDS.
     * Permite comparar detección automática vs evento real validado.
     */
    public function up(): void
    {
        Schema::create('system_tests', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('analysis_id')->nullable()->constrained('analysis')->onDelete('set null');
            
            // ═══════════════════════════════════════════════════════
            // TIPO DE PRUEBA
            // ═══════════════════════════════════════════════════════
            $table->enum('test_type', [
                'SYSTEM_DETECTION',      // Qué detectó el sistema
                'MANUAL_VALIDATION',     // Qué se validó manualmente
                'COMPARISON',            // Comparación entre ambos
            ])->comment('Tipo de prueba realizada');
            
            // ═══════════════════════════════════════════════════════
            // DETECCIÓN DEL SISTEMA
            // ═══════════════════════════════════════════════════════
            $table->boolean('system_detected_anomaly')->default(false)
                  ->comment('¿El sistema detectó una anomalía?');
            $table->string('system_detection_type')->nullable()
                  ->comment('Tipo de anomalía detectada: LIXIVIATION, etc');
            $table->timestamp('system_detection_time')->nullable()
                  ->comment('Momento de detección del sistema');
            $table->decimal('system_confidence', 5, 2)->default(100)
                  ->comment('Confianza del diagnóstico del sistema %');
            
            // ═══════════════════════════════════════════════════════
            // VALIDACIÓN MANUAL (GROUND TRUTH)
            // ═══════════════════════════════════════════════════════
            $table->boolean('actual_anomaly_existed')->default(false)
                  ->comment('¿La anomalía realmente existió? (validación manual)');
            $table->string('actual_anomaly_type')->nullable()
                  ->comment('Tipo real de anomalía: LIXIVIATION, etc');
            $table->timestamp('actual_anomaly_time')->nullable()
                  ->comment('Momento en que realmente ocurrió');
            $table->string('validated_by')->comment('Usuario/experimento que validó');
            $table->timestamp('validated_at')->useCurrent()->comment('Cuándo se validó');
            
            // ═══════════════════════════════════════════════════════
            // RESULTADOS DE COMPARACIÓN (PDS)
            // ═══════════════════════════════════════════════════════
            $table->enum('match_result', [
                'TRUE_POSITIVE',    // Sistema detectó, realidad confirmó ✓
                'TRUE_NEGATIVE',    // Sistema no detectó, realidad confirmó no existe ✓
                'FALSE_POSITIVE',   // Sistema detectó, pero realidad dice que no existe ✗
                'FALSE_NEGATIVE',   // Sistema no detectó, pero realidad dice que existe ✗
            ])->nullable()->comment('Resultado: TP, TN, FP, FN');
            
            $table->boolean('is_correct')->virtualAs('match_result IN ("TRUE_POSITIVE", "TRUE_NEGATIVE")')
                  ->comment('¿El diagnóstico fue correcto?');
            
            // ═══════════════════════════════════════════════════════
            // OBSERVACIONES
            // ═══════════════════════════════════════════════════════
            $table->text('system_notes')->nullable()
                  ->comment('Datos adicionales del análisis del sistema');
            $table->text('validation_notes')->nullable()
                  ->comment('Notas de la validación manual');
            $table->text('discrepancy_reason')->nullable()
                  ->comment('Si hay discrepancia, explicar por qué');
            
            // ═══════════════════════════════════════════════════════
            // ESTADO
            // ═══════════════════════════════════════════════════════
            $table->boolean('included_in_pds')->default(true)
                  ->comment('¿Incluir en cálculo de PDS?');
            
            $table->timestamps();
            
            // Índices
            $table->index('location_id');
            $table->index('test_type');
            $table->index('match_result');
            $table->index('validated_at');
            $table->index('is_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_tests');
    }
};
