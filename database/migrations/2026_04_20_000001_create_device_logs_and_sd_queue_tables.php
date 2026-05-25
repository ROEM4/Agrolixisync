<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de soporte IoT industrial:
 *
 * device_logs  — Registro de estado del ESP32 (WiFi, errores, reinicios, heartbeat)
 * sd_queue     — Cola offline trazable: cada entrada tiene estado (pending/sent/failed)
 *                Permite auditar qué datos llegaron desde la SD y cuáles fallaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // device_logs: estado del dispositivo ESP32
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();

            // FK a location (auto-provisioned)
            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->nullOnDelete();

            $table->string('device_code', 64)->index();  // ej: ESP32-G1

            // Tipo de evento
            $table->enum('event_type', [
                'BOOT',           // reinicio del ESP32
                'WIFI_CONNECTED',
                'WIFI_LOST',
                'SENSOR_ERROR',   // fallo Modbus
                'SD_ERROR',
                'QUEUE_FLUSH',    // vaciado de cola offline
                'HEARTBEAT',      // ping periódico de estado
                'OTA_UPDATE',
            ])->index();

            $table->string('message', 255)->nullable();

            // Métricas de estado en el momento del evento
            $table->smallInteger('wifi_rssi')->unsigned()->nullable();       // dBm
            $table->integer('free_heap_bytes')->unsigned()->nullable();
            $table->integer('uptime_seconds')->unsigned()->nullable();
            $table->smallInteger('queue_size')->unsigned()->nullable();      // registros pendientes en SD
            $table->smallInteger('sent_ok_count')->unsigned()->nullable();
            $table->smallInteger('sent_fail_count')->unsigned()->nullable();

            $table->timestamp('logged_at')->useCurrent()->index();
            $table->timestamps();

            // Índice para consultas de salud por dispositivo
            $table->index(['device_code', 'event_type', 'logged_at']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // sd_queue: cola offline trazable
        //
        // Diferencia con la cola en SD del firmware:
        //   - La SD es el buffer físico en el ESP32
        //   - Esta tabla registra cada intento de envío desde la SD
        //     para auditoría y detección de pérdida de datos
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('sd_queue', function (Blueprint $table) {
            $table->id();

            $table->string('device_code', 64)->index();
            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->nullOnDelete();

            // Datos originales de la lectura offline
            $table->timestamp('device_timestamp');                   // timestamp del RTC del ESP32
            $table->tinyInteger('depth');                            // 20 o 60 cm
            $table->decimal('conductivity', 10, 6);
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();

            // Estado del procesamiento
            $table->enum('status', ['pending', 'sent', 'failed', 'duplicate'])
                  ->default('pending')
                  ->index();

            $table->tinyInteger('retry_count')->unsigned()->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('error_message', 255)->nullable();

            // FK a la lectura creada (si se procesó exitosamente)
            $table->foreignId('reading_id')
                  ->nullable()
                  ->constrained('readings')
                  ->nullOnDelete();

            $table->timestamps();

            // Índice para deduplicación: mismo dispositivo + timestamp + profundidad
            $table->unique(['device_code', 'device_timestamp', 'depth'], 'uq_sd_queue_dedup');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sd_queue');
        Schema::dropIfExists('device_logs');
    }
};
