<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_consolidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade');
            $table->date('consolidation_date');
            $table->unsignedInteger('vp')->default(0);
            $table->unsignedInteger('fp')->default(0);
            $table->unsignedInteger('fn')->default(0);
            $table->unsignedInteger('total_evaluations')->default(0);
            $table->decimal('pds_percentage', 6, 2)->default(0);
            $table->decimal('error_rate', 6, 2)->default(0);
            $table->boolean('is_closed')->default(true);
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['lote_id', 'consolidation_date'], 'uq_lote_date_consolidation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_consolidations');
    }
};