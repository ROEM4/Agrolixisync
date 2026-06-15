<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->integer('plant_number')->nullable()->after('id');
            $table->enum('experimental_group', ['control', 'experimental'])
                  ->nullable()
                  ->after('plant_number');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['plant_number', 'experimental_group']);
        });
    }
};
?>