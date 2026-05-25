<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ec_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained()->onDelete('cascade');
            $table->decimal('value', 8, 2);        // EC
            $table->decimal('humidity', 5, 2);     // Humedad %
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ec_readings');
    }
};