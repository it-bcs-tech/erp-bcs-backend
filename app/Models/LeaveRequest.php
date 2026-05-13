<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'type',         // Annual, Sick, Personal, Maternity, etc.
        'start_date',
        'end_date',
        'reason',
        'status',       // Pending, Approved, Rejected
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
