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
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('subparcela')->nullable()->after('location_id');
        });

        Schema::table('detection_time_records', function (Blueprint $table) {
            $table->string('subparcela')->nullable()->after('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('subparcela');
        });

        Schema::table('detection_time_records', function (Blueprint $table) {
            $table->dropColumn('subparcela');
        });
    }
};
