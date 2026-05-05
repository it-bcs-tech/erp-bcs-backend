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

        $perPage = $request->get('limit', 20);
        $logs = $query->orderBy('check_in', 'desc')->paginate($perPage);

        return $this->paginatedResponse($logs);
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
