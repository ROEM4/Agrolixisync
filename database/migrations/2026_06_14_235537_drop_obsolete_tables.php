<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('thesis_metrics');
        Schema::dropIfExists('sd_queue');
        Schema::dropIfExists('device_logs');
    }

    public function down(): void
    {
        // No revertir
    }
};