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
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ej: "ESP32_001", "SENSOR_PROF_A"
            $table->string('name')->nullable(); // nombre descriptivo
            $table->foreignId('sensor_type_id')->constrained('sensor_types')->onDelete('restrict');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->decimal('depth', 5, 2); // profundidad en cm (0 = superficial, >0 = profundo)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_reading_at')->nullable();
            $table->string('status')->default('active'); // active, inactive, error
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices para queries frecuentes
            $table->index('location_id');
            $table->index('sensor_type_id');
            $table->index('is_active');
            $table->unique(['location_id', 'depth']); // Una profundidad por ubicación
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
