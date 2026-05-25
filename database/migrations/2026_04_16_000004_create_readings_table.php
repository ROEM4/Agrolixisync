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
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->onDelete('cascade');
            
            // Métricas del sensor
            $table->decimal('conductivity', 8, 2)->nullable(); // µS/cm - Conductividad
            $table->decimal('humidity', 5, 2)->nullable();     // % - Humedad relativa
            $table->decimal('temperature', 5, 2)->nullable();  // °C - Temperatura
            $table->decimal('soil_moisture', 5, 2)->nullable(); // % - Humedad del suelo
            
            // Timestamp preciso de la medición (puede diferir del created_at)
            $table->timestamp('recorded_at')->useCurrent(); // cuándo se tomó la medición
            $table->timestamps(); // created_at, updated_at
            
            // Índices para queries frecuentes (historial, rangos de fecha)
            $table->index('sensor_id');
            $table->index('recorded_at');
            $table->index(['sensor_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
