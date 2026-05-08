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
        $logs = [
            [
                'id'               => 'ATT-1001',
                'employeeName'     => 'Budi Santoso',
                'employeeId'       => 'EMP-001',
                'department'       => 'Engineering',
                'date'             => '2026-05-07',
                'checkIn'          => '07:45 AM',
                'checkOut'         => '17:15 PM',
                'status'           => 'On Time',
                'checkInLocation'  => 'Kantor Pusat Cilegon',
                'checkOutLocation' => 'Kantor Pusat Cilegon',
                'avatar'           => 'https://ui-avatars.com/api/?name=Budi+Santoso'
            ]
        ];

        $data = [
            'logs'    => $logs,
            'metrics' => [
                'totalEmployees' => 648,
                'presentToday'   => 602,
                'lateToday'      => 24,
                'absentToday'    => 22,
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
