<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        $credentials = $request->only('email', 'password');

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return $this->errorResponse(
                'Invalid email or password',
                'ERR_UNAUTHORIZED',
                401
            );
        }

        return $this->respondWithToken($token);
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        $user = \App\Models\User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->respondWithToken($token, 'User registered successfully');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout()
    {
        Auth::guard('api')->logout();
        return $this->successResponse(null, 'Successfully logged out');
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh()
    {
        $token = Auth::guard('api')->refresh();
        return $this->respondWithToken($token, 'Token refreshed successfully');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me()
    {
        $user = Auth::guard('api')->user();
        $user->load('employee.department');

        return $this->successResponse($user);
    }

    /**
     * Build token response.
     */
    protected function respondWithToken(string $token, string $message = 'Login successful')
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'user'         => Auth::guard('api')->user(),
        ], $message);
    }
}
