<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('role');                    // Job title: Software Engineer, HR Manager, etc.
            $table->enum('status', ['Active', 'Inactive', 'On Leave', 'Probation'])->default('Active');
            $table->date('join_date');
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->integer('leave_balance')->default(12);
            $table->decimal('performance_score', 3, 1)->default(0.0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
