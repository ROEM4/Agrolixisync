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

        // inicializar correctamente
        $locations = \App\Models\Location::all();

        foreach ($locations as $location) {
            $location->update([
                'alert_settings' => [
                    'lixiviacion_alta' => true,
                    'lixiviacion' => true,
                    'acumulacion' => true,
                ]
            ]);
        }
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
