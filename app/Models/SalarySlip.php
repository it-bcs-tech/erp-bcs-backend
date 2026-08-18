<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySlip extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_presensi';
    protected $table = 'salary_slips';

    protected $fillable = [
        'user_id',
        'employee_nik',
        'employee_name',
        'employee_position',
        'employee_division',
        'period',
        'basic_salary',
        'professional_allowance',
        'performance_allowance',
        'position_allowance',
        'meal_allowance',
        'transport_allowance',
        'relocation_allowance',
        'skill_allowance',
        'other_allowance',
        'incentive',
        'communication_allowance',
        'overtime_allowance',
        'overtime_hours',
        'khk_allowance',
        'khk_count',
        'gross_salary',
        'zakat',
        'tax',
        'bpjs',
        'union_fee',
        'absence_deduction',
        'absence_days',
        'cooperative',
        'bpr_installment',
        'other_deduction',
        'total_deductions',
        'net_salary',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary'           => 'float',
            'professional_allowance' => 'float',
            'performance_allowance'  => 'float',
            'position_allowance'     => 'float',
            'meal_allowance'         => 'float',
            'transport_allowance'    => 'float',
            'relocation_allowance'   => 'float',
            'skill_allowance'        => 'float',
            'other_allowance'        => 'float',
            'incentive'              => 'float',
            'communication_allowance'=> 'float',
            'overtime_allowance'     => 'float',
            'overtime_hours'         => 'float',
            'khk_allowance'          => 'float',
            'khk_count'              => 'integer',
            'gross_salary'           => 'float',
            'zakat'                  => 'float',
            'tax'                    => 'float',
            'bpjs'                   => 'float',
            'union_fee'              => 'float',
            'absence_deduction'      => 'float',
            'absence_days'           => 'integer',
            'cooperative'            => 'float',
            'bpr_installment'        => 'float',
            'other_deduction'        => 'float',
            'total_deductions'       => 'float',
            'net_salary'             => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(PresensiUser::class, 'user_id', 'id');
    }
}
