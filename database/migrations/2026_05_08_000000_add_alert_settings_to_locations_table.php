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
        Schema::table('locations', function (Blueprint $table) {
            $table->json('alert_settings')->nullable()->after('is_active');
        });

        // Inicializar con valores por defecto para ubicaciones existentes
        \Illuminate\Support\Facades\DB::table('locations')->update([
            'alert_settings' => json_encode([
                'lixiviacion_alta' => true,
                'lixiviacion' => true,
                'acumulacion' => true,
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('alert_settings');
        });
    }
};
