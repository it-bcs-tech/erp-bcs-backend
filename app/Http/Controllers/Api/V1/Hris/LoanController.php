<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\PresensiUser;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/payroll/loans
     * Ringkasan statistik & daftar pinjaman karyawan
     */
    public function index(Request $request)
    {
        $status  = $request->query('status', 'all');
        $search  = $request->query('search');
        $perPage = (int) $request->query('per_page', 50);

        // Summary Statistics
        $totalLoans     = Loan::count();
        $totalAmount    = (float) Loan::sum('amount');
        $totalRemaining = (float) Loan::sum('remaining_amount');
        $activeLoans    = Loan::whereIn('status', ['approved', 'active'])->count();
        $pendingLoans   = Loan::where('status', 'pending_approval')->count();

        // Query
        $query = Loan::with('user:id,name,email');

        if ($status && $status !== 'all') {
            if ($status === 'pending') {
                $query->where('status', 'pending_approval');
            } elseif ($status === 'active') {
                $query->whereIn('status', ['approved', 'active']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason_detail', 'LIKE', "%{$search}%")
                  ->orWhere('reason', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uQuery) use ($search) {
                      $uQuery->where('name', 'LIKE', "%{$search}%")
                             ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        $formattedLoans = collect($paginated->items())->map(function ($loan) {
            $user = $loan->user;
            return [
                'id'                  => (int) $loan->id,
                'user_id'             => (int) $loan->user_id,
                'employee_name'       => $user ? $user->name : 'Employee #' . $loan->user_id,
                'email'               => $user ? $user->email : '',
                'amount'              => (float) $loan->amount,
                'tenor_months'        => (int) $loan->tenor_months,
                'monthly_installment' => (float) $loan->monthly_installment,
                'total_repayment'     => (float) $loan->total_repayment,
                'remaining_amount'    => (float) $loan->remaining_amount,
                'reason'              => $loan->reason,
                'reason_detail'       => $loan->reason_detail,
                'status'              => $loan->status,
                'bank_name'           => $loan->bank_name,
                'bank_account_number' => $loan->bank_account_number,
                'request_date'        => $loan->created_at ? $loan->created_at->format('Y-m-d') : null,
                'start_date'          => $loan->start_date ? Carbon::parse($loan->start_date)->format('Y-m-d') : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'total_loans'     => $totalLoans,
                    'total_amount'    => $totalAmount,
                    'total_remaining' => $totalRemaining,
                    'active_loans'    => $activeLoans,
                    'pending_loans'   => $pendingLoans,
                ],
                'loans'   => $formattedLoans,
            ],
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/payroll/loans
     * Pengajuan kasbon / pinjaman karyawan baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'             => 'required|integer',
            'amount'              => 'required|numeric|min:100000',
            'tenor_months'        => 'required|integer|min:1|max:60',
            'reason'              => 'required|string',
            'reason_detail'       => 'nullable|string',
            'bank_name'           => 'nullable|string',
            'bank_account_number' => 'nullable|string',
        ]);

        $amount      = (float) $validated['amount'];
        $tenorMonths = (int) $validated['tenor_months'];

        // Automatic backend calculations
        $interestRatePercent     = 1.00;
        $interestAmountPerMonth  = round($amount * 0.01, 2);
        $adminFee                = 25000.00;
        $monthlyInstallment      = round(($amount / $tenorMonths) + $interestAmountPerMonth, 2);
        $totalRepayment          = round($monthlyInstallment * $tenorMonths, 2);
        $disbursementAmount      = round($amount - $adminFee, 2);
        $remainingAmount         = $totalRepayment;

        $loan = Loan::create([
            'user_id'                   => $validated['user_id'],
            'amount'                    => $amount,
            'tenor_months'              => $tenorMonths,
            'interest_rate_percent'     => $interestRatePercent,
            'interest_amount_per_month' => $interestAmountPerMonth,
            'admin_fee'                 => $adminFee,
            'monthly_installment'       => $monthlyInstallment,
            'total_repayment'           => $totalRepayment,
            'disbursement_amount'       => $disbursementAmount,
            'remaining_amount'          => $remainingAmount,
            'reason'                    => $validated['reason'],
            'reason_detail'             => $validated['reason_detail'] ?? null,
            'bank_name'                 => $validated['bank_name'] ?? null,
            'bank_account_number'       => $validated['bank_account_number'] ?? null,
            'status'                    => 'pending_approval',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan pinjaman berhasil dibuat',
            'data'    => [
                'id'     => (int) $loan->id,
                'status' => $loan->status,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/hris/payroll/loans/{id}/approve
     * Persetujuan pinjaman oleh HRD / Finance Manager
     */
    public function approve(Request $request, $id)
    {
        $loan = Loan::find($id);
        if (!$loan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pinjaman tidak ditemukan',
            ], 404);
        }

        $user = auth('api')->user();
        $approverName = $user ? ($user->name ?? 'HRD / Finance Manager') : 'HRD / Finance Manager';

        $startDate = Carbon::now()->addMonth()->startOfMonth();
        $endDate   = (clone $startDate)->addMonths($loan->tenor_months)->subDay();

        $loan->status            = 'approved';
        $loan->approved_by       = $approverName;
        $loan->approved_at       = now();
        $loan->disbursement_date = now();
        $loan->start_date        = $startDate;
        $loan->end_date          = $endDate;
        $loan->rejection_reason  = null;
        $loan->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pinjaman berhasil disetujui',
            'data'    => [
                'id'     => (int) $loan->id,
                'status' => $loan->status,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/payroll/loans/{id}/reject
     * Penolakan pengajuan pinjaman
     */
    public function reject(Request $request, $id)
    {
        $loan = Loan::find($id);
        if (!$loan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pinjaman tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $loan->status           = 'rejected';
        $loan->rejection_reason = $validated['rejection_reason'];
        $loan->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan pinjaman ditolak',
            'data'    => [
                'id'     => (int) $loan->id,
                'status' => $loan->status,
            ],
        ], 200);
    }
}
