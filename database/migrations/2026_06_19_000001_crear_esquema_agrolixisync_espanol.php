<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración maestra — Esquema AgroLixiSync en español
 *
 * IMPORTANTE: Las tablas ya existen en la BD. Esta migración
 * se registra para mantener el control de versiones limpio.
 * Solo crea tablas si NO existen (idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── USUARIOS ────────────────────────────────────────────────────────
        if (!Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('rol')->default('agricultor'); // admin | agricultor
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // ─── PLANTAS ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('plantas')) {
            Schema::create('plantas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->string('nombre');
                $table->integer('numero_planta');
                $table->string('grupo_experimental')->default('experimental'); // experimental | control
                $table->string('tipo_cultivo')->default('palta');
                $table->text('descripcion')->nullable();
                $table->decimal('ce_referencia', 8, 4)->nullable();
                $table->timestamps();
            });
        }

        // ─── UBICACIONES ─────────────────────────────────────────────────────
        if (!Schema::hasTable('ubicaciones')) {
            Schema::create('ubicaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('planta_id')->constrained('plantas')->cascadeOnDelete();
                $table->string('nombre');
                $table->string('grupo_experimental')->nullable();
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->boolean('activa')->default(true);
                $table->json('configuracion_alertas')->nullable();
                $table->string('codigo_dispositivo')->nullable();
                $table->timestamps();
            });
        }

        // ─── SENSORES ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('sensores')) {
            Schema::create('sensores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ubicacion_id')->constrained('ubicaciones')->cascadeOnDelete();
                $table->string('codigo')->unique();
                $table->string('nombre')->nullable();
                $table->decimal('profundidad', 8, 2)->default(20); // cm
                $table->string('tipo_grupo')->nullable();
                $table->boolean('activo')->default(true);
                $table->string('estado')->default('activo');
                $table->text('notas')->nullable();
                $table->timestamp('ultima_lectura')->nullable();
                $table->timestamps();
            });
        }

        // ─── LECTURAS ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('lecturas')) {
            Schema::create('lecturas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sensor_id')->constrained('sensores')->cascadeOnDelete();
                $table->decimal('conductividad', 10, 4)->nullable();
                $table->decimal('humedad', 6, 2)->nullable();
                $table->decimal('temperatura', 6, 2)->nullable();
                $table->timestamp('fecha_registro')->useCurrent();
                $table->timestamps();

                $table->index(['sensor_id', 'fecha_registro']);
            });
        }

        // ─── ANÁLISIS LIXIVIACIÓN ─────────────────────────────────────────────
        if (!Schema::hasTable('analisis_lixiviacion')) {
            Schema::create('analisis_lixiviacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('planta_id')->nullable()->constrained('plantas')->nullOnDelete();
                $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
                $table->foreignId('sensor_superficial_id')->nullable()->constrained('sensores')->nullOnDelete();
                $table->foreignId('sensor_profundo_id')->nullable()->constrained('sensores')->nullOnDelete();
                $table->foreignId('lectura_superficial_id')->nullable()->constrained('lecturas')->nullOnDelete();
                $table->foreignId('lectura_profundo_id')->nullable()->constrained('lecturas')->nullOnDelete();
                $table->string('grupo_experimental')->nullable();
                $table->decimal('conductividad_superficial', 10, 4)->nullable();
                $table->decimal('conductividad_profundo', 10, 4)->nullable();
                $table->decimal('delta_conductividad', 10, 4)->nullable();
                $table->decimal('ilx', 8, 4)->nullable();
                $table->string('ilx_estado')->nullable();
                $table->decimal('umbral_usado', 8, 4)->nullable();
                $table->boolean('lixiviacion_detectada')->default(false);
                $table->string('nivel_riesgo')->default('bajo');
                $table->decimal('porcentaje_riesgo', 5, 2)->nullable();
                $table->timestamp('fecha_analisis')->nullable();
                $table->timestamp('fecha_deteccion')->nullable();
                $table->timestamp('fecha_generacion_alerta')->nullable();
                $table->string('tipo_evento')->nullable();
                $table->boolean('validado')->default(false);
                $table->timestamp('fecha_validacion')->nullable();
                $table->string('validado_por')->nullable();
                $table->text('notas_academicas')->nullable();
                $table->timestamps();
            });
        }

        // ─── ALERTAS ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('alertas')) {
            Schema::create('alertas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('analisis_lixiviacion_id')->nullable()->constrained('analisis_lixiviacion')->nullOnDelete();
                $table->foreignId('planta_id')->nullable()->constrained('plantas')->nullOnDelete();
                $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
                $table->string('subparcela')->nullable();
                $table->string('tipo')->nullable();
                $table->string('severidad')->nullable();
                $table->string('estado')->default('ABIERTA');
                $table->string('nivel')->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('ce_actual', 10, 3)->nullable();
                $table->decimal('ce_anterior', 10, 3)->nullable();
                $table->decimal('delta_ce', 10, 3)->nullable();
                $table->timestamp('tiempo_alerta')->nullable();
                $table->timestamp('tiempo_riesgo')->nullable();
                $table->integer('tar')->nullable();
                $table->boolean('resuelta')->default(false);
                $table->timestamp('fecha_resolucion')->nullable();
                $table->text('notas_resolucion')->nullable();
                $table->timestamps();
            });
        }

        // ─── OBSERVACIONES CAMPO ─────────────────────────────────────────────
        if (!Schema::hasTable('observaciones_campo')) {
            Schema::create('observaciones_campo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
                $table->foreignId('alerta_id')->nullable()->constrained('alertas')->nullOnDelete();
                $table->string('grupo_experimental')->nullable();
                $table->decimal('ce_real', 10, 4)->nullable();
                $table->string('diagnostico')->nullable();
                $table->string('resultado')->nullable(); // VP | FP | FN | VN
                $table->boolean('consolidado')->default(false);
                $table->timestamps();
            });
        }

        // ─── EVALUACIONES ALERTA ─────────────────────────────────────────────
        if (!Schema::hasTable('evaluaciones_alerta')) {
            Schema::create('evaluaciones_alerta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alerta_id')->nullable()->constrained('alertas')->nullOnDelete();
                $table->foreignId('planta_id')->nullable()->constrained('plantas')->nullOnDelete();
                $table->string('resultado')->nullable(); // VP | FP | FN | VN
                $table->text('notas')->nullable();
                $table->boolean('consolidado')->default(false);
                $table->timestamp('consolidado_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── CONSOLIDACIONES DIARIAS ──────────────────────────────────────────
        if (!Schema::hasTable('consolidaciones_diarias')) {
            Schema::create('consolidaciones_diarias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('planta_id')->nullable()->constrained('plantas')->nullOnDelete();
                $table->date('fecha');
                $table->integer('vp')->default(0);
                $table->integer('fp')->default(0);
                $table->integer('fn')->default(0);
                $table->integer('vn')->default(0);
                $table->decimal('pds', 5, 2)->nullable();
                $table->timestamps();
            });
        }

        // ─── REGISTROS PORCENTAJE PÉRDIDA ─────────────────────────────────────
        if (!Schema::hasTable('registros_porcentaje_perdida')) {
            Schema::create('registros_porcentaje_perdida', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
                $table->string('grupo_experimental')->nullable();
                $table->timestamp('fecha_registro')->nullable();
                $table->decimal('ce_superficial', 10, 4)->nullable();
                $table->decimal('ce_profunda', 10, 4)->nullable();
                $table->decimal('ce_referencia', 10, 4)->nullable();
                $table->decimal('ce_medida', 10, 4)->nullable();
                $table->decimal('porcentaje_pf', 8, 2)->nullable();
                $table->string('subparcela')->nullable();
                $table->timestamps();
            });
        }

        // ─── TIEMPOS DETECCIÓN ─────────────────────────────────────────────────
        if (!Schema::hasTable('tiempos_deteccion')) {
            Schema::create('tiempos_deteccion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
                $table->string('grupo_experimental')->nullable();
                $table->timestamp('fecha_inicio')->nullable();
                $table->timestamp('fecha_deteccion')->nullable();
                $table->integer('tiempo_segundos')->nullable();
                $table->string('resultado')->nullable(); // VP | FP | FN
                $table->text('notas')->nullable();
                $table->string('subparcela')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No se hace rollback de la estructura principal en producción
        Schema::dropIfExists('tiempos_deteccion');
        Schema::dropIfExists('registros_porcentaje_perdida');
        Schema::dropIfExists('consolidaciones_diarias');
        Schema::dropIfExists('evaluaciones_alerta');
        Schema::dropIfExists('observaciones_campo');
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('analisis_lixiviacion');
        Schema::dropIfExists('lecturas');
        Schema::dropIfExists('sensores');
        Schema::dropIfExists('ubicaciones');
        Schema::dropIfExists('plantas');
        Schema::dropIfExists('usuarios');
    }
};
