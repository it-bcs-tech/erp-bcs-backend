<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalarySlip;
use App\Models\Reimbursement;
use App\Models\PresensiUser;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayrollController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/payroll
     * Ringkasan keuangan bulanan & direktori slip gaji
     */
    public function index(Request $request)
    {
        $periodParam = $request->query('period', date('Y-m'));
        if (strlen($periodParam) === 7) {
            $period = $periodParam . '-01';
        } else {
            $period = $periodParam;
        }
        $search   = $request->query('search');
        $division = $request->query('division');
        $perPage  = (int) $request->query('per_page', 20);

        // Subquery summary per periode
        $summaryQuery = SalarySlip::where(function ($q) use ($period, $periodParam) {
            $q->where('period', $period)
              ->orWhere('period', $periodParam)
              ->orWhere('period', 'LIKE', "{$periodParam}%");
        });
        $totalCount      = (int) $summaryQuery->count();
        $totalGross      = (float) $summaryQuery->sum('gross_salary');
        $totalDeductions = (float) $summaryQuery->sum('total_deductions');
        $totalNetThp     = (float) $summaryQuery->sum('net_salary');
        $avgSalary       = $totalCount > 0 ? (float) round($totalNetThp / $totalCount, 2) : 0;

        // Distinct list divisi
        $divisions = SalarySlip::whereNotNull('employee_division')
            ->distinct()
            ->pluck('employee_division')
            ->toArray();

        // Main Query
        $query = SalarySlip::query();

        if ($periodParam) {
            $query->where(function ($q) use ($period, $periodParam) {
                $q->where('period', $period)
                  ->orWhere('period', $periodParam)
                  ->orWhere('period', 'LIKE', "{$periodParam}%");
            });
        }

        if ($division) {
            $query->where('employee_division', $division);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_nik', 'LIKE', "%{$search}%")
                  ->orWhere('employee_name', 'LIKE', "%{$search}%")
                  ->orWhere('employee_position', 'LIKE', "%{$search}%");
            });
        }

        $slips = $query->orderBy('id', 'asc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'total_count'      => $totalCount,
                    'total_gross'      => $totalGross,
                    'total_deductions' => $totalDeductions,
                    'total_net_thp'    => $totalNetThp,
                    'avg_salary'       => $avgSalary,
                ],
                'divisions' => array_values($divisions),
                'slips'     => $slips->items(),
            ],
            'meta'   => [
                'current_page' => $slips->currentPage(),
                'per_page'     => $slips->perPage(),
                'total'        => $slips->total(),
            ],
        ], 200);
    }

    /**
     * PUT /api/v1/hris/payroll/slips/{id}
     * Update komponen slip gaji (Live Edit)
     */
    public function updateSlip(Request $request, $id)
    {
        $slip = SalarySlip::find($id);
        if (!$slip) {
            return response()->json([
                'status' => 'error',
                'message' => 'Slip gaji tidak ditemukan',
            ], 404);
        }

        $data = $request->validate([
            'basic_salary'           => 'nullable|numeric|min:0',
            'professional_allowance' => 'nullable|numeric|min:0',
            'performance_allowance'  => 'nullable|numeric|min:0',
            'position_allowance'     => 'nullable|numeric|min:0',
            'meal_allowance'         => 'nullable|numeric|min:0',
            'transport_allowance'    => 'nullable|numeric|min:0',
            'relocation_allowance'   => 'nullable|numeric|min:0',
            'skill_allowance'        => 'nullable|numeric|min:0',
            'other_allowance'        => 'nullable|numeric|min:0',
            'incentive'              => 'nullable|numeric|min:0',
            'communication_allowance'=> 'nullable|numeric|min:0',
            'overtime_allowance'     => 'nullable|numeric|min:0',
            'overtime_hours'         => 'nullable|numeric|min:0',
            'khk_allowance'          => 'nullable|numeric|min:0',
            'khk_count'              => 'nullable|integer|min:0',
            'zakat'                  => 'nullable|numeric|min:0',
            'tax'                    => 'nullable|numeric|min:0',
            'bpjs'                   => 'nullable|numeric|min:0',
            'union_fee'              => 'nullable|numeric|min:0',
            'absence_deduction'      => 'nullable|numeric|min:0',
            'absence_days'           => 'nullable|integer|min:0',
            'cooperative'            => 'nullable|numeric|min:0',
            'bpr_installment'        => 'nullable|numeric|min:0',
            'other_deduction'        => 'nullable|numeric|min:0',
            'net_salary'             => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($slip, $data) {
            $slip->fill($data);

            // Calculate Gross Salary
            $gross = $slip->basic_salary +
                     $slip->professional_allowance +
                     $slip->performance_allowance +
                     $slip->position_allowance +
                     $slip->meal_allowance +
                     $slip->transport_allowance +
                     $slip->relocation_allowance +
                     $slip->skill_allowance +
                     $slip->other_allowance +
                     $slip->incentive +
                     $slip->communication_allowance +
                     $slip->overtime_allowance +
                     $slip->khk_allowance;

            // Calculate Deductions
            $deductions = $slip->zakat +
                          $slip->tax +
                          $slip->bpjs +
                          $slip->union_fee +
                          $slip->absence_deduction +
                          $slip->cooperative +
                          $slip->bpr_installment +
                          $slip->other_deduction;

            $slip->gross_salary = $gross;
            $slip->total_deductions = $deductions;

            if (isset($data['net_salary'])) {
                $slip->net_salary = (float) $data['net_salary'];
            } else {
                $slip->net_salary = $gross - $deductions;
            }

            $slip->save();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Slip gaji berhasil diperbarui',
            'data'    => [
                'id'         => (int) $slip->id,
                'net_salary' => (float) $slip->net_salary,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/hris/payroll/reimbursements
     * Ringkasan & daftar klaim reimbursement
     */
    public function reimbursements(Request $request)
    {
        $status  = $request->query('status', 'all');
        $search  = $request->query('search');
        $perPage = (int) $request->query('per_page', 20);

        // Summary
        $totalClaims = Reimbursement::count();
        $totalApprovedAmount = (float) Reimbursement::where('status', 'approved')->sum('amount');
        $pendingClaims = Reimbursement::where('status', 'pending')->count();
        $rejectedClaims = Reimbursement::where('status', 'rejected')->count();

        // Query
        $query = Reimbursement::query();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'LIKE', "%{$search}%")
                  ->orWhere('employee_nik', 'LIKE', "%{$search}%")
                  ->orWhere('claim_type', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $claims = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'total_claims'          => $totalClaims,
                    'total_approved_amount' => $totalApprovedAmount,
                    'pending_claims'        => $pendingClaims,
                    'rejected_claims'       => $rejectedClaims,
                ],
                'claims'  => $claims->items(),
            ],
            'meta'   => [
                'current_page' => $claims->currentPage(),
                'per_page'     => $claims->perPage(),
                'total'        => $claims->total(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/payroll/reimbursements
     * Pengajuan klaim reimbursement baru
     */
    public function storeReimbursement(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'required|integer',
            'claim_type'    => 'required|string',
            'amount'        => 'required|numeric|min:0',
            'description'   => 'required|string',
            'receipt_file'  => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'employee_name' => 'nullable|string',
            'employee_nik'  => 'nullable|string',
        ]);

        $receiptUrl = null;
        if ($request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads/reimbursements', $filename, 'public');
            $receiptUrl = '/storage/' . $path;
        }

        // Search user info
        $user = PresensiUser::find($validated['user_id']);
        $employeeName = $validated['employee_name'] ?? ($user ? $user->name : 'Employee #' . $validated['user_id']);
        $employeeNik = $validated['employee_nik'] ?? ($user ? ($user->nik ?? 'NIK-' . $validated['user_id']) : 'NIK-' . $validated['user_id']);

        $claim = Reimbursement::create([
            'user_id'                => $validated['user_id'],
            'employee_name'          => $employeeName,
            'employee_nik'           => $employeeNik,
            'claim_type'             => $validated['claim_type'],
            'description'            => $validated['description'],
            'amount'                 => $validated['amount'],
            'receipt_attachment_url' => $receiptUrl,
            'status'                 => 'pending',
            'submitted_at'           => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan reimbursement berhasil didaftarkan',
            'data'    => [
                'id'     => (int) $claim->id,
                'status' => $claim->status,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/hris/payroll/reimbursements/{id}/approve
     * Persetujuan klaim reimbursement
     */
    public function approveReimbursement(Request $request, $id)
    {
        $claim = Reimbursement::find($id);
        if (!$claim) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Klaim reimbursement tidak ditemukan',
            ], 404);
        }

        $user = auth('api')->user();
        $approverName = $user ? ($user->name ?? 'HRD Manager') : 'HRD Manager';

        $claim->status = 'approved';
        $claim->approved_by = $approverName;
        $claim->approved_at = now();
        $claim->rejection_reason = null;
        $claim->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Klaim reimbursement berhasil disetujui',
            'data'    => [
                'id'     => (int) $claim->id,
                'status' => $claim->status,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/payroll/reimbursements/{id}/reject
     * Penolakan klaim reimbursement
     */
    public function rejectReimbursement(Request $request, $id)
    {
        $claim = Reimbursement::find($id);
        if (!$claim) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Klaim reimbursement tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $claim->status = 'rejected';
        $claim->rejection_reason = $validated['rejection_reason'];
        $claim->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Klaim reimbursement telah ditolak',
            'data'    => [
                'id'     => (int) $claim->id,
                'status' => $claim->status,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/payroll/calculate
     * Automated Payroll Calculation Engine for all active employees
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'period' => 'required|string',
            'mode'   => 'nullable|string|in:all,new_only',
            'commit' => 'nullable|boolean',
        ]);

        $periodParam = $validated['period'];
        if (strlen($periodParam) === 7) {
            $period = $periodParam . '-01';
        } else {
            $period = $periodParam;
        }

        $mode   = $validated['mode'] ?? 'all';
        $commit = filter_var($request->input('commit', true), FILTER_VALIDATE_BOOLEAN);

        // Fetch active employees from master.m_karyawan or Employee model
        try {
            $employees = DB::table('master.m_karyawan as k')
                ->leftJoin('master.m_title as t', 't.title_code', '=', 'k.title')
                ->leftJoin('master.m_division as dv', 'dv.div_code', '=', 'k.div_id')
                ->whereRaw("(k.aktif = 'Y' OR k.aktif = '1' OR k.aktif IS NULL)")
                ->select(
                    'k.id',
                    'k.nama_karyawan as name',
                    'k.nik',
                    DB::raw("COALESCE(t.title, k.title, 'Staff') as position"),
                    'dv.div_name as division',
                    'k.gaji_pokok'
                )
                ->get();
        } catch (\Exception $e) {
            $employees = collect();
        }

        if ($employees->isEmpty()) {
            try {
                $employees = Employee::where('aktif', 'Y')->get()->map(function ($e) {
                    return (object)[
                        'id'         => $e->id,
                        'name'       => $e->nama_karyawan ?? $e->nama,
                        'nik'        => $e->nik ?? ('EMP-' . str_pad($e->id, 3, '0', STR_PAD_LEFT)),
                        'position'   => (isset($e->titleRelation) && $e->titleRelation ? $e->titleRelation->title : ($e->title ?? 'Staff')),
                        'division'   => 'Operations',
                        'gaji_pokok' => $e->gaji_pokok ?? 4500000,
                    ];
                });
            } catch (\Exception $e) {
                $employees = collect();
            }
        }

        // Existing slips for new_only filter
        $existingUserIds = SalarySlip::where('period', $period)->pluck('user_id')->toArray();

        $totalEmployees        = 0;
        $totalGross            = 0;
        $totalDeductions       = 0;
        $totalNet              = 0;
        $totalOvertimeHours    = 0;
        $totalOvertimeAmount   = 0;
        $totalAbsenceDays      = 0;
        $totalAbsenceDeduction = 0;
        $totalLoanDeduction    = 0;
        $totalBpjs             = 0;
        $totalTax              = 0;

        $items = [];

        foreach ($employees as $emp) {
            $empId = $emp->id;

            if ($mode === 'new_only' && in_array($empId, $existingUserIds)) {
                continue;
            }

            $basicSalary = (float) ($emp->gaji_pokok ?? 4800000);
            if ($basicSalary <= 0) {
                $basicSalary = 4800000;
            }

            // Tunjangan Default
            $profAllowance   = 0;
            $perfAllowance   = 0;
            $posAllowance    = 500000;
            $mealAllowance   = 300000;
            $transAllowance  = 200000;
            $relocAllowance  = 0;
            $skillAllowance  = 0;
            $otherAllowance  = 0;
            $shiftAllowance  = 150000;
            $shiftCount      = 10;

            // Overtime SPKL (Jam Lembur x 1/173 x Gaji Pokok x 1.5)
            $otHours  = 18;
            $otHourly = round((1 / 173) * $basicSalary * 1.5, 2);
            $otAmount = round($otHours * $otHourly, 2);

            $grossSalary = $basicSalary + $profAllowance + $perfAllowance + $posAllowance + $mealAllowance + $transAllowance + $shiftAllowance + $otAmount;

            // Absence Deduction (Hari Alpa x Gaji Pokok / 25)
            $absenceDays      = 0;
            $absenceDeduction = round($absenceDays * ($basicSalary / 25), 2);

            // Loan Deduction (from presensi.loans)
            try {
                $activeLoan = DB::table('presensi.loans')
                    ->where('user_id', $empId)
                    ->whereIn('status', ['approved', 'active', 'disbursed'])
                    ->first();
                $loanDeduction = $activeLoan ? (float) $activeLoan->monthly_installment : 0;
            } catch (\Exception $e) {
                $loanDeduction = 0;
            }

            // BPJS Karyawan: Kes (1% max cap 12m), JHT (2%), JP (1% max cap 10.042m)
            $bpjsHealth = round(min($basicSalary, 12000000) * 0.01, 2);
            $bpjsJht    = round($basicSalary * 0.02, 2);
            $bpjsJp     = round(min($basicSalary, 10042300) * 0.01, 2);
            $bpjsTotal  = $bpjsHealth + $bpjsJht + $bpjsJp;

            // PPh 21 TER (Pajak Efektif 2.5%)
            $taxAmount = round($grossSalary * 0.025, 2);
            $unionFee  = 10000;

            $sumDeductions = $bpjsTotal + $taxAmount + $loanDeduction + $absenceDeduction + $unionFee;
            $netSalary     = max(0, round($grossSalary - $sumDeductions, 2));

            // Aggregations
            $totalEmployees++;
            $totalGross            += $grossSalary;
            $totalDeductions       += $sumDeductions;
            $totalNet              += $netSalary;
            $totalOvertimeHours    += $otHours;
            $totalOvertimeAmount   += $otAmount;
            $totalAbsenceDays      += $absenceDays;
            $totalAbsenceDeduction += $absenceDeduction;
            $totalLoanDeduction    += $loanDeduction;
            $totalBpjs             += $bpjsTotal;
            $totalTax              += $taxAmount;

            $items[] = [
                'user_id'                 => $empId,
                'employee_nik'            => $emp->nik ?? ('EMP-' . str_pad($empId, 3, '0', STR_PAD_LEFT)),
                'employee_name'           => $emp->name ?? 'Staff',
                'employee_position'       => $emp->position ?? 'Staff',
                'employee_division'       => $emp->division ?? 'Operations',
                'bank_name'               => 'BCA',
                'account_number'          => '1234567890',
                'work_days'               => 24,
                'basic_salary'            => $basicSalary,
                'professional_allowance'  => $profAllowance,
                'performance_allowance'   => $perfAllowance,
                'position_allowance'      => $posAllowance,
                'meal_allowance'          => $mealAllowance,
                'transport_allowance'     => $transAllowance,
                'relocation_allowance'   => $relocAllowance,
                'skill_allowance'        => $skillAllowance,
                'other_allowance'        => $otherAllowance,
                'incentive_10th'          => 0,
                'communication_allowance' => 0,
                'incentive'               => 0,
                'shift_allowance'         => $shiftAllowance,
                'shift_count'             => $shiftCount,
                'overtime_allowance'      => $otAmount,
                'overtime_hours'          => $otHours,
                'khk_allowance'           => 0,
                'khk_count'               => 0,
                'zakat'                   => 0,
                'tax'                     => $taxAmount,
                'bpjs'                    => $bpjsTotal,
                'union_fee'               => $unionFee,
                'absence_deduction'       => $absenceDeduction,
                'absence_days'            => $absenceDays,
                'cooperative'             => 0,
                'bpr_installment'         => 0,
                'other_deduction'         => 0,
                'gross_salary'            => $grossSalary,
                'total_deductions'        => $sumDeductions,
                'net_salary'              => $netSalary,
            ];

            if ($commit) {
                SalarySlip::updateOrCreate(
                    [
                        'user_id' => $empId,
                        'period'  => $period,
                    ],
                    [
                        'employee_nik'            => $emp->nik ?? ('EMP-' . str_pad($empId, 3, '0', STR_PAD_LEFT)),
                        'employee_name'           => $emp->name ?? 'Staff',
                        'employee_division'       => $emp->division ?? 'Operations',
                        'employee_position'       => $emp->position ?? 'Staff',
                        'basic_salary'            => $basicSalary,
                        'professional_allowance'  => $profAllowance,
                        'performance_allowance'   => $perfAllowance,
                        'position_allowance'      => $posAllowance,
                        'meal_allowance'          => $mealAllowance,
                        'transport_allowance'     => $transAllowance,
                        'overtime_amount'         => $otAmount,
                        'gross_salary'            => $grossSalary,
                        'bpjs_kesehatan_employee' => $bpjsHealth,
                        'bpjs_jht_employee'       => $bpjsJht,
                        'bpjs_jp_employee'        => $bpjsJp,
                        'pph21_tax'               => $taxAmount,
                        'loan_deduction'          => $loanDeduction,
                        'absence_deduction'       => $absenceDeduction,
                        'total_deductions'        => $sumDeductions,
                        'net_salary'              => $netSalary,
                    ]
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'period'                  => $period,
                'total_employees'         => $totalEmployees,
                'total_gross'             => round($totalGross, 2),
                'total_deductions'        => round($totalDeductions, 2),
                'total_net'               => round($totalNet, 2),
                'total_overtime_hours'    => round($totalOvertimeHours, 1),
                'total_overtime_amount'   => round($totalOvertimeAmount, 2),
                'total_absence_days'      => $totalAbsenceDays,
                'total_absence_deduction' => round($totalAbsenceDeduction, 2),
                'total_loan_deduction'    => round($totalLoanDeduction, 2),
                'total_bpjs'              => round($totalBpjs, 2),
                'total_tax'               => round($totalTax, 2),
                'items'                   => $items,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/payroll/commit
     * Eksekusi & Kunci Slip Gaji ke database
     */
    public function commit(Request $request)
    {
        $validated = $request->validate([
            'period' => 'required|string',
            'mode'   => 'nullable|string|in:all,new_only',
        ]);

        // Force commit to true
        $request->merge(['commit' => true]);
        $response = $this->calculate($request);
        $resData = $response->getData(true);

        if (($resData['status'] ?? '') === 'success') {
            $dataObj = $resData['data'] ?? [];
            $periodFormatted = Carbon::parse($validated['period'])->format('Y-m');

            return response()->json([
                'status'  => 'success',
                'message' => "Payroll periode {$periodFormatted} berhasil dihitung dan dikunci ke database.",
                'data'    => [
                    'slips_generated' => $dataObj['total_employees'] ?? 0,
                    'total_amount'    => $dataObj['total_net'] ?? 0,
                ],
            ], 200);
        }

        return $response;
    }
}
