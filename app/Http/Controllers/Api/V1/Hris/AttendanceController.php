<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Presence;
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

        $query = Presence::with('User:id,name,email');

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
                              $user = $presence->User;
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
                                  'avatar'           => 'https://ui-avatars.com/api/?name=' . urlencode($userName),
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
}
