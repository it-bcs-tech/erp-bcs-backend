<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'erp.activity_logs';

    protected $fillable = [
        'type',         // employee_joined, leave_approved, policy_changed, etc.
        'description',
        'employee_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(ErpEmployee::class, 'employee_id');
    }
}
