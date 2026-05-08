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
        $query = AttendanceLog::with('employee:id,name,role,avatar,department_id', 'employee.department:id,name');

        // Filter by date (default: today)
        $date = $request->get('date', Carbon::today()->toDateString());
        $query->whereDate('date', $date);

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by work type
        if ($workType = $request->get('work_type')) {
            $query->where('work_type', $workType);
        }

        // Search by employee name
        if ($search = $request->get('search')) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('check_in', 'desc')->get()->map(function ($log) {
            $status = $log->status;
            if (!in_array($status, ['On Time', 'Late', 'Absent'])) {
                $status = 'On Time';
            }

            return [
                'id'               => 'ATT-' . str_pad($log->id, 4, '0', STR_PAD_LEFT),
                'employeeName'     => $log->employee ? $log->employee->name : 'Unknown',
                'employeeId'       => $log->employee ? ($log->employee->employee_code ?? 'EMP-' . str_pad($log->employee->id, 3, '0', STR_PAD_LEFT)) : 'Unknown',
                'department'       => $log->employee && $log->employee->department ? $log->employee->department->name : 'General',
                'date'             => $log->date->format('Y-m-d'),
                'checkIn'          => $log->check_in ? $log->check_in->format('h:i A') : '--:-- AM',
                'checkOut'         => $log->check_out ? $log->check_out->format('h:i A') : '--:-- PM',
                'status'           => $status,
                'checkInLocation'  => 'Kantor Pusat Cilegon', // Placeholder for actual location
                'checkOutLocation' => 'Kantor Pusat Cilegon',
                'avatar'           => $log->employee && $log->employee->avatar ? $log->employee->avatar : 'https://ui-avatars.com/api/?name=' . urlencode($log->employee ? $log->employee->name : 'User')
            ];
        });

        $totalEmployees = \App\Models\Employee::where('status', 'Active')->count();
        $presentToday   = AttendanceLog::whereDate('date', $date)->count();
        $lateToday      = AttendanceLog::whereDate('date', $date)->where('status', 'Late')->count();
        $absentToday    = AttendanceLog::whereDate('date', $date)->where('status', 'Absent')->count() ?: ($totalEmployees - $presentToday);

        $data = [
            'logs'    => $logs,
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
