<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thesis_metrics', function (Blueprint $table) {
            $table->decimal('pf_percentage', 5, 2)->nullable()
                  ->after('nces_calculated_at')
                  ->comment('Índice de pérdida de fertilizante en porcentaje');
            $table->decimal('pf_reference_ce', 8, 4)->nullable()
                  ->after('pf_percentage')
                  ->comment('CE de referencia del cultivo');
            $table->decimal('pf_measured_ce', 8, 4)->nullable()
                  ->after('pf_reference_ce')
                  ->comment('CE medida por los sensores en el período');
            $table->timestamp('pf_calculated_at')->nullable()
                  ->after('pf_measured_ce')
                  ->comment('Cuándo se calculó el índice PF');
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->decimal('reference_ce', 8, 4)->nullable()->after('crop_type')
                  ->comment('CE de referencia requerido por el cultivo');
        });
    }

    public function down(): void
    {
        Schema::table('thesis_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'pf_percentage',
                'pf_reference_ce',
                'pf_measured_ce',
                'pf_calculated_at',
            ]);
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('reference_ce');
        });
    }
};
