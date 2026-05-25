<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Agregar columnas faltantes
            if (!Schema::hasColumn('lotes', 'nombre')) {
                $table->string('nombre')->nullable()->after('id');
            }
            if (!Schema::hasColumn('lotes', 'variedad')) {
                $table->string('variedad')->nullable()->after('nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'variedad']);
        });
    }
};
