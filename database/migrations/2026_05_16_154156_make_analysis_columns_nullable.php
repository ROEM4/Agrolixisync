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
        Schema::table('analysis', function (Blueprint $table) {
            $table->unsignedBigInteger('sensor_superficial_id')->nullable()->change();
            $table->unsignedBigInteger('sensor_profundo_id')->nullable()->change();
            $table->unsignedBigInteger('reading_superficial_id')->nullable()->change();
            $table->unsignedBigInteger('reading_profundo_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('analysis', function (Blueprint $table) {
            $table->unsignedBigInteger('sensor_superficial_id')->nullable(false)->change();
            $table->unsignedBigInteger('sensor_profundo_id')->nullable(false)->change();
            $table->unsignedBigInteger('reading_superficial_id')->nullable(false)->change();
            $table->unsignedBigInteger('reading_profundo_id')->nullable(false)->change();
        });
    }
};
