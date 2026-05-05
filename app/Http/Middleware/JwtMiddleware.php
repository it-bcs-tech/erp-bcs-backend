<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use App\Traits\ApiResponseTrait;

class JwtMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'ERR_UNAUTHORIZED',
                'message' => 'Token is invalid or expired. Please login again.',
                'data'    => null,
            ], 401);
        }

        return $next($request);
    }
}
