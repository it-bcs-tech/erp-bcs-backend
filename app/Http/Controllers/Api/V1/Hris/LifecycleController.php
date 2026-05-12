<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LifecycleController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/lifecycle
     * Menampilkan riwayat promosi, mutasi, terminasi, dan SP.
     * Data diambil dari activity_logs yang bertipe lifecycle.
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 50);
        $type = $request->get('type'); // Mutation, Warning, Termination

        // Query activity logs for lifecycle events
        $query = ActivityLog::with('employee:id,name,role,avatar')
            ->whereIn('type', [
                'mutation', 'warning', 'termination', 'promotion',
                'employee_joined', 'employee_terminated', 'employee_warned',
                'employee_promoted', 'employee_mutated',
            ]);

        if ($type) {
            $typeMap = [
                'Mutation'    => ['mutation', 'employee_mutated'],
                'Warning'     => ['warning', 'employee_warned'],
                'Termination' => ['termination', 'employee_terminated'],
                'Promotion'   => ['promotion', 'employee_promoted'],
            ];
            if (isset($typeMap[$type])) {
                $query->whereIn('type', $typeMap[$type]);
            }
        }

        $actions = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $employee = $log->employee;
                $displayType = $this->mapActivityType($log->type);

                return [
                    'id'           => strtoupper(substr($displayType, 0, 3)) . '-' . now()->format('Y') . '-' . str_pad($log->id, 3, '0', STR_PAD_LEFT),
                    'type'         => $displayType,
                    'employeeName' => $employee ? ($employee->name ?? 'Unknown') : 'Unknown',
                    'employeeId'   => $employee ? 'EMP-' . str_pad($employee->id, 3, '0', STR_PAD_LEFT) : 'Unknown',
                    'date'         => $log->created_at->format('Y-m-d'),
                    'description'  => $log->description,
                    'status'       => 'Completed',
                ];
            });

        // Metrics from real data
        $metrics = [
            'activeMutations'     => ActivityLog::whereIn('type', ['mutation', 'employee_mutated'])->count(),
            'activeWarnings'      => ActivityLog::whereIn('type', ['warning', 'employee_warned'])->count(),
            'pendingTerminations' => ActivityLog::whereIn('type', ['termination', 'employee_terminated'])->count(),
        ];

        $data = [
            'actions' => $actions,
            'metrics' => $metrics,
        ];

        return $this->successResponse($data, 'Lifecycle records retrieved successfully');
    }

    /**
     * Map activity log type to display type.
     */
    private function mapActivityType(string $type): string
    {
        $map = [
            'mutation'             => 'Mutation',
            'employee_mutated'     => 'Mutation',
            'warning'              => 'Warning',
            'employee_warned'      => 'Warning',
            'termination'          => 'Termination',
            'employee_terminated'  => 'Termination',
            'promotion'            => 'Promotion',
            'employee_promoted'    => 'Promotion',
            'employee_joined'      => 'Mutation', // New hire treated as mutation
        ];

        return $map[$type] ?? ucfirst($type);
    }
}
