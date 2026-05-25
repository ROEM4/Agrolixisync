<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pf_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->timestamp('recorded_at')->nullable();
            $table->decimal('ce_reference', 8, 3)->nullable();
            $table->decimal('ce_measured', 8, 3)->nullable();
            $table->string('subparcela')->nullable();
            $table->decimal('pf_percentage', 6, 3)->nullable();
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pf_records');
    }
};
