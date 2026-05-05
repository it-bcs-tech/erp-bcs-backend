<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department_id',
        'description',
        'requirements',
        'location',
        'employment_type',  // Full-time, Part-time, Contract
        'status',           // Open, Closed, On Hold
    ];

    // ── Relationships ───────────────────────────────────

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function candidates()
    {
        return $this->hasMany(RecruitmentCandidate::class, 'job_id');
    }
}
