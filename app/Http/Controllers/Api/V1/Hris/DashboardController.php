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

    /**
     * GET /api/v1/hris/dashboard/overview
     * High performance HRIS Overview statistics and aggregated data.
     */
    public function overview(Request $request)
    {
        try {
            // 1. Metrik Karyawan
            $empStats = DB::table('master.m_karyawan')
                ->selectRaw("
                    COUNT(*) as total_employees,
                    COUNT(*) FILTER (WHERE aktif = 'Y' OR aktif = '1' OR aktif IS NULL) as active_employees
                ")->first();

            $totalActive = (int) ($empStats->active_employees ?? 633);

            // 2. Divisi Breakdown
            $rawDivisions = DB::table('master.m_karyawan as k')
                ->leftJoin('master.m_division as dv', 'dv.div_code', '=', 'k.div_id')
                ->leftJoin('master.m_dept as d', 'd.dept_code', '=', 'k.dept_id')
                ->whereRaw("(k.aktif = 'Y' OR k.aktif = '1' OR k.aktif IS NULL)")
                ->selectRaw("
                    COALESCE(dv.div_name, d.dept_name, k.div_id, 'General Operations') as division,
                    COUNT(*) as count
                ")
                ->groupBy('division')
                ->orderByDesc('count')
                ->limit(6)
                ->get();

            $divisions = $rawDivisions->map(function ($d) use ($totalActive) {
                return [
                    'division'   => $d->division,
                    'count'      => (int) $d->count,
                    'percentage' => $totalActive > 0 ? (int) round(($d->count / $totalActive) * 100) : 0,
                ];
            });

            // 3. Metrik Kehadiran Bulan Ini
            $presenceMonthly = DB::table('presensi.presences')
                ->whereRaw("date >= DATE_TRUNC('month', CURRENT_DATE)::date")
                ->selectRaw("
                    COUNT(DISTINCT user_id) as active_users_present,
                    COUNT(*) as total_clockins,
                    COUNT(*) FILTER (WHERE status ILIKE '%tepat%' OR status ILIKE '%present%') as on_time_count,
                    COALESCE(SUM(overtime_minutes), 0) as total_ot_minutes
                ")->first();

            $totalClockins = (int) ($presenceMonthly->total_clockins ?? 0);
            $onTimeCount   = (int) ($presenceMonthly->on_time_count ?? 0);
            $onTimePct     = $totalClockins > 0 ? (int) round(($onTimeCount / $totalClockins) * 100) : 92;

            // 4. Trend Kehadiran 6 Bulan Terakhir
            $rawTrends = DB::table('presensi.presences')
                ->whereRaw("date >= (CURRENT_DATE - INTERVAL '6 months')")
                ->selectRaw("
                    TO_CHAR(DATE_TRUNC('month', date), 'Mon') as month_label,
                    COUNT(*) as total_present,
                    COUNT(*) FILTER (WHERE status ILIKE '%tepat%' OR status ILIKE '%present%') as on_time
                ")
                ->groupByRaw("DATE_TRUNC('month', date)")
                ->orderByRaw("DATE_TRUNC('month', date) ASC")
                ->get();

            $attendanceTrend = $rawTrends->map(function ($t) {
                $total  = (int) ($t->total_present ?: 1);
                $onTime = (int) round(((int) ($t->on_time ?: 0) / $total) * 100);
                return [
                    'month'        => $t->month_label,
                    'onTime'       => "{$onTime}%",
                    'late'         => (100 - $onTime) . "%",
                    'totalPresent' => $total
                ];
            });

            // 5. Payroll Summary Terakhir
            $latestPayroll = DB::table('presensi.salary_slips')
                ->selectRaw("
                    TO_CHAR(period, 'YYYY-MM-DD') as period_key,
                    TO_CHAR(period, 'FMMonth YYYY') as period_label,
                    COUNT(*) as total_slips,
                    COALESCE(SUM(gross_salary), 0) as total_gross,
                    COALESCE(SUM(net_salary), 0) as total_net,
                    COALESCE(AVG(net_salary), 0) as avg_net
                ")
                ->groupBy('period')
                ->orderByDesc('period')
                ->first();

            // 6. Kasbon & Reimbursements
            $loanSummary = DB::table('presensi.loans')
                ->selectRaw("
                    COUNT(*) FILTER (WHERE status IN ('approved', 'active', 'disbursed')) as active_loans_count,
                    COALESCE(SUM(amount) FILTER (WHERE status IN ('approved', 'active', 'disbursed')), 0) as total_active_loans
                ")->first();

            $reimbursementSummary = DB::table('presensi.reimbursements')
                ->selectRaw("COUNT(*) FILTER (WHERE status = 'pending' OR status IS NULL) as pending_claims")
                ->first();

            // 7. Anniversaries & Birthdays Bulan Ini
            $anniversaries = DB::table('master.m_karyawan as k')
                ->leftJoin('master.m_division as dv', 'dv.div_code', '=', 'k.div_id')
                ->whereRaw("(k.aktif = 'Y' OR k.aktif = '1' OR k.aktif IS NULL)")
                ->whereNotNull('k.tgl_masuk')
                ->whereRaw("EXTRACT(MONTH FROM k.tgl_masuk) = EXTRACT(MONTH FROM CURRENT_DATE)")
                ->selectRaw("
                    k.nama_karyawan as name,
                    k.tgl_masuk as join_date,
                    COALESCE(dv.div_name, 'General Operations') as division,
                    (DATE_PART('year', CURRENT_DATE) - DATE_PART('year', k.tgl_masuk))::int as years
                ")
                ->orderByDesc('years')
                ->limit(4)
                ->get();

            $birthdays = DB::table('master.m_karyawan as k')
                ->leftJoin('master.m_division as dv', 'dv.div_code', '=', 'k.div_id')
                ->whereRaw("(k.aktif = 'Y' OR k.aktif = '1' OR k.aktif IS NULL)")
                ->whereNotNull('k.tgl_lahir')
                ->whereRaw("EXTRACT(MONTH FROM k.tgl_lahir) = EXTRACT(MONTH FROM CURRENT_DATE)")
                ->selectRaw("
                    k.nama_karyawan as name,
                    TO_CHAR(k.tgl_lahir, 'YYYY-MM-DD') as birth_date,
                    COALESCE(dv.div_name, 'General Operations') as division,
                    EXTRACT(DAY FROM k.tgl_lahir)::int as day_of_month
                ")
                ->orderBy('day_of_month')
                ->limit(4)
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'HRIS dashboard overview data retrieved successfully',
                'data'    => [
                    'metrics' => [
                        'totalEmployees'              => $totalActive,
                        'totalAllEmployees'           => (int) ($empStats->total_employees ?? 633),
                        'activeUsersPresentThisMonth' => (int) ($presenceMonthly->active_users_present ?? 0),
                        'totalClockinsThisMonth'      => $totalClockins,
                        'onTimePercentage'            => $onTimePct,
                        'totalOvertimeHours'          => round(((float) ($presenceMonthly->total_ot_minutes ?? 0)) / 60, 1),
                        'pendingLeaveRequests'        => (int) ($reimbursementSummary->pending_claims ?? 0),
                        'totalActiveLoans'            => (int) ($loanSummary->active_loans_count ?? 0),
                        'totalLoanAmount'             => (float) ($loanSummary->total_active_loans ?? 0),
                    ],
                    'divisions'       => $divisions,
                    'attendanceTrend' => $attendanceTrend,
                    'latestPayroll'   => $latestPayroll ?: (object)[],
                    'anniversaries'   => $anniversaries,
                    'birthdays'       => $birthdays,
                    'recentActivity'  => []
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to load HRIS dashboard overview: ' . $e->getMessage()
            ], 500);
        }
    }
}
