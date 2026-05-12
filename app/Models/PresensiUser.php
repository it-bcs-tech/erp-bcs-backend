<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model for users table in presensi_db.
 * Separate from User (master_db.m_presensi).
 */
class PresensiUser extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
    ];

    // ── Relationships ───────────────────────────────────

    public function presences()
    {
        return $this->hasMany(Presence::class, 'user_id');
    }
}
