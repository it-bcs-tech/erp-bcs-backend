<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'loans';

    protected $fillable = [
        'user_id',
        'amount',
        'tenor_months',
        'interest_rate_percent',
        'interest_amount_per_month',
        'admin_fee',
        'monthly_installment',
        'total_repayment',
        'disbursement_amount',
        'remaining_amount',
        'reason',
        'reason_detail',
        'status',
        'bank_name',
        'bank_account_number',
        'disbursement_date',
        'start_date',
        'end_date',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount'                    => 'float',
            'tenor_months'              => 'integer',
            'interest_rate_percent'     => 'float',
            'interest_amount_per_month' => 'float',
            'admin_fee'                 => 'float',
            'monthly_installment'       => 'float',
            'total_repayment'           => 'float',
            'disbursement_amount'       => 'float',
            'remaining_amount'          => 'float',
            'disbursement_date'         => 'datetime',
            'start_date'                => 'date:Y-m-d',
            'end_date'                  => 'date:Y-m-d',
            'approved_at'               => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(PresensiUser::class, 'user_id', 'id');
    }
}
