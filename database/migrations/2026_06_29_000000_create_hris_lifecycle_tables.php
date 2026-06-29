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
        // 1. Create hris.employee_lifecycle table if not exists
        DB::statement('CREATE TABLE IF NOT EXISTS hris.employee_lifecycle (
            id BIGSERIAL PRIMARY KEY,
            document_no VARCHAR(50) NULL,
            payroll_id VARCHAR(50) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_description TEXT NULL,
            status VARCHAR(10) DEFAULT \'P\',
            dept_from VARCHAR(50) NULL,
            dept_to VARCHAR(50) NULL,
            title_from VARCHAR(50) NULL,
            title_to VARCHAR(50) NULL,
            loc_from VARCHAR(50) NULL,
            loc_to VARCHAR(50) NULL,
            start_date DATE NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        // 2. Create hris.employee_warnings table if not exists
        DB::statement('CREATE TABLE IF NOT EXISTS hris.employee_warnings (
            id BIGSERIAL PRIMARY KEY,
            document_no VARCHAR(50) NULL,
            payroll_id VARCHAR(50) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            remarks TEXT NULL,
            status VARCHAR(10) DEFAULT \'A\',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        // 3. Create hris.employee_terminations table if not exists
        DB::statement('CREATE TABLE IF NOT EXISTS hris.employee_terminations (
            id BIGSERIAL PRIMARY KEY,
            document_no VARCHAR(50) NULL,
            payroll_id VARCHAR(50) NOT NULL,
            termination_type VARCHAR(50) NOT NULL,
            reason_out TEXT NULL,
            status VARCHAR(10) DEFAULT \'P\',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS hris.employee_terminations');
        DB::statement('DROP TABLE IF EXISTS hris.employee_warnings');
        DB::statement('DROP TABLE IF EXISTS hris.employee_lifecycle');
    }
};
