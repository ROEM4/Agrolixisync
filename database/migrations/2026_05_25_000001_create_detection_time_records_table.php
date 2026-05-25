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
        Schema::create('detection_time_records', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade');
            
            // Tiempo Promedio de Detección en segundos
            $table->integer('tiempo_promedio_segundos');
            
            // Datos de agrupación
            $table->integer('cantidad_eventos');
            $table->integer('suma_tiempos_segundos');
            
            // Tipo de entrada: 'manual' o 'automatico'
            $table->enum('tipo_entrada', ['manual', 'automatico'])->default('automatico');
            
            // Para rastrear cambios
            $table->timestamps();
            
            // Índices
            $table->unique(['fecha', 'location_id']);
            $table->index('lote_id');
            $table->index('tipo_entrada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detection_time_records');
    }
};
