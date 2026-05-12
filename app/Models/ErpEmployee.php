<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for erp.employees table.
 * This is separate from Employee (m_karyawan) because ERP modules
 * (attendance, leaves, recruitment) reference erp.employees.
 */
class ErpEmployee extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'erp.employees';

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
            'join_date'         => 'date',
            'birth_date'        => 'date',
            'leave_balance'     => 'integer',
            'performance_score' => 'decimal:1',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function manager()
    {
        return $this->belongsTo(ErpEmployee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(ErpEmployee::class, 'manager_id');
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'employee_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }
}
