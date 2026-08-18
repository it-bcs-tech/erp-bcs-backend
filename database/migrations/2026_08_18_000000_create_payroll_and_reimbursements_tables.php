<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connection for this migration.
     */
    protected $connection = 'pgsql_presensi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('pgsql_presensi')->hasTable('salary_slips')) {
            Schema::connection('pgsql_presensi')->create('salary_slips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('employee_nik')->index();
                $table->string('employee_name');
                $table->string('employee_position')->nullable();
                $table->string('employee_division')->nullable()->index();
                $table->string('period', 7)->index(); // YYYY-MM

                // Earnings (13 components + count + gross)
                $table->decimal('basic_salary', 14, 2)->default(0);
                $table->decimal('professional_allowance', 14, 2)->default(0);
                $table->decimal('performance_allowance', 14, 2)->default(0);
                $table->decimal('position_allowance', 14, 2)->default(0);
                $table->decimal('meal_allowance', 14, 2)->default(0);
                $table->decimal('transport_allowance', 14, 2)->default(0);
                $table->decimal('relocation_allowance', 14, 2)->default(0);
                $table->decimal('skill_allowance', 14, 2)->default(0);
                $table->decimal('other_allowance', 14, 2)->default(0);
                $table->decimal('incentive', 14, 2)->default(0);
                $table->decimal('communication_allowance', 14, 2)->default(0);
                $table->decimal('overtime_allowance', 14, 2)->default(0);
                $table->decimal('overtime_hours', 8, 2)->default(0);
                $table->decimal('khk_allowance', 14, 2)->default(0);
                $table->integer('khk_count')->default(0);
                $table->decimal('gross_salary', 14, 2)->default(0);

                // Deductions (8 components + days + total)
                $table->decimal('zakat', 14, 2)->default(0);
                $table->decimal('tax', 14, 2)->default(0);
                $table->decimal('bpjs', 14, 2)->default(0);
                $table->decimal('union_fee', 14, 2)->default(0);
                $table->decimal('absence_deduction', 14, 2)->default(0);
                $table->integer('absence_days')->default(0);
                $table->decimal('cooperative', 14, 2)->default(0);
                $table->decimal('bpr_installment', 14, 2)->default(0);
                $table->decimal('other_deduction', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);

                // Take Home Pay
                $table->decimal('net_salary', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::connection('pgsql_presensi')->hasTable('reimbursements')) {
            Schema::connection('pgsql_presensi')->create('reimbursements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('employee_name');
                $table->string('employee_nik');
                $table->string('claim_type'); // Outpatient, Optical, Inpatient, Operational, etc.
                $table->text('description');
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('receipt_attachment_url')->nullable();
                $table->string('status')->default('pending')->index(); // pending, approved, rejected
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_presensi')->dropIfExists('reimbursements');
        Schema::connection('pgsql_presensi')->dropIfExists('salary_slips');
    }
};
