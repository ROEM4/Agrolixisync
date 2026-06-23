<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_lixiviacion', function (Blueprint $table) {
            if (!Schema::hasColumn('analisis_lixiviacion', 'ilx_estado')) {
                $table->string('ilx_estado')->nullable()->after('ilx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analisis_lixiviacion', function (Blueprint $table) {
            $table->dropColumn('ilx_estado');
        });
    }
};
