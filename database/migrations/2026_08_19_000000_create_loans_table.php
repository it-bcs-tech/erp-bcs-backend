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
        if (!Schema::connection('pgsql_presensi')->hasTable('loans')) {
            Schema::connection('pgsql_presensi')->create('loans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();

                // Nominal & Calculation fields
                $table->decimal('amount', 14, 2);
                $table->integer('tenor_months');
                $table->decimal('interest_rate_percent', 5, 2)->default(1.00);
                $table->decimal('interest_amount_per_month', 14, 2)->default(0);
                $table->decimal('admin_fee', 14, 2)->default(25000.00);
                $table->decimal('monthly_installment', 14, 2)->default(0);
                $table->decimal('total_repayment', 14, 2)->default(0);
                $table->decimal('disbursement_amount', 14, 2)->default(0);
                $table->decimal('remaining_amount', 14, 2)->default(0);

                // Reason & Status
                $table->string('reason')->default('other'); // education, medical, renovation, emergency, other
                $table->text('reason_detail')->nullable();
                $table->string('status')->default('pending_approval')->index(); // pending_approval, approved, active, completed, rejected

                // Bank & Disbursement Details
                $table->string('bank_name')->nullable();
                $table->string('bank_account_number')->nullable();
                $table->timestamp('disbursement_date')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();

                // Approvals
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_presensi')->dropIfExists('loans');
    }
};
