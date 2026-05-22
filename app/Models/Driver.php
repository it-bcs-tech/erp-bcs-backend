<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    protected $connection = 'pgsql_master';
    protected $table = 'm_drivers';

    protected $fillable = [
        'karyawan_id',
        'driver_category',
        'sim_type',
        'sim_expiry_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sim_expiry_date' => 'date',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function karyawan()
    {
        return $this->belongsTo(Employee::class, 'karyawan_id', 'id');
    }

    // ── Status Mapping ──────────────────────────────────

    /**
     * Map internal DB status to API contract status.
     * DB: ACTIVE, INACTIVE, etc.
     * API: On Duty, Available, On Leave, Off Duty
     */
    public function getApiStatusAttribute(): string
    {
        return match (strtoupper($this->status)) {
            'ACTIVE'   => 'Available',
            'ON_DUTY'  => 'On Duty',
            'ON_LEAVE' => 'On Leave',
            default    => 'Off Duty',
        };
    }
}
