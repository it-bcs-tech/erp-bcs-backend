<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\SalarySlip;
use App\Models\Reimbursement;
use App\Models\PresensiUser;
use App\Traits\ApiResponseTrait;
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
        $period   = $request->query('period', date('Y-m'));
        $search   = $request->query('search');
        $division = $request->query('division');
        $perPage  = (int) $request->query('per_page', 20);

        // Subquery summary per periode
        $summaryQuery = SalarySlip::where('period', $period);
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

        if ($period) {
            $query->where('period', $period);
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
}
