<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Presence;
use App\Models\OvertimeRequest;
use App\Models\ShiftRoster;
use App\Models\PresensiUser;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/attendance
     * List attendance logs from presensi_db.presences (real-time data).
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $date = $request->get('date');
        $status = $request->get('status');

        $query = Presence::with('user:id,name,email,photo');

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($status) {
            // Map frontend status to DB values
            $statusMap = [
                'On Time' => ['present', 'Tepat Waktu'],
                'Late'    => ['late', 'Terlambat'],
            ];
            if (isset($statusMap[$status])) {
                $query->whereIn('status', $statusMap[$status]);
            } else {
                $query->where('status', $status);
            }
        }

        $logsData = $query->orderBy('date', 'desc')
                          ->orderBy('clock_in', 'desc')
                          ->limit($limit)
                          ->get()
                          ->map(function ($presence) {
                              $user = $presence->user;
                              $userName = $user ? $user->name : 'Unknown';

                              return [
                                  'id'               => 'ATT-' . str_pad($presence->id, 4, '0', STR_PAD_LEFT),
                                  'employeeName'     => $userName,
                                  'employeeId'       => $presence->user_id ? 'EMP-' . str_pad($presence->user_id, 3, '0', STR_PAD_LEFT) : 'Unknown',
                                  'department'       => 'General',
                                  'date'             => $presence->date->format('Y-m-d'),
                                  'checkIn'          => $presence->clock_in ? Carbon::parse($presence->clock_in)->format('h:i A') : null,
                                  'checkOut'         => $presence->clock_out ? Carbon::parse($presence->clock_out)->format('h:i A') : null,
                                  'status'           => $presence->normalized_status,
                                  'checkInLocation'  => ($presence->latitude_in && $presence->longitude_in)
                                      ? "{$presence->latitude_in}, {$presence->longitude_in}"
                                      : 'Kantor',
                                  'checkOutLocation' => ($presence->latitude_out && $presence->longitude_out)
                                      ? "{$presence->latitude_out}, {$presence->longitude_out}"
                                      : 'Kantor',
                                  'avatar'           => $user && $user->photo ? $user->photo : 'https://ui-avatars.com/api/?name=' . urlencode($userName),
                              ];
                          });

        // Metrics from real presensi data
        $today = Carbon::today()->toDateString();
        $totalEmployees = Employee::where('aktif', 'Y')->count();
        $presentToday = Presence::whereDate('date', $today)
                                ->whereIn('status', ['present', 'Tepat Waktu'])
                                ->distinct('user_id')
                                ->count('user_id');
        $lateToday = Presence::whereDate('date', $today)
                             ->whereIn('status', ['late', 'Terlambat'])
                             ->distinct('user_id')
                             ->count('user_id');
        $totalLoggedToday = Presence::whereDate('date', $today)
                                    ->distinct('user_id')
                                    ->count('user_id');
        $absentToday = $totalEmployees - $totalLoggedToday;

        $data = [
            'logs'    => $logsData,
            'metrics' => [
                'totalEmployees' => $totalEmployees,
                'presentToday'   => $presentToday,
                'lateToday'      => $lateToday,
                'absentToday'    => max(0, $absentToday),
            ]
        ];

        return $this->successResponse($data, 'Attendance retrieved successfully');
    }

    /**
     * GET /api/v1/hris/attendance/stats
     * Today's attendance summary from presensi_db.
     */
    public function stats(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $stats = [
            'date'      => $date,
            'on_time'   => Presence::whereDate('date', $date)->whereIn('status', ['present', 'Tepat Waktu'])->count(),
            'late'      => Presence::whereDate('date', $date)->whereIn('status', ['late', 'Terlambat'])->count(),
            'absent'    => 0, // Calculated: total_employees - total_logged
            'half_day'  => 0,
            'remote'    => 0,
            'on_site'   => Presence::whereDate('date', $date)->count(),
            'total'     => Presence::whereDate('date', $date)->count(),
        ];

        // Calculate absent
        $totalEmployees = Employee::where('aktif', 'Y')->count();
        $totalLogged = Presence::whereDate('date', $date)->distinct('user_id')->count('user_id');
        $stats['absent'] = max(0, $totalEmployees - $totalLogged);

        return $this->successResponse($stats);
    }

    /**
     * GET /api/v1/hris/attendance/overtimes
     * Ringkasan & daftar permohonan lembur SPKL
     */
    public function overtimes(Request $request)
    {
        $status  = $request->query('status', 'all');
        $search  = $request->query('search');
        $date    = $request->query('date');
        $perPage = (int) $request->query('per_page', 50);

        // Summary
        $totalRequests    = OvertimeRequest::count();
        $approvedRequests = OvertimeRequest::where('status', 'approved')->count();
        $pendingRequests  = OvertimeRequest::where('status', 'pending')->count();
        $rejectedRequests = OvertimeRequest::where('status', 'rejected')->count();

        // Main Query with relation
        $query = OvertimeRequest::with('user:id,name,email');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($date) {
            $query->whereDate('start_date', $date);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uQuery) use ($search) {
                      $uQuery->where('name', 'LIKE', "%{$search}%")
                             ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        $formattedRequests = collect($paginated->items())->map(function ($ot) {
            $user = $ot->user;
            return [
                'id'               => (int) $ot->id,
                'user_id'          => (int) $ot->user_id,
                'employee_name'    => $user ? $user->name : 'Employee #' . $ot->user_id,
                'email'            => $user ? $user->email : '',
                'start_date'       => $ot->start_date ? Carbon::parse($ot->start_date)->format('Y-m-d') : null,
                'end_date'         => $ot->end_date ? Carbon::parse($ot->end_date)->format('Y-m-d') : null,
                'start_time'       => $ot->start_time,
                'end_time'         => $ot->end_time,
                'description'      => $ot->description,
                'status'           => $ot->status,
                'approved_by'      => $ot->approved_by,
                'approved_at'      => $ot->approved_at ? Carbon::parse($ot->approved_at)->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'totalRequests'    => $totalRequests,
                    'approvedRequests' => $approvedRequests,
                    'pendingRequests'  => $pendingRequests,
                    'rejectedRequests' => $rejectedRequests,
                ],
                'requests' => $formattedRequests,
            ],
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/attendance/overtimes
     * Penerbitan Surat Perintah Kerja Lembur (SPKL) baru
     */
    public function storeOvertime(Request $request)
    {
        $validated = $request->validate([
            'user_id'     => 'required|integer',
            'start_date'  => 'required|date_format:Y-m-d',
            'end_date'    => 'required|date_format:Y-m-d',
            'start_time'  => 'required|string',
            'end_time'    => 'required|string',
            'description' => 'required|string',
        ]);

        $overtime = OvertimeRequest::create([
            'user_id'     => $validated['user_id'],
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'start_time'  => $validated['start_time'],
            'end_time'    => $validated['end_time'],
            'description' => $validated['description'],
            'status'      => 'pending',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Surat Perintah Kerja Lembur (SPKL) berhasil diterbitkan',
            'data'    => [
                'id'     => (int) $overtime->id,
                'status' => $overtime->status,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/hris/attendance/overtimes/{id}/approve
     * Persetujuan SPKL oleh HRD / Kepala Pool
     */
    public function approveOvertime(Request $request, $id)
    {
        $overtime = OvertimeRequest::find($id);
        if (!$overtime) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Permohonan lembur tidak ditemukan',
            ], 404);
        }

        $user = auth('api')->user();
        $approverName = $user ? ($user->name ?? 'SUPERVISOR WORKSHOP & HRD') : 'SUPERVISOR WORKSHOP & HRD';

        $overtime->status = 'approved';
        $overtime->approved_by = $approverName;
        $overtime->approved_at = now();
        $overtime->rejection_reason = null;
        $overtime->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Permohonan lembur SPKL telah disetujui',
            'data'    => [
                'id'     => (int) $overtime->id,
                'status' => $overtime->status,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/hris/attendance/overtimes/{id}/reject
     * Penolakan permohonan lembur
     */
    public function rejectOvertime(Request $request, $id)
    {
        $overtime = OvertimeRequest::find($id);
        if (!$overtime) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Permohonan lembur tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $overtime->status = 'rejected';
        $overtime->rejection_reason = $validated['rejection_reason'];
        $overtime->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Permohonan lembur SPKL telah ditolak',
            'data'    => [
                'id'     => (int) $overtime->id,
                'status' => $overtime->status,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/hris/attendance/roster
     * Matriks penjadwalan shift mingguan 24/7
     */
    public function roster(Request $request)
    {
        $rosters = ShiftRoster::all();

        // Default seed formatting if empty
        if ($rosters->isEmpty()) {
            $formatted = [
                [
                    'employeeId'   => 'EMP-001',
                    'employeeName' => 'Budi Santoso',
                    'department'   => 'Bengkel Workshop',
                    'pool'         => 'Pool Cilegon',
                    'schedule'     => ['S1', 'S1', 'S2', 'S2', 'S3', 'OFF', 'OFF'],
                ]
            ];
        } else {
            $formatted = $rosters->map(function ($r) {
                return [
                    'employeeId'   => $r->employee_id,
                    'employeeName' => $r->employee_name,
                    'department'   => $r->department ?: 'Bengkel Workshop',
                    'pool'         => $r->pool ?: 'Pool Cilegon',
                    'schedule'     => is_array($r->schedule) ? $r->schedule : ['S1', 'S1', 'S2', 'S2', 'S3', 'OFF', 'OFF'],
                ];
            });
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'roster' => $formatted,
            ],
        ], 200);
    }

    /**
     * PUT /api/v1/hris/attendance/roster/{employeeId}
     * Update penugasan shift mingguan
     */
    public function updateRoster(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'dayIndex'  => 'required|integer|between:0,6',
            'shiftCode' => 'required|string',
        ]);

        $roster = ShiftRoster::where('employee_id', $employeeId)->first();
        if (!$roster) {
            $roster = ShiftRoster::create([
                'employee_id'   => $employeeId,
                'employee_name' => 'Karyawan ' . $employeeId,
                'department'    => 'Bengkel Workshop',
                'pool'          => 'Pool Cilegon',
                'schedule'      => ['S1', 'S1', 'S2', 'S2', 'S3', 'OFF', 'OFF'],
            ]);
        }

        $schedule = is_array($roster->schedule) ? $roster->schedule : ['S1', 'S1', 'S2', 'S2', 'S3', 'OFF', 'OFF'];
        $schedule[$validated['dayIndex']] = $validated['shiftCode'];
        $roster->schedule = $schedule;
        $roster->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Penugasan shift berhasil diperbarui',
        ], 200);
    }
}
