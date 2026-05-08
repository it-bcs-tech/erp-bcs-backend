<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class LifecycleController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/lifecycle
     * Menampilkan riwayat promosi, mutasi, terminasi, dan SP.
     * Saat ini menggunakan static dummy data sesuai kontrak API.
     */
    public function index(Request $request)
    {
        $actions = [
            [
                "id"          => "MUT-2026-101",
                "type"        => "Mutation",
                "employeeName"=> "Budi Santoso",
                "employeeId"  => "EMP-001",
                "date"        => "2026-05-01",
                "description" => "Transferred from Serang Branch to Cilegon HQ",
                "status"      => "Completed"
            ]
        ];

        $metrics = [
            "activeMutations"     => 5,
            "activeWarnings"      => 12,
            "pendingTerminations" => 3
        ];

        $data = [
            "actions" => $actions,
            "metrics" => $metrics
        ];

        return $this->successResponse($data, 'Lifecycle records retrieved successfully');
    }
}
