<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->decimal('ce_actual', 8, 4)->nullable()->after('description');
            $table->decimal('ce_anterior', 8, 4)->nullable()->after('ce_actual');
            $table->decimal('delta_ce', 8, 4)->nullable()->after('ce_anterior');
            $table->timestamp('tiempo_alerta')->nullable()->after('delta_ce');
            $table->timestamp('tiempo_riesgo')->nullable()->after('tiempo_alerta');
            $table->integer('tar')->nullable()->after('tiempo_riesgo')->comment('TAR in seconds');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['ce_actual', 'ce_anterior', 'delta_ce', 'tiempo_alerta', 'tiempo_riesgo', 'tar']);
        });
    }
};
