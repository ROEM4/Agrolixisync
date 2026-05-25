<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            
            // Sensores comparados
            $table->foreignId('sensor_superficial_id')->constrained('sensors')->onDelete('restrict');
            $table->foreignId('sensor_profundo_id')->constrained('sensors')->onDelete('restrict');
            
            // Lecturas utilizadas en el análisis
            $table->foreignId('reading_superficial_id')->nullable()->constrained('readings')->onDelete('set null');
            $table->foreignId('reading_profundo_id')->nullable()->constrained('readings')->onDelete('set null');
            
            // Cálculos del análisis
            $table->decimal('conductivity_superficial', 8, 2)->nullable();
            $table->decimal('conductivity_profundo', 8, 2)->nullable();
            $table->decimal('delta_conductivity', 8, 2); // Diferencia (profundo - superficial)
            $table->decimal('threshold_used', 8, 2); // Umbral aplicado en este análisis
            
            // Resultado
            $table->boolean('lixiviation_detected')->default(false); // ¿Se detectó lixiviación?
            $table->string('risk_level')->default('bajo'); // bajo, medio, alto
            $table->decimal('risk_percentage', 5, 2)->default(0); // % de riesgo
            
            // Observaciones
            $table->text('notes')->nullable();
            
            // Timestamp del análisis
            $table->timestamp('analyzed_at')->useCurrent(); // cuándo se hizo el análisis
            $table->timestamps();
            
            // Índices
            $table->index('lote_id');
            $table->index('location_id');
            $table->index('analyzed_at');
            $table->index('lixiviation_detected');
            $table->index(['lote_id', 'analyzed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis');
    }
};
