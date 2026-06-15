<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up()
    {
        DB::table('lotes')
            ->where('name', 'Lote-01')
            ->update(['name' => 'Subparcelas Grupo Control']);

        DB::table('lotes')
            ->where('name', 'Auto-ESP32-G1')
            ->update(['name' => 'Subparcelas Grupo Experimental']);

        Schema::table('lotes', function (Blueprint $table) {
            if (!Schema::hasColumn('lotes', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });
    }

    public function down()
    {
        DB::table('lotes')
            ->where('name', 'Subparcelas Grupo Control')
            ->update(['name' => 'Lote-01']);

        DB::table('lotes')
            ->where('name', 'Subparcelas Grupo Experimental')
            ->update(['name' => 'Auto-ESP32-G1']);

        Schema::table('lotes', function (Blueprint $table) {
            if (Schema::hasColumn('lotes', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
?>
