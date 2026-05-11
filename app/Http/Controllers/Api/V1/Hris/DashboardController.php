<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $totalEmployees = Employee::where('aktif', 'Y')->count();
        
        $presentToday = \App\Models\AttendanceLog::whereDate('date', $today->format('Y-m-d'))
                                                 ->whereIn('status', ['On Time', 'Late', 'Half Day'])
                                                 ->distinct('employee_id')
                                                 ->count('employee_id');

        $totalLeaveRequests = \App\Models\LeaveRequest::count();
        $pendingLeaveRequests = \App\Models\LeaveRequest::where('status', 'Pending')->count();

        $data = [
            'totalEmployees'        => $totalEmployees,
            'presentToday'          => $presentToday,
            'attendanceCapacity'    => $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 0,
            'totalLeaveRequests'    => $totalLeaveRequests,
            'pendingLeaveRequests'  => $pendingLeaveRequests,
            'openPositions'         => 0, // Mocked until recruitment is integrated
            'highPriorityPositions' => 0,
        ];

        return $this->successResponse($data, 'Metrics retrieved successfully');
    }

    /**
     * GET /api/v1/hris/dashboard/attendance-trend
     * Monthly attendance trend.
     */
    public function attendanceTrend(Request $request)
    {
        $months = $request->get('months', 6);
        $trend  = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $month = $date->format('Y-m');
            $label = $date->format('M Y');

            $total = \App\Models\AttendanceLog::whereYear('date', $date->year)
                                              ->whereMonth('date', $date->month)
                                              ->whereIn('status', ['On Time', 'Late', 'Half Day'])
                                              ->count();

            $remote = \App\Models\AttendanceLog::whereYear('date', $date->year)
                                               ->whereMonth('date', $date->month)
                                               ->where('work_type', 'Remote')
                                               ->count();
            
            $onSite = $total > 0 ? $total - $remote : 0;

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

        // Work anniversaries (tgl_masuk same month, but not this year = anniversary)
        $workAnniversaries = Employee::where('aktif', 'Y')
            ->whereNotNull('tgl_masuk')
            ->whereMonth('tgl_masuk', $month)
            ->whereYear('tgl_masuk', '<', $now->year)
            ->get()
            ->map(function ($emp) use ($now) {
                $joinDate = \Carbon\Carbon::parse($emp->tgl_masuk);
                return [
                    'id'    => $emp->id,
                    'name'  => $emp->nama_karyawan ?? $emp->nama,
                    'role'  => $emp->jabatan ?? 'Staff',
                    'type'  => 'work_anniversary',
                    'date'  => $joinDate->format('Y-m-d'),
                    'years' => $now->year - $joinDate->year,
                ];
            });

        // Birthdays this month
        $birthdays = Employee::where('aktif', 'Y')
            ->whereNotNull('tgl_lahir')
            ->whereMonth('tgl_lahir', $month)
            ->get()
            ->map(function ($emp) {
                $birthDate = \Carbon\Carbon::parse($emp->tgl_lahir);
                return [
                    'id'   => $emp->id,
                    'name' => $emp->nama_karyawan ?? $emp->nama,
                    'role' => $emp->jabatan ?? 'Staff',
                    'type' => 'birthday',
                    'date' => $birthDate->format('m-d'),
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
        $activities = [];

        try {
            $activityLogs = DB::connection('pgsql_master')
                ->table('activity_log') // Use spatie default table if exists or mock
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
                
            $activities = $activityLogs->map(function ($log) {
                return [
                    'id'          => $log->id,
                    'type'        => $log->log_name ?? 'activity',
                    'description' => $log->description,
                    'employee'    => null, // Can map to causer_id later
                    'metadata'    => json_decode($log->properties ?? '{}', true),
                    'created_at'  => Carbon::parse($log->created_at)->toISOString(),
                ];
            });
        } catch (\Exception $e) {
            // Fallback if no activity log table
            $activities = [];
        }

        return $this->successResponse($activities);
    }
}
