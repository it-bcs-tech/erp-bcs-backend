<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Return a success JSON response.
     */
    protected function successResponse($data, string $message = 'Data retrieved successfully', int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Return a success JSON response with pagination meta.
     */
    protected function paginatedResponse($paginator, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
                'total_items'  => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ], 200);
    }

    /**
     * Return an error JSON response.
     */
    protected function errorResponse(string $message, string $errorCode = 'ERR_GENERAL', int $httpCode = 400, $data = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'code'    => $errorCode,
            'message' => $message,
            'data'    => $data,
        ], $httpCode);
    }
}
