<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('alert_evaluations', function (Blueprint $table) {
            $table->foreignId('lote_id')->nullable()->after('alert_id')->constrained('lotes')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('lote_id')->constrained('locations')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('alert_evaluations', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
            $table->dropForeign(['location_id']);
            $table->dropColumn(['lote_id', 'location_id']);
        });
    }
};