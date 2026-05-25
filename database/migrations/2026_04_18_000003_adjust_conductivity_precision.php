<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE readings MODIFY conductivity DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_superficial DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_profundo DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE analysis MODIFY delta_conductivity DECIMAL(10,4) NOT NULL');
        DB::statement('ALTER TABLE analysis MODIFY threshold_used DECIMAL(10,4) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE readings MODIFY conductivity DECIMAL(8,2) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_superficial DECIMAL(8,2) NULL');
        DB::statement('ALTER TABLE analysis MODIFY conductivity_profundo DECIMAL(8,2) NULL');
        DB::statement('ALTER TABLE analysis MODIFY delta_conductivity DECIMAL(8,2) NOT NULL');
        DB::statement('ALTER TABLE analysis MODIFY threshold_used DECIMAL(8,2) NOT NULL');
    }
};
