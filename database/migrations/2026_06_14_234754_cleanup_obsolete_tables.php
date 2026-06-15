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
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('ec_readings');
        Schema::dropIfExists('system_tests');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversible
    }
};
