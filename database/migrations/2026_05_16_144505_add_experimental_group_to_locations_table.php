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
        Schema::table('locations', function (Blueprint $table) {
            $table->enum('experimental_group', ['control', 'experimental'])->default('experimental')->after('name');
        });

        Schema::table('pf_records', function (Blueprint $table) {
            $table->enum('experimental_group', ['control', 'experimental'])->nullable()->after('location_id');
        });

        Schema::table('analysis', function (Blueprint $table) {
            $table->enum('experimental_group', ['control', 'experimental'])->nullable()->after('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('experimental_group');
        });
        Schema::table('pf_records', function (Blueprint $table) {
            $table->dropColumn('experimental_group');
        });
        Schema::table('analysis', function (Blueprint $table) {
            $table->dropColumn('experimental_group');
        });
    }
};
