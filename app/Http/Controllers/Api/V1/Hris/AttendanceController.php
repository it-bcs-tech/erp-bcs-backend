<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/attendance
     * List attendance logs, filterable by date and status.
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $date = $request->get('date');
        $status = $request->get('status');
        
        $query = AttendanceLog::with('employee:id,nama_karyawan,departemen,jabatan,foto');

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $logsData = $query->orderBy('date', 'desc')
                          ->orderBy('check_in', 'desc')
                          ->limit($limit)
                          ->get()
                          ->map(function ($log) {
                              $employee = $log->employee;
                              return [
                                  'id'               => 'ATT-' . str_pad($log->id, 4, '0', STR_PAD_LEFT),
                                  'employeeName'     => $employee ? ($employee->nama_karyawan ?? 'Unknown') : 'Unknown',
                                  'employeeId'       => $log->employee_id ? 'EMP-' . str_pad($log->employee_id, 3, '0', STR_PAD_LEFT) : 'Unknown',
                                  'department'       => $employee ? ($employee->departemen ?? 'General') : 'General',
                                  'date'             => $log->date->format('Y-m-d'),
                                  'checkIn'          => $log->check_in ? $log->check_in->format('H:i A') : null,
                                  'checkOut'         => $log->check_out ? $log->check_out->format('H:i A') : null,
                                  'status'           => $log->status,
                                  'checkInLocation'  => $log->notes ?? 'Kantor', // Fallback if no location data available
                                  'checkOutLocation' => $log->notes ?? 'Kantor',
                                  'avatar'           => $employee && $employee->foto ? $employee->foto : 'https://ui-avatars.com/api/?name=' . urlencode($employee ? ($employee->nama_karyawan ?? 'User') : 'User')
                              ];
                          });

        $today = Carbon::today()->toDateString();
        $totalEmployees = Employee::where('aktif', 'Y')->count();
        $presentToday = AttendanceLog::whereDate('date', $today)
                                     ->whereIn('status', ['On Time', 'Late', 'Half Day'])
                                     ->count();
        $lateToday = AttendanceLog::whereDate('date', $today)->where('status', 'Late')->count();
        $absentToday = AttendanceLog::whereDate('date', $today)->where('status', 'Absent')->count();

        $data = [
            'logs'    => $logsData,
            'metrics' => [
                'totalEmployees' => $totalEmployees,
                'presentToday'   => $presentToday,
                'lateToday'      => $lateToday,
                'absentToday'    => $absentToday,
            ]
        ];

        return $this->successResponse($data, 'Attendance retrieved successfully');
    }

    /**
     * GET /api/v1/hris/attendance/stats
     * Today's attendance summary.
     */
    public function stats(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $stats = [
            'date'      => $date,
            'on_time'   => AttendanceLog::whereDate('date', $date)->where('status', 'On Time')->count(),
            'late'      => AttendanceLog::whereDate('date', $date)->where('status', 'Late')->count(),
            'absent'    => AttendanceLog::whereDate('date', $date)->where('status', 'Absent')->count(),
            'half_day'  => AttendanceLog::whereDate('date', $date)->where('status', 'Half Day')->count(),
            'remote'    => AttendanceLog::whereDate('date', $date)->where('work_type', 'Remote')->count(),
            'on_site'   => AttendanceLog::whereDate('date', $date)->where('work_type', 'On-Site')->count(),
            'total'     => AttendanceLog::whereDate('date', $date)->count(),
        ];

        return $this->successResponse($stats);
    }
}
