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
        $query = LeaveRequest::with('employee:id,name,role,avatar,department_id', 'employee.department:id,name');

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by type
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by employee
        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        $requests = $query->orderBy('created_at', 'desc')->get()->map(function ($leave) {
            $status = $leave->status;
            if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
                $status = 'Pending';
            }

            return [
                'id'           => 'LV-' . $leave->created_at->format('Y') . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT),
                'employeeName' => $leave->employee ? $leave->employee->name : 'Unknown',
                'employeeId'   => $leave->employee ? ($leave->employee->employee_code ?? 'EMP-' . str_pad($leave->employee->id, 3, '0', STR_PAD_LEFT)) : 'Unknown',
                'type'         => $leave->type ?? 'Annual Leave (Cuti Tahunan)',
                'startDate'    => $leave->start_date ? $leave->start_date->format('Y-m-d') : '2026-05-10',
                'endDate'      => $leave->end_date ? $leave->end_date->format('Y-m-d') : '2026-05-12',
                'duration'     => $leave->start_date && $leave->end_date ? $leave->start_date->diffInDays($leave->end_date) + 1 : 3,
                'reason'       => $leave->reason ?? 'Liburan Keluarga',
                'status'       => $status,
                'avatar'       => $leave->employee && $leave->employee->avatar ? $leave->employee->avatar : 'https://ui-avatars.com/api/?name=' . urlencode($leave->employee ? $leave->employee->name : 'User')
            ];
        });

        $now = Carbon::now();

        $metrics = [
            'pendingApprovals'      => LeaveRequest::where('status', 'Pending')->count(),
            'approvedThisMonth'     => LeaveRequest::where('status', 'Approved')->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count(),
            'rejectedThisMonth'     => LeaveRequest::where('status', 'Rejected')->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count(),
            'employeesOnLeaveToday' => LeaveRequest::where('status', 'Approved')->whereDate('start_date', '<=', $now->toDateString())->whereDate('end_date', '>=', $now->toDateString())->count(),
        ];

        $data = [
            'requests' => $requests,
            'metrics'  => $metrics,
        ];

        return $this->successResponse($data, 'Leaves retrieved successfully');
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
