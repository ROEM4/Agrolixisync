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
        Schema::table('pf_records', function (Blueprint $table) {
            $table->decimal('ce_superficial', 8, 3)->after('recorded_at')->nullable();
            $table->decimal('ce_profunda', 8, 3)->after('ce_superficial')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pf_records', function (Blueprint $table) {
            $table->dropColumn(['ce_superficial', 'ce_profunda']);
        });
    }
};
