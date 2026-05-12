<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LeaveRequest;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/leaves
     * List all leave requests with filters.
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 50);
        $status = $request->get('status');

        $query = LeaveRequest::with('employee:id,name,email,role,avatar');

        if ($status) {
            $query->where('status', $status);
        }

        $leaveData = $query->orderBy('created_at', 'desc')
                           ->limit($limit)
                           ->get()
                           ->map(function ($leave) {
                               $employee = $leave->employee;
                               $startDate = \Carbon\Carbon::parse($leave->start_date);
                               $endDate = \Carbon\Carbon::parse($leave->end_date);
                               $duration = $startDate->diffInDays($endDate) + 1;

                               return [
                                   'id'           => 'LV-' . $startDate->format('Y') . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT),
                                   'employeeName' => $employee ? ($employee->name ?? 'Unknown') : 'Unknown',
                                   'employeeId'   => $employee ? 'EMP-' . str_pad($employee->id, 3, '0', STR_PAD_LEFT) : 'Unknown',
                                   'type'         => $this->formatLeaveType($leave->type),
                                   'startDate'    => $startDate->format('Y-m-d'),
                                   'endDate'      => $endDate->format('Y-m-d'),
                                   'duration'     => $duration,
                                   'reason'       => $leave->reason ?? '-',
                                   'status'       => $leave->status,
                                   'avatar'       => $employee && $employee->avatar
                                       ? $employee->avatar
                                       : 'https://ui-avatars.com/api/?name=' . urlencode($employee ? ($employee->name ?? 'User') : 'User'),
                               ];
                           });

        $now = Carbon::now();
        $metrics = [
            'pendingApprovals'      => LeaveRequest::where('status', 'Pending')->count(),
            'approvedThisMonth'     => LeaveRequest::where('status', 'Approved')
                                          ->whereMonth('updated_at', $now->month)
                                          ->whereYear('updated_at', $now->year)
                                          ->count(),
            'rejectedThisMonth'     => LeaveRequest::where('status', 'Rejected')
                                          ->whereMonth('updated_at', $now->month)
                                          ->whereYear('updated_at', $now->year)
                                          ->count(),
            'employeesOnLeaveToday' => LeaveRequest::where('status', 'Approved')
                                          ->where('start_date', '<=', $now->toDateString())
                                          ->where('end_date', '>=', $now->toDateString())
                                          ->count(),
        ];

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
            'Annual'   => 'Annual Leave (Cuti Tahunan)',
            'Sick'     => 'Sick Leave (Sakit)',
            'Personal' => 'Personal Leave (Izin Pribadi)',
            'Maternity'=> 'Maternity Leave (Cuti Melahirkan)',
        ];

        return $types[$type] ?? $type;
    }

    /**
     * GET /api/v1/hris/leaves/stats
     * Aggregated leave stats.
     */
    public function stats()
    {
        $now = Carbon::now();

        $stats = [
            'total_pending'        => LeaveRequest::where('status', 'Pending')->count(),
            'total_approved_month' => LeaveRequest::where('status', 'Approved')
                                        ->whereMonth('updated_at', $now->month)
                                        ->whereYear('updated_at', $now->year)
                                        ->count(),
            'total_rejected_month' => LeaveRequest::where('status', 'Rejected')
                                        ->whereMonth('updated_at', $now->month)
                                        ->whereYear('updated_at', $now->year)
                                        ->count(),
            'total_this_month'     => LeaveRequest::whereMonth('created_at', $now->month)
                                        ->whereYear('created_at', $now->year)
                                        ->count(),
        ];

        return $this->successResponse($stats);
    }

    /**
     * PUT /api/v1/hris/leaves/{id}/status
     * Update leave request status (Approve / Reject).
     */
    public function updateStatus(Request $request, string $id)
    {
        $leave = LeaveRequest::with('employee:id,name')->find($id);

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

        $leave->update([
            'status' => $request->status,
            'notes'  => $request->notes,
        ]);

        // Log the activity
        $action = $request->status === 'Approved' ? 'approved' : 'rejected';
        ActivityLog::create([
            'type'        => "leave_{$action}",
            'description' => "{$leave->employee->name}'s {$leave->type} leave has been {$action}",
            'employee_id' => $leave->employee_id,
            'metadata'    => [
                'leave_id'   => $leave->id,
                'leave_type' => $leave->type,
                'start_date' => $leave->start_date->toDateString(),
                'end_date'   => $leave->end_date->toDateString(),
            ],
        ]);

        // Deduct leave balance if approved
        if ($request->status === 'Approved' && $leave->employee) {
            $days = $leave->start_date->diffInDays($leave->end_date) + 1;
            $leave->employee->decrement('leave_balance', $days);
        }

        return $this->successResponse($leave, "Leave request {$action} successfully");
    }
}
