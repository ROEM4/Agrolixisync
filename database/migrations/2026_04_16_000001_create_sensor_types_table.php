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
        Schema::create('sensor_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // ej: "Sensor de Humedad", "Sensor de Conductividad"
            $table->text('description')->nullable();
            $table->string('unit')->default(''); // ej: "%", "µS/cm"
            $table->string('model')->nullable(); // ej: "DHT22", "EC-4P"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_types');
    }
};
