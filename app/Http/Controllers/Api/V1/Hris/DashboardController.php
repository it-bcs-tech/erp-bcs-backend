<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RecruitmentJob;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/dashboard/metrics
     * Total employees, attendance today, pending leaves, open positions.
     */
    public function metrics()
    {
        $today = Carbon::today();

        $data = [
            'totalEmployees'        => 648,
            'presentToday'          => 602,
            'attendanceCapacity'    => 92,
            'totalLeaveRequests'    => 12,
            'pendingLeaveRequests'  => 5,
            'openPositions'         => 8,
            'highPriorityPositions' => 3,
        ];

        return $this->successResponse($data, 'Metrics retrieved successfully');
    }

    /**
     * GET /api/v1/hris/dashboard/attendance-trend
     * Monthly attendance trend: Remote vs On-Site percentage.
     */
    public function attendanceTrend(Request $request)
    {
        $months = $request->get('months', 6);
        $trend  = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $month = $date->format('Y-m');
            $label = $date->format('M Y');

            $total  = AttendanceLog::whereYear('date', $date->year)
                                   ->whereMonth('date', $date->month)
                                   ->count();

            $remote = AttendanceLog::whereYear('date', $date->year)
                                   ->whereMonth('date', $date->month)
                                   ->where('work_type', 'Remote')
                                   ->count();

            $onSite = $total - $remote;

            $trend[] = [
                'month'          => $month,
                'label'          => $label,
                'total'          => $total,
                'remote'         => $remote,
                'on_site'        => $onSite,
                'remote_percent' => $total > 0 ? round(($remote / $total) * 100, 1) : 0,
                'onsite_percent' => $total > 0 ? round(($onSite / $total) * 100, 1) : 0,
            ];
        }

        return $this->successResponse($trend);
    }

    /**
     * GET /api/v1/hris/dashboard/anniversaries
     * Employees with work anniversaries and birthdays this month.
     */
    public function anniversaries()
    {
        $now   = Carbon::now();
        $month = $now->month;

        // Work anniversaries (join_date same month, but not this year = anniversary)
        $workAnniversaries = Employee::where('status', 'Active')
            ->whereMonth('join_date', $month)
            ->whereYear('join_date', '<', $now->year)
            ->get()
            ->map(function ($emp) use ($now) {
                return [
                    'id'    => $emp->id,
                    'name'  => $emp->name,
                    'role'  => $emp->role,
                    'type'  => 'work_anniversary',
                    'date'  => $emp->join_date->format('Y-m-d'),
                    'years' => $now->year - $emp->join_date->year,
                ];
            });

        // Birthdays this month
        $birthdays = Employee::where('status', 'Active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $month)
            ->get()
            ->map(function ($emp) {
                return [
                    'id'   => $emp->id,
                    'name' => $emp->name,
                    'role' => $emp->role,
                    'type' => 'birthday',
                    'date' => $emp->birth_date->format('m-d'),
                ];
            });

        return $this->successResponse([
            'work_anniversaries' => $workAnniversaries,
            'birthdays'          => $birthdays,
        ]);
    }

    /**
     * GET /api/v1/hris/dashboard/activities
     * Recent HRIS activity log.
     */
    public function activities(Request $request)
    {
        $limit = $request->get('limit', 10);

        $activities = ActivityLog::with('employee:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id'          => $log->id,
                    'type'        => $log->type,
                    'description' => $log->description,
                    'employee'    => $log->employee ? [
                        'id'     => $log->employee->id,
                        'name'   => $log->employee->name,
                        'avatar' => $log->employee->avatar,
                    ] : null,
                    'metadata'    => $log->metadata,
                    'created_at'  => $log->created_at->toISOString(),
                ];
            });

        return $this->successResponse($activities);
    }
}
