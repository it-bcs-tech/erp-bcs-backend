<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/performance
     * Menyediakan hasil evaluasi KPI karyawan dan informasi jadwal training/pelatihan.
     * Data diambil dari erp.employees (performance_score) dan erp.activity_logs.
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 50);

        // KPI Records from employees with performance_score
        $employees = DB::connection('pgsql')
            ->table('erp.employees')
            ->join('erp.departments', 'erp.employees.department_id', '=', 'erp.departments.id')
            ->select(
                'erp.employees.id',
                'erp.employees.name',
                'erp.employees.employee_code',
                'erp.employees.role',
                'erp.employees.performance_score',
                'erp.departments.name as department_name'
            )
            ->whereNotNull('erp.employees.performance_score')
            ->where('erp.employees.performance_score', '>', 0)
            ->orderBy('erp.employees.performance_score', 'desc')
            ->limit($limit)
            ->get();

        $kpiRecords = $employees->map(function ($emp) {
            $score = (float) $emp->performance_score;
            // Scale: performance_score is 0-5, convert to percentage (0-100)
            $percentScore = round(($score / 5) * 100);
            $grade = $this->scoreToGrade($percentScore);
            $quarter = 'Q' . ceil(now()->month / 3) . ' ' . now()->year;

            return [
                'id'           => 'KPI-' . now()->format('Y') . '-' . $quarter . '-' . str_pad($emp->id, 3, '0', STR_PAD_LEFT),
                'employeeName' => $emp->name,
                'employeeId'   => $emp->employee_code ?? ('EMP-' . str_pad($emp->id, 3, '0', STR_PAD_LEFT)),
                'department'   => $emp->department_name ?? 'General',
                'period'       => $quarter,
                'score'        => $percentScore,
                'grade'        => $grade,
                'evaluator'    => 'Manager ' . ($emp->department_name ?? 'Dept'),
            ];
        });

        // Training programs from activity_logs with training type
        $trainingLogs = DB::connection('pgsql')
            ->table('erp.activity_logs')
            ->where('type', 'like', '%training%')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $trainingPrograms = $trainingLogs->map(function ($log) {
            $metadata = json_decode($log->metadata ?? '{}', true);
            return [
                'id'           => 'TRN-' . now()->format('Y') . '-' . str_pad($log->id, 2, '0', STR_PAD_LEFT),
                'title'        => $log->description ?? 'Training Program',
                'date'         => \Carbon\Carbon::parse($log->created_at)->format('Y-m-d'),
                'participants' => $metadata['participants'] ?? 0,
                'status'       => $metadata['status'] ?? 'Completed',
            ];
        });

        // Metrics from real data
        $allScores = DB::connection('pgsql')
            ->table('erp.employees')
            ->whereNotNull('performance_score')
            ->where('performance_score', '>', 0)
            ->pluck('performance_score');

        $avgRaw = $allScores->count() > 0 ? $allScores->avg() : 0;
        $avgPercent = round(($avgRaw / 5) * 100, 1);

        $metrics = [
            'avgKpiScore'       => $avgPercent,
            'totalEvaluated'    => $allScores->count(),
            'upcomingTrainings' => $trainingLogs->count(),
        ];

        $data = [
            'kpiRecords'       => $kpiRecords,
            'trainingPrograms' => $trainingPrograms,
            'metrics'          => $metrics,
        ];

        return $this->successResponse($data, 'Performance metrics retrieved successfully');
    }

    /**
     * Convert percentage score to letter grade.
     */
    private function scoreToGrade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'E';
    }
}
