<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'manager_id',
        'employee_code',
        'name',
        'email',
        'phone',
        'role',
        'status',
        'join_date',
        'birth_date',
        'address',
        'avatar',
        'leave_balance',
        'performance_score',
    ];

    protected function casts(): array
    {
        return [
            'join_date'   => 'date',
            'birth_date'  => 'date',
            'leave_balance' => 'integer',
            'performance_score' => 'decimal:1',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
