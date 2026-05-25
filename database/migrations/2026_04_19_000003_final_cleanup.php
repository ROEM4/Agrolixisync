<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── lotes: eliminar nombre, variedad, description (todas NULL, nunca usadas) ──
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'variedad', 'description']);
        });

        // ── locations: eliminar description (NULL, nunca usada) ──────────────────────
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        // ── readings: eliminar soil_moisture, device_estado, device_timestamp ─────────
        // device_timestamp == recorded_at en todos los registros (confirmado)
        // soil_moisture y device_estado: 0 filas con datos
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn(['soil_moisture', 'device_estado', 'device_timestamp']);
        });

        // ── sensor_groups: tabla vacía, reemplazada por sensors.group_type ────────────
        Schema::dropIfExists('sensor_groups');

        // ── alerts: la tabla existe pero nunca se llenó porque el código usaba
        //    columnas 'severity'/'status' que no existían en la migración original.
        //    Ya se agregaron en la migración anterior. Ahora limpiamos columnas
        //    que no aportan valor para el proyecto actual:
        //    recommendation, notified, notified_at (notificaciones no implementadas)
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['recommendation', 'notified', 'notified_at']);
        });
    }

    public function down(): void
    {
        // No reversible intencionalmente — datos eran NULL
    }
};
