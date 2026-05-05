<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecruitmentController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/recruitment/pipeline
     * Candidates grouped by pipeline stage (for Kanban board).
     */
    public function pipeline(Request $request)
    {
        $stages = ['Applied', 'Screening', 'Interview', 'Offered'];

        $pipeline = [];

        foreach ($stages as $stage) {
            $query = RecruitmentCandidate::with('job:id,title')
                ->where('pipeline_stage', $stage);

            // Filter by job
            if ($jobId = $request->get('job_id')) {
                $query->where('job_id', $jobId);
            }

            $pipeline[] = [
                'stage'      => $stage,
                'count'      => $query->count(),
                'candidates' => $query->orderBy('updated_at', 'desc')->get()->map(function ($c) {
                    return [
                        'id'             => $c->id,
                        'name'           => $c->name,
                        'email'          => $c->email,
                        'role'           => $c->role,
                        'experience'     => $c->experience,
                        'pipeline_stage' => $c->pipeline_stage,
                        'job'            => $c->job ? $c->job->title : null,
                        'applied_at'     => $c->created_at->toISOString(),
                    ];
                }),
            ];
        }

        return $this->successResponse($pipeline);
    }

    /**
     * PUT /api/v1/hris/recruitment/candidates/{id}/stage
     * Update candidate pipeline stage (drag-and-drop).
     */
    public function updateStage(Request $request, string $id)
    {
        $candidate = RecruitmentCandidate::find($id);

        if (!$candidate) {
            return $this->errorResponse('Candidate not found', 'ERR_NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'new_stage' => 'required|in:Applied,Screening,Interview,Offered,Hired,Rejected',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        $oldStage = $candidate->pipeline_stage;
        $candidate->update(['pipeline_stage' => $request->new_stage]);

        return $this->successResponse([
            'id'        => $candidate->id,
            'name'      => $candidate->name,
            'old_stage' => $oldStage,
            'new_stage' => $request->new_stage,
        ], "Candidate moved from {$oldStage} to {$request->new_stage}");
    }
}
