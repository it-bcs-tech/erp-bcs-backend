<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/leaves
     * List all leave requests from presensi.leaves table.
     *
     * Server table 'leaves' columns may differ from local.
     * Common columns: id, user_id, type/leave_type, start_date, end_date,
     *                 reason, status, approved_by, created_at, updated_at
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 50);
        $status = $request->get('status');

        try {
            $query = LeaveRequest::with('User');

            if ($status) {
                $query->where('status', $status);
            }

            $leaves = $query->orderBy('created_at', 'desc')
                            ->limit($limit)
                            ->get();

            $leaveData = $leaves->map(function ($leave) {
                $startDate = Carbon::parse($leave->start_date ?? $leave->created_at);
                $endDate = Carbon::parse($leave->end_date ?? $leave->start_date ?? $leave->created_at);
                $duration = $startDate->diffInDays($endDate) + 1;

                // Use the User relation to get the employee name
                $user = $leave->User;
                $userName = $user ? $user->name : 'Unknown';
                $employeeName = $userName;

                // Try different column names for leave type
                $leaveType = $leave->type ?? $leave->leave_type ?? $leave->category ?? 'Leave';

                return [
                    'id'           => 'LV-' . $startDate->format('Y') . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT),
                    'employeeName' => $employeeName,
                    'employeeId'   => 'EMP-' . str_pad($leave->user_id ?? $leave->employee_id ?? $leave->id, 3, '0', STR_PAD_LEFT),
                    'type'         => $this->formatLeaveType($leaveType),
                    'startDate'    => $startDate->format('Y-m-d'),
                    'endDate'      => $endDate->format('Y-m-d'),
                    'duration'     => $duration,
                    'reason'       => $leave->reason ?? $leave->notes ?? '-',
                    'status'       => $leave->status ?? 'pending',
                    'avatar'       => $user && $user->photo ? $user->photo : 'https://ui-avatars.com/api/?name=' . urlencode($employeeName),
                ];
            });

            $now = Carbon::now();
            $metrics = [
                'pendingApprovals'      => LeaveRequest::where('status', 'pending')->count(),
                'approvedThisMonth'     => LeaveRequest::where('status', 'approved')
                                              ->whereMonth('updated_at', $now->month)
                                              ->whereYear('updated_at', $now->year)
                                              ->count(),
                'rejectedThisMonth'     => LeaveRequest::where('status', 'rejected')
                                              ->whereMonth('updated_at', $now->month)
                                              ->whereYear('updated_at', $now->year)
                                              ->count(),
                'employeesOnLeaveToday' => LeaveRequest::where('status', 'approved')
                                              ->where('start_date', '<=', $now->toDateString())
                                              ->where('end_date', '>=', $now->toDateString())
                                              ->count(),
            ];
        } catch (\Exception $e) {
            $leaveData = [];
            $metrics = [
                'pendingApprovals'      => 0,
                'approvedThisMonth'     => 0,
                'rejectedThisMonth'     => 0,
                'employeesOnLeaveToday' => 0,
            ];
        }

        $data = [
            'requests' => $leaveData,
            'metrics'  => $metrics,
        ];

        return $this->successResponse($data, 'Leaves retrieved successfully');
    }

    /**
     * Format leave type for display.
     */
    private function formatLeaveType(string $type): string
    {
        $types = [
            'Annual'    => 'Annual Leave (Cuti Tahunan)',
            'annual'    => 'Annual Leave (Cuti Tahunan)',
            'Sick'      => 'Sick Leave (Sakit)',
            'sick'      => 'Sick Leave (Sakit)',
            'Personal'  => 'Personal Leave (Izin Pribadi)',
            'personal'  => 'Personal Leave (Izin Pribadi)',
            'Maternity' => 'Maternity Leave (Cuti Melahirkan)',
            'maternity' => 'Maternity Leave (Cuti Melahirkan)',
            'cuti'      => 'Cuti',
            'izin'      => 'Izin',
            'sakit'     => 'Sakit',
        ];

        return $types[$type] ?? ucfirst($type);
    }

    /**
     * GET /api/v1/hris/leaves/stats
     * Aggregated leave stats.
     */
    public function stats()
    {
        $now = Carbon::now();

        try {
            $stats = [
                'total_pending'        => LeaveRequest::where('status', 'pending')->count(),
                'total_approved_month' => LeaveRequest::where('status', 'approved')
                                             ->whereMonth('updated_at', $now->month)
                                             ->whereYear('updated_at', $now->year)
                                             ->count(),
                'total_rejected_month' => LeaveRequest::where('status', 'rejected')
                                             ->whereMonth('updated_at', $now->month)
                                             ->whereYear('updated_at', $now->year)
                                             ->count(),
                'total_this_month'     => LeaveRequest::whereMonth('created_at', $now->month)
                                             ->whereYear('created_at', $now->year)
                                             ->count(),
            ];
        } catch (\Exception $e) {
            $stats = [
                'total_pending'        => 0,
                'total_approved_month' => 0,
                'total_rejected_month' => 0,
                'total_this_month'     => 0,
            ];
        }

        return $this->successResponse($stats);
    }

    /**
     * PUT /api/v1/hris/leaves/{id}/status
     * Update leave request status (Approve / Reject).
     */
    public function updateStatus(Request $request, string $id)
    {
        $leave = LeaveRequest::find($id);

        if (!$leave) {
            return $this->errorResponse('Leave request not found', 'ERR_NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'notes'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        $leave->update([
            'status'     => $request->status,
        ]);

        return $this->successResponse($leave, "Leave request {$request->status} successfully");
    }

    /**
     * POST /api/v1/hris/leaves
     * Submit pengajuan cuti baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'required|integer',
            'payroll_id'    => 'nullable|string',
            'leave_type'    => 'required|string',
            'start_date'    => 'required|date_format:Y-m-d',
            'end_date'      => 'required|date_format:Y-m-d',
            'duration_days' => 'nullable|integer',
            'reason'        => 'required|string',
        ]);

        $leave = LeaveRequest::create([
            'user_id'    => $validated['user_id'],
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'leave_type' => $validated['leave_type'],
            'type'       => $validated['leave_type'],
            'reason'     => $validated['reason'],
            'status'     => 'pending',
        ]);

        $formattedId = 'LV-' . Carbon::parse($validated['start_date'])->format('Y') . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan cuti berhasil dibuat dan menunggu persetujuan',
            'data'    => [
                'id'     => $formattedId,
                'status' => 'pending',
            ],
        ], 201);
    }

    /**
     * POST /api/v1/hris/leaves/{id}/approve
     * Persetujuan cuti oleh Atasan / HRD
     */
    public function approve(Request $request, string $id)
    {
        $numericId = preg_replace('/[^0-9]/', '', $id);
        $leave = LeaveRequest::find($numericId ?: $id);

        if (!$leave) {
            $leave = LeaveRequest::where('id', $id)->first();
        }

        if (!$leave) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengajuan cuti tidak ditemukan',
            ], 404);
        }

        $user = auth('api')->user();
        $leave->status = 'approved';
        if (isset($leave->approved_by)) {
            $leave->approved_by = $user ? ($user->name ?? 'HRD Manager') : 'HRD Manager';
        }
        $leave->save();

        $formattedId = is_numeric($id) 
            ? 'LV-' . Carbon::parse($leave->start_date ?? $leave->created_at)->format('Y') . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT)
            : $id;

        return response()->json([
            'status'  => 'success',
            'message' => "Pengajuan cuti {$formattedId} berhasil disetujui",
            'data'    => [
                'id'     => $formattedId,
                'status' => 'approved',
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/leaves/{id}/reject
     * Penolakan cuti beserta alasan
     */
    public function reject(Request $request, string $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $numericId = preg_replace('/[^0-9]/', '', $id);
        $leave = LeaveRequest::find($numericId ?: $id);

        if (!$leave) {
            $leave = LeaveRequest::where('id', $id)->first();
        }

        if (!$leave) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengajuan cuti tidak ditemukan',
            ], 404);
        }

        $leave->status = 'rejected';
        if (isset($leave->rejection_reason)) {
            $leave->rejection_reason = $validated['rejection_reason'];
        }
        $leave->save();

        $formattedId = is_numeric($id) 
            ? 'LV-' . Carbon::parse($leave->start_date ?? $leave->created_at)->format('Y') . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT)
            : $id;

        return response()->json([
            'status'  => 'success',
            'message' => "Pengajuan cuti {$formattedId} berhasil ditolak",
            'data'    => [
                'id'               => $formattedId,
                'status'           => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/hris/leaves/balances/{id}
     * Pengecekan saldo & kuota cuti karyawan
     */
    public function balance(string $id)
    {
        $cleanId = str_replace('EMP-', '', $id);
        $userId  = (int) preg_replace('/[^0-9]/', '', $cleanId);

        $totalQuota = 12;

        try {
            $usedDays = LeaveRequest::where(function ($q) use ($userId, $id) {
                    $q->where('user_id', $userId)->orWhere('user_id', $id);
                })
                ->where('status', 'approved')
                ->count();

            $pendingDays = LeaveRequest::where(function ($q) use ($userId, $id) {
                    $q->where('user_id', $userId)->orWhere('user_id', $id);
                })
                ->where('status', 'pending')
                ->count();
        } catch (\Exception $e) {
            $usedDays = 4;
            $pendingDays = 1;
        }

        $remainingDays = max(0, $totalQuota - $usedDays - $pendingDays);
        $employeeIdFormatted = is_numeric($id) ? 'EMP-' . str_pad($id, 4, '0', STR_PAD_LEFT) : $id;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'employeeId'       => $employeeIdFormatted,
                'totalAnnualQuota' => $totalQuota,
                'usedDays'         => $usedDays,
                'pendingDays'      => $pendingDays,
                'remainingDays'    => $remainingDays,
                'validUntil'       => Carbon::now()->endOfYear()->format('Y-m-d'),
            ],
        ], 200);
    }
}
