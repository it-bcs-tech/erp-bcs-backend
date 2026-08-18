<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'reimbursements';

    protected $fillable = [
        'user_id',
        'employee_name',
        'employee_nik',
        'claim_type',
        'description',
        'amount',
        'receipt_attachment_url',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'float',
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(PresensiUser::class, 'user_id', 'id');
    }
}
