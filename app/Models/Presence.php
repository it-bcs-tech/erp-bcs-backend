<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for presences table in presensi_db.
 * Real-time attendance data from mobile app.
 */
class Presence extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'presences';

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'latitude_in',
        'longitude_in',
        'latitude_out',
        'longitude_out',
        'status',           // present, late, Tepat Waktu, Terlambat
        'face_photo_in',
        'face_photo_out',
        'shift_code',
        'late_minutes',
        'overtime_minutes',
        'working_hours',
        'is_auto_clockout',
    ];

    protected function casts(): array
    {
        return [
            'date'             => 'date',
            'late_minutes'     => 'integer',
            'overtime_minutes' => 'integer',
            'working_hours'    => 'decimal:2',
            'is_auto_clockout' => 'boolean',
        ];
    }

    // ── Relationships ───────────────────────────────────

    /**
     * User from master_db.m_presensi
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function presensiUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ─────────────────────────────────────────

    /**
     * Normalize status to standard English values.
     */
    public function getNormalizedStatusAttribute(): string
    {
        $map = [
            'present'      => 'On Time',
            'late'         => 'Late',
            'Tepat Waktu'  => 'On Time',
            'Terlambat'    => 'Late',
        ];

        return $map[$this->status] ?? ucfirst($this->status);
    }
}
