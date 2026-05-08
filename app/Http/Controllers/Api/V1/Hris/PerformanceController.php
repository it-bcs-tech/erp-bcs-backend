<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/performance
     * Menyediakan hasil evaluasi KPI karyawan dan informasi jadwal training/pelatihan.
     * Saat ini menggunakan static dummy data sesuai kontrak API.
     */
    public function index(Request $request)
    {
        $kpiRecords = [
            [
                "id"           => "KPI-2026-Q1-001",
                "employeeName" => "Budi Santoso",
                "employeeId"   => "EMP-001",
                "department"   => "Engineering",
                "period"       => "Q1 2026",
                "score"        => 92,
                "grade"        => "A",
                "evaluator"    => "Manager Ops"
            ]
        ];

        $trainingPrograms = [
            [
                "id"           => "TRN-2026-01",
                "title"        => "Advanced React & SvelteKit Integration",
                "date"         => "2026-05-20",
                "participants" => 12,
                "status"       => "Upcoming"
            ]
        ];

        $metrics = [
            "avgKpiScore"       => 84.5,
            "totalEvaluated"    => 124,
            "upcomingTrainings" => 3
        ];

        $data = [
            "kpiRecords"       => $kpiRecords,
            "trainingPrograms" => $trainingPrograms,
            "metrics"          => $metrics
        ];

        return $this->successResponse($data, 'Performance metrics retrieved successfully');
    }
}
