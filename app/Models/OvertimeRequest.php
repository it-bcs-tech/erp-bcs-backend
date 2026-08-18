<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'overtime_requests';

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'description',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date'  => 'date:Y-m-d',
            'end_date'    => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(PresensiUser::class, 'user_id', 'id');
    }
}
