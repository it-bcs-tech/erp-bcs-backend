<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'leaves';

    protected $fillable = [
        'user_id',
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

    public function User()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }
}
