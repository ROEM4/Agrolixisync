<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Nueva tabla: data_exports
     * 
     * Rastreo de exportaciones automáticas de datos CSV.
     * Permite auditar qué datos fueron exportados, cuándo y por quién.
     */
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            
            // ═══════════════════════════════════════════════════════
            // INFORMACIÓN DEL EXPORT
            // ═══════════════════════════════════════════════════════
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('set null');
            
            $table->enum('export_type', [
                'FULL_EXPORT',          // Todas las lecturas
                'ANALYSIS_EXPORT',      // Solo análisis
                'THESIS_METRICS',       // Solo métricas de tesis
                'SYSTEM_TESTS',         // Solo pruebas del sistema
                'PERIODIC_EXPORT',      // Exportación programada
            ])->comment('Tipo de exportación realizada');
            
            // ═══════════════════════════════════════════════════════
            // PERÍODO
            // ═══════════════════════════════════════════════════════
            $table->date('period_start')->comment('Inicio del período exportado');
            $table->date('period_end')->comment('Fin del período exportado');
            
            // ═══════════════════════════════════════════════════════
            // ARCHIVO
            // ═══════════════════════════════════════════════════════
            $table->string('filename')->comment('Nombre del archivo CSV');
            $table->string('filepath')->comment('Ruta relativa en storage/');
            $table->unsignedBigInteger('file_size_bytes')->comment('Tamaño del archivo en bytes');
            $table->unsignedInteger('record_count')->comment('Número de registros exportados');
            
            // ═══════════════════════════════════════════════════════
            // EJECUCIÓN
            // ═══════════════════════════════════════════════════════
            $table->enum('export_status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])
                  ->default('PENDING')
                  ->comment('Estado de la exportación');
            
            $table->string('triggered_by')->default('scheduler')->comment('Sistema o usuario que disparó');
            $table->timestamp('started_at')->nullable()->comment('Cuándo empezó');
            $table->timestamp('completed_at')->nullable()->comment('Cuándo terminó');
            $table->string('error_message')->nullable()->comment('Mensaje de error si falló');
            
            // ═══════════════════════════════════════════════════════
            // DESTINO
            // ═══════════════════════════════════════════════════════
            $table->enum('storage_location', [
                'LOCAL_STORAGE',        // storage/app/exports/
                'CLOUD_DRIVE',          // Google Drive, OneDrive
                'MICROSD',              // ESP32 microSD card
                'DATABASE',             // Guardado en BD
                'EMAIL',                // Enviado por email
            ])->default('LOCAL_STORAGE')->comment('Dónde se guardó');
            
            $table->string('cloud_url')->nullable()->comment('URL en cloud storage');
            $table->string('email_recipient')->nullable()->comment('Email al que se envió');
            
            // ═══════════════════════════════════════════════════════
            // METADATOS
            // ═══════════════════════════════════════════════════════
            $table->text('query_filters')->nullable()->comment('Filtros aplicados en JSON');
            $table->text('notes')->nullable()->comment('Notas sobre la exportación');
            $table->boolean('is_backup')->default(false)->comment('¿Es copia de seguridad?');
            
            $table->timestamps();
            
            // Índices
            $table->index('location_id');
            $table->index('export_type');
            $table->index('export_status');
            $table->index('period_start');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
