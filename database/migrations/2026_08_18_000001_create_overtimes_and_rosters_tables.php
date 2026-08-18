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
        if (!Schema::connection('pgsql_presensi')->hasTable('overtime_requests')) {
            Schema::connection('pgsql_presensi')->create('overtime_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('start_time', 10);
                $table->string('end_time', 10);
                $table->text('description');
                $table->string('status')->default('pending')->index(); // pending, approved, rejected
                $table->string('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('pgsql_presensi')->hasTable('shift_rosters')) {
            Schema::connection('pgsql_presensi')->create('shift_rosters', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id')->index(); // EMP-001
                $table->string('employee_name');
                $table->string('department')->nullable();
                $table->string('pool')->nullable();
                $table->json('schedule')->nullable(); // ["S1", "S1", "S2", "S2", "S3", "OFF", "OFF"]
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_presensi')->dropIfExists('shift_rosters');
        Schema::connection('pgsql_presensi')->dropIfExists('overtime_requests');
    }
};
