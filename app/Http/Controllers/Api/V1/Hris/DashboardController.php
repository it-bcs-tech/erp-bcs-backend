<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Presence;
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
     * Attendance data from presensi_db (real-time).
     */
    public function metrics()
    {
        $today = Carbon::today()->toDateString();

        // m_karyawan (629 active employees)
        $totalEmployees = Employee::where('aktif', 'Y')->count();

        // Real-time attendance from presensi_db.presences
        $presentToday = Presence::whereDate('date', $today)
                                ->distinct('user_id')
                                ->count('user_id');

        // Leaves from erp.leave_requests
        $totalLeaveRequests = \App\Models\LeaveRequest::count();
        $pendingLeaveRequests = \App\Models\LeaveRequest::where('status', 'Pending')->count();

        // Recruitment from erp.recruitment_jobs
        $openPositions = \App\Models\RecruitmentJob::where('status', 'Open')->count();

        $data = [
            'totalEmployees'        => $totalEmployees,
            'presentToday'          => $presentToday,
            'attendanceCapacity'    => $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 0,
            'totalLeaveRequests'    => $totalLeaveRequests,
            'pendingLeaveRequests'  => $pendingLeaveRequests,
            'openPositions'         => $openPositions,
            'highPriorityPositions' => (int) ceil($openPositions / 2),
        ];

        return $this->successResponse($data, 'Metrics retrieved successfully');
    }

    /**
     * GET /api/v1/hris/dashboard/attendance-trend
     * Monthly attendance trend from presensi_db.
     */
    public function attendanceTrend(Request $request)
    {
        $months = $request->get('months', 6);
        $trend  = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i);
            $month = $date->format('Y-m');
            $label = $date->format('M Y');

            // Real attendance from presensi_db
            $total = Presence::whereYear('date', $date->year)
                             ->whereMonth('date', $date->month)
                             ->count();

            $onTime = Presence::whereYear('date', $date->year)
                              ->whereMonth('date', $date->month)
                              ->whereIn('status', ['present', 'Tepat Waktu'])
                              ->count();

            $late = Presence::whereYear('date', $date->year)
                            ->whereMonth('date', $date->month)
                            ->whereIn('status', ['late', 'Terlambat'])
                            ->count();

            $trend[] = [
                'month'          => $month,
                'label'          => $label,
                'total'          => $total,
                'remote'         => 0,     // presensi_db doesn't track work_type
                'on_site'        => $total, // All are on-site
                'on_time'        => $onTime,
                'late'           => $late,
                'remote_percent' => 0,
                'onsite_percent' => 100,
            ];
        }

        return $this->successResponse($trend);
    }

    /**
     * GET /api/v1/hris/dashboard/anniversaries
     * Employees with work anniversaries and birthdays this month.
     * Data from master_db.m_karyawan.
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
                $joinDate = Carbon::parse($emp->tgl_masuk);
                return [
                    'id'    => $emp->id,
                    'name'  => $emp->nama_karyawan ?? $emp->nama,
                    'role'  => $emp->title ?? 'Staff',
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
                $birthDate = Carbon::parse($emp->tgl_lahir);
                return [
                    'id'   => $emp->id,
                    'name' => $emp->nama_karyawan ?? $emp->nama,
                    'role' => $emp->title ?? 'Staff',
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
     * Recent HRIS activity log from erp.activity_logs.
     */
    public function activities(Request $request)
    {
        $limit = $request->get('limit', 10);

        try {
            $activityLogs = \App\Models\ActivityLog::with('employee:id,name')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $activities = $activityLogs->map(function ($log) {
                return [
                    'id'          => $log->id,
                    'type'        => $log->type ?? 'activity',
                    'description' => $log->description,
                    'employee'    => $log->employee ? $log->employee->name : null,
                    'metadata'    => $log->metadata ?? [],
                    'created_at'  => $log->created_at->toISOString(),
                ];
            });
        } catch (\Exception $e) {
            $activities = [];
        }

        return $this->successResponse($activities);
    }
}
