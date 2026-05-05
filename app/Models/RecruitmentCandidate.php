<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'name',
        'email',
        'phone',
        'role',
        'experience',
        'resume_url',
        'pipeline_stage',   // Applied, Screening, Interview, Offered, Hired, Rejected
        'notes',
    ];

    // ── Relationships ───────────────────────────────────

    public function job()
    {
        return $this->belongsTo(RecruitmentJob::class, 'job_id');
    }
}
