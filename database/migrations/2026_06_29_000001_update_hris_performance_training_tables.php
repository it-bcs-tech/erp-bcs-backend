<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update hris.performance_kpi table
        DB::statement('ALTER TABLE hris.performance_kpi ADD COLUMN IF NOT EXISTS document_no VARCHAR(100) NULL');
        DB::statement('ALTER TABLE hris.performance_kpi ADD COLUMN IF NOT EXISTS remarks TEXT NULL');

        // 2. Update hris.training_programs table
        // Change id column to VARCHAR(50) - cast existing integer ids to varchar
        DB::statement('ALTER TABLE hris.training_programs ALTER COLUMN id TYPE VARCHAR(50)');
        DB::statement('ALTER TABLE hris.training_programs ADD COLUMN IF NOT EXISTS category VARCHAR(100) NULL');
        DB::statement('ALTER TABLE hris.training_programs ADD COLUMN IF NOT EXISTS trainer VARCHAR(100) NULL');

        // 3. Update hris.training_participants table
        // Change program_id column to VARCHAR(50) - cast existing integer program_ids to varchar
        DB::statement('ALTER TABLE hris.training_participants ALTER COLUMN program_id TYPE VARCHAR(50)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse type change to avoid data loss, just drop newly added columns
        DB::statement('ALTER TABLE hris.training_programs DROP COLUMN IF EXISTS category');
        DB::statement('ALTER TABLE hris.training_programs DROP COLUMN IF EXISTS trainer');
        DB::statement('ALTER TABLE hris.performance_kpi DROP COLUMN IF EXISTS remarks');
        DB::statement('ALTER TABLE hris.performance_kpi DROP COLUMN IF EXISTS document_no');
    }
};
