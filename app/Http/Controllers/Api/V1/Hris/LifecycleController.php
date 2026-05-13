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
     * Data diambil dari activity_log (Spatie format) yang bertipe lifecycle.
     *
     * Spatie activity_log columns: log_name, description, subject_type/id, causer_type/id, properties, event
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 50);
        $type = $request->get('type'); // Mutation, Warning, Termination

        try {
            // Query activity_log for lifecycle events using log_name or event column
            $query = ActivityLog::whereIn('log_name', [
                'mutation', 'warning', 'termination', 'promotion',
                'employee_joined', 'employee_terminated', 'employee_warned',
                'employee_promoted', 'employee_mutated',
                'lifecycle', 'hr',
            ]);

            if ($type) {
                $typeMap = [
                    'Mutation'    => ['mutation', 'employee_mutated'],
                    'Warning'     => ['warning', 'employee_warned'],
                    'Termination' => ['termination', 'employee_terminated'],
                    'Promotion'   => ['promotion', 'employee_promoted'],
                ];
                if (isset($typeMap[$type])) {
                    $query->whereIn('log_name', $typeMap[$type]);
                }
            }

            $actions = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    $displayType = $this->mapActivityType($log->log_name ?? $log->event ?? 'activity');
                    $properties = $log->metadata ?? [];
                    $employeeName = $properties['employee_name'] ?? $properties['causer_name'] ?? 'Unknown';

                    return [
                        'id'           => strtoupper(substr($displayType, 0, 3)) . '-' . now()->format('Y') . '-' . str_pad($log->id, 3, '0', STR_PAD_LEFT),
                        'type'         => $displayType,
                        'employeeName' => $employeeName,
                        'employeeId'   => isset($properties['employee_id']) ? 'EMP-' . str_pad($properties['employee_id'], 3, '0', STR_PAD_LEFT) : 'Unknown',
                        'date'         => $log->created_at ? $log->created_at->format('Y-m-d') : now()->format('Y-m-d'),
                        'description'  => $log->description,
                        'status'       => 'Completed',
                    ];
                });
        } catch (\Exception $e) {
            $actions = collect([]);
        }

        // Metrics
        try {
            $metrics = [
                'activeMutations'     => ActivityLog::whereIn('log_name', ['mutation', 'employee_mutated'])->count(),
                'activeWarnings'      => ActivityLog::whereIn('log_name', ['warning', 'employee_warned'])->count(),
                'pendingTerminations' => ActivityLog::whereIn('log_name', ['termination', 'employee_terminated'])->count(),
            ];
        } catch (\Exception $e) {
            $metrics = [
                'activeMutations'     => 0,
                'activeWarnings'      => 0,
                'pendingTerminations' => 0,
            ];
        }

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
            'employee_joined'      => 'Mutation',
            'lifecycle'            => 'Lifecycle',
            'hr'                   => 'HR Action',
        ];

        return $map[$type] ?? ucfirst($type);
    }
}
