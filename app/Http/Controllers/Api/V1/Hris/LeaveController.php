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

        $perPage = $request->get('limit', 10);
        $leaves = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($leaves);
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
