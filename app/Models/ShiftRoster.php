<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftRoster extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'shift_rosters';

    protected $fillable = [
        'employee_id',
        'employee_name',
        'department',
        'pool',
        'schedule',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
        ];
    }
}
