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
            $query = DB::connection('pgsql')->table('leaves');

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

                // Try to get employee name from user relation or properties
                $employeeName = $leave->employee_name ?? $leave->user_name ?? 'Employee';

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
                    'status'       => $leave->status ?? 'Pending',
                    'avatar'       => 'https://ui-avatars.com/api/?name=' . urlencode($employeeName),
                ];
            });

            $now = Carbon::now();
            $metrics = [
                'pendingApprovals'      => DB::connection('pgsql')->table('leaves')->where('status', 'Pending')->count(),
                'approvedThisMonth'     => DB::connection('pgsql')->table('leaves')
                                              ->where('status', 'Approved')
                                              ->whereMonth('updated_at', $now->month)
                                              ->whereYear('updated_at', $now->year)
                                              ->count(),
                'rejectedThisMonth'     => DB::connection('pgsql')->table('leaves')
                                              ->where('status', 'Rejected')
                                              ->whereMonth('updated_at', $now->month)
                                              ->whereYear('updated_at', $now->year)
                                              ->count(),
                'employeesOnLeaveToday' => DB::connection('pgsql')->table('leaves')
                                              ->where('status', 'Approved')
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
                'total_pending'        => DB::connection('pgsql')->table('leaves')->where('status', 'Pending')->count(),
                'total_approved_month' => DB::connection('pgsql')->table('leaves')
                                             ->where('status', 'Approved')
                                             ->whereMonth('updated_at', $now->month)
                                             ->whereYear('updated_at', $now->year)
                                             ->count(),
                'total_rejected_month' => DB::connection('pgsql')->table('leaves')
                                             ->where('status', 'Rejected')
                                             ->whereMonth('updated_at', $now->month)
                                             ->whereYear('updated_at', $now->year)
                                             ->count(),
                'total_this_month'     => DB::connection('pgsql')->table('leaves')
                                             ->whereMonth('created_at', $now->month)
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
        $leave = DB::connection('pgsql')->table('leaves')->find($id);

        if (!$leave) {
            return $this->errorResponse('Leave request not found', 'ERR_NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Approved,Rejected',
            'notes'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        DB::connection('pgsql')->table('leaves')
            ->where('id', $id)
            ->update([
                'status'     => $request->status,
                'updated_at' => now(),
            ]);

        return $this->successResponse($leave, "Leave request {$request->status} successfully");
    }
}
