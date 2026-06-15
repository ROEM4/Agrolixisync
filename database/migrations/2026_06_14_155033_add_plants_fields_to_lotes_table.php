<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {

            // SOLO agrega si no existen (evita errores en tu caso actual)
            if (!Schema::hasColumn('lotes', 'plant_number')) {
                $table->integer('plant_number')->nullable()->after('id');
            }

            if (!Schema::hasColumn('lotes', 'experimental_group')) {
                $table->string('experimental_group')->nullable()->after('plant_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            if (Schema::hasColumn('lotes', 'plant_number')) {
                $table->dropColumn('plant_number');
            }

            if (Schema::hasColumn('lotes', 'experimental_group')) {
                $table->dropColumn('experimental_group');
            }
        });
    }
};