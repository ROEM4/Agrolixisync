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
        Schema::table('ec_readings', function (Blueprint $table) {
            // Hacer lote_id nullable
            $table->unsignedBigInteger('lote_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_readings', function (Blueprint $table) {
            $table->unsignedBigInteger('lote_id')->nullable(false)->change();
        });
    }
};
