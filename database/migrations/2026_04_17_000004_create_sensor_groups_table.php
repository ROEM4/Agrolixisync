<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Nueva tabla: sensor_groups
     * 
     * Clasifica sensores/lotes como CONTROL o EXPERIMENTAL.
     * Necesario para cálculo de NCES (Nivel CE Suelo).
     * 
     * NCES = Promedio(CE_control) - Promedio(CE_experimental)
     */
    public function up(): void
    {
        Schema::create('sensor_groups', function (Blueprint $table) {
            $table->id();
            
            // Relación con sensor
            $table->foreignId('sensor_id')->constrained('sensors')->onDelete('cascade');
            
            // ═══════════════════════════════════════════════════════
            // CLASIFICACIÓN
            // ═══════════════════════════════════════════════════════
            $table->enum('group_type', ['CONTROL', 'EXPERIMENTAL', 'REFERENCE'])
                  ->comment('Tipo de grupo: Control, Experimental o Referencia');
            
            // ═══════════════════════════════════════════════════════
            // IDENTIFICACIÓN
            // ═══════════════════════════════════════════════════════
            $table->string('group_name')->comment('Nombre identificador del grupo');
            $table->text('description')->nullable()->comment('Descripción del grupo experimental');
            
            // ═══════════════════════════════════════════════════════
            // PERÍODO DE VIGENCIA
            // ═══════════════════════════════════════════════════════
            $table->date('start_date')->comment('Fecha de inicio del experimento');
            $table->date('end_date')->nullable()->comment('Fecha de fin (NULL si está en curso)');
            
            // ═══════════════════════════════════════════════════════
            // TRATAMIENTO APLICADO (para experimental)
            // ═══════════════════════════════════════════════════════
            $table->text('treatment_applied')->nullable()
                  ->comment('Descripción del tratamiento aplicado (experimental)');
            $table->enum('treatment_type', ['RIEGO', 'NUTRIENTES', 'pH_CORRECTION', 'NONE'])
                  ->default('NONE')
                  ->comment('Tipo de tratamiento');
            
            // ═══════════════════════════════════════════════════════
            // INVESTIGADOR A CARGO
            // ═══════════════════════════════════════════════════════
            $table->string('researcher_name')->nullable()->comment('Nombre del investigador');
            $table->string('researcher_institution')->nullable()->comment('Institución/Universidad');
            $table->string('thesis_title')->nullable()->comment('Título del trabajo de tesis');
            
            // ═══════════════════════════════════════════════════════
            // METADATOS
            // ═══════════════════════════════════════════════════════
            $table->boolean('is_active')->default(true)->comment('¿Grupo activo?');
            $table->text('academic_notes')->nullable()->comment('Notas académicas');
            
            $table->timestamps();
            
            // Índices
            $table->index('sensor_id');
            $table->index('group_type');
            $table->index('start_date');
            $table->index('is_active');
            
            // Única combinación: 1 sensor solo puede estar en 1 grupo activo a la vez
            $table->unique(['sensor_id', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_groups');
    }
};
