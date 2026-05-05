<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('recruitment_jobs')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role');
            $table->string('experience')->nullable();
            $table->string('resume_url')->nullable();
            $table->enum('pipeline_stage', ['Applied', 'Screening', 'Interview', 'Offered', 'Hired', 'Rejected'])->default('Applied');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidates');
    }
};
