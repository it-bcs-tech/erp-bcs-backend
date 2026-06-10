<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiUser extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_master';
    protected $table = 'm_presensi';

    protected $fillable = [
        'karyawan_id',
        'name',
        'email',
        'password',
        'photo',
        'phone',
        'address',
        'device_token',
        'is_active',
        'role',
        'employment_type',
        'office_location_id',
        'pin',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'karyawan_id');
    }
}
