<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            // Timestamp original enviado por el RTC del ESP32
            $table->timestamp('device_timestamp')->nullable()->after('recorded_at');
            // Estado de lixiviación calculado en el ESP32 (para comparar con el servidor)
            $table->string('device_estado', 50)->nullable()->after('device_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn(['device_timestamp', 'device_estado']);
        });
    }
};
