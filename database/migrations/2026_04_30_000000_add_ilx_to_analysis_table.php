<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `ilx` a la tabla `analysis`.
 *
 * ILx = CE_p / CE_s — Índice de Lixiviación (indicador principal, modelo agronómico v3).
 * Es el único criterio de clasificación de estado.
 *
 * ΔCE (delta_conductivity) se mantiene como dato complementario (ya existe en la tabla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis', function (Blueprint $table) {
            // ILx = CE_p / CE_s  (0 cuando CE_s == 0, para evitar division por cero)
            $table->decimal('ilx', 8, 4)->nullable()->after('delta_conductivity')
                  ->comment('Índice de Lixiviación = CE_p / CE_s. Indicador principal v3.');

            // Estado agronomico derivado de ILx
            $table->string('ilx_estado', 30)->nullable()->after('ilx')
                  ->comment('LIXIVIACIÓN ALTA|LIXIVIACIÓN|EQUILIBRIO|RETENCIÓN|ACUMULACIÓN');
        });
    }

    public function down(): void
    {
        Schema::table('analysis', function (Blueprint $table) {
            $table->dropColumn(['ilx', 'ilx_estado']);
        });
    }
};
