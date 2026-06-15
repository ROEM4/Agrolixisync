<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->onDelete('cascade');
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('evaluation_date');
            $table->enum('evaluation', ['VP', 'FP', 'FN']);
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->unique(['alert_id'], 'uq_alert_evaluation');
            $table->index(['lote_id', 'evaluation_date'], 'idx_lote_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_evaluations');
    }
};