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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->nullable()->constrained('analysis')->onDelete('set null');
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            
            // Tipo de alerta
            $table->string('type'); // lixiviacion, temperatura_alta, temperatura_baja, humedad_baja, etc.
            $table->string('level'); // bajo, medio, alto, crítico
            
            // Descripción
            $table->text('description');
            $table->text('recommendation')->nullable(); // Recomendación de acción
            
            // Estado
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            
            // Notificación
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('lote_id');
            $table->index('location_id');
            $table->index('type');
            $table->index('level');
            $table->index('is_resolved');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
