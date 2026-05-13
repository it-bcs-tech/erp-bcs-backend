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

        // Leaves from presensi.leaves
        try {
            $totalLeaveRequests = DB::connection('pgsql')->table('leaves')->count();
            $pendingLeaveRequests = DB::connection('pgsql')->table('leaves')->where('status', 'Pending')->count();
        } catch (\Exception $e) {
            $totalLeaveRequests = 0;
            $pendingLeaveRequests = 0;
        }

        // Recruitment (tabel mungkin belum ada di server)
        try {
            $openPositions = \App\Models\RecruitmentJob::where('status', 'Open')->count();
        } catch (\Exception $e) {
            $openPositions = 0;
        }

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
     * Recent HRIS activity log from presensi.activity_log.
     */
    public function activities(Request $request)
    {
        $limit = $request->get('limit', 10);

        try {
            $activityLogs = \App\Models\ActivityLog::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $activities = $activityLogs->map(function ($log) {
                // Spatie format: log_name, description, subject_type/id, causer_type/id, properties
                $properties = $log->metadata ?? [];
                $causerName = $properties['causer_name'] ?? null;

                // Try to get causer name from causer relation if available
                if (!$causerName && $log->causer_id) {
                    $causer = \App\Models\Employee::find($log->causer_id);
                    $causerName = $causer ? ($causer->nama_karyawan ?? $causer->nama) : null;
                }

                return [
                    'id'          => $log->id,
                    'type'        => $log->type ?? 'activity',
                    'description' => $log->description,
                    'employee'    => $causerName,
                    'metadata'    => $properties,
                    'created_at'  => $log->created_at ? $log->created_at->toISOString() : null,
                ];
            });
        } catch (\Exception $e) {
            $activities = [];
        }

        return $this->successResponse($activities);
    }
}
