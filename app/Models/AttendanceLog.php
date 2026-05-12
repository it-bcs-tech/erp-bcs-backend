<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'erp.attendance_logs';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'status',       // On Time, Late, Absent, Remote
        'work_type',    // On-Site, Remote
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'check_in'  => 'datetime',
            'check_out' => 'datetime',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(ErpEmployee::class, 'employee_id');
    }
}
