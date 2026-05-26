<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // Rename specific lot names
        DB::table('lots')->where('name', 'Lote-01')->update(['name' => 'Subparcelas Grupo Control']);
        DB::table('lots')->where('name', 'Auto-ESP32-G1')->update(['name' => 'Subparcelas Grupo Experimental']);
        // Add description column if not exists
        if (!Schema::hasColumn('lots', 'description')) {
            Schema::table('lots', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }
    public function down() {
        // Revert name changes
        DB::table('lots')->where('name', 'Subparcelas Grupo Control')->update(['name' => 'Lote-01']);
        DB::table('lots')->where('name', 'Subparcelas Grupo Experimental')->update(['name' => 'Auto-ESP32-G1']);
        // Drop description column
        if (Schema::hasColumn('lots', 'description')) {
            Schema::table('lots', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
?>
