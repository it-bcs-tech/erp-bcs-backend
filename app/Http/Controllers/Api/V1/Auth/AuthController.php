<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Traits\ApiResponseTrait;

class AuthController extends Controller
{
    use ApiResponseTrait;
    /**
     * Konfigurasi RBAC (Sesuai dengan $lib/types/auth.ts)
     */
    private const ALL_MODULES = ['fms', 'ocs', 'hris', 'marketing', 'pms', 'kasir', 'finance', 'dms', 'qhse'];
    private const OCS_MIN_LEVEL_SEQUENCE = 4;
    private const ADMIN_ROLES = ['superadmin', 'superhyperadmin'];

    // Map default modul berdasarkan role
    private const ROLE_MODULE_MAP = [
        'hr' => ['hris'],
        'manager' => ['ocs'],
        'supervisor' => ['ocs'],
        'kepala_mekanik' => ['fms'],
        'admin_maintenance' => ['fms'],
        'inspector' => ['fms'],
        'kepala_gudang' => ['dms'],
        'admin_warehouse' => ['dms'],
        'manager_fms' => ['fms'],
        'manager_maintenance' => ['fms'],
        'manager_pms' => ['pms'],
        'manager_finance' => ['finance'],
        'manager_marketing' => ['marketing'],
        'manager_dms' => ['dms'],
        'manager_qhse' => ['qhse'],
    ];

    // Map default modul berdasarkan divisi
    private const DIVISION_MODULE_MAP = [
        'DV_41' => ['fms', 'kasir'],
        'DV_37' => ['hris'],
        'DV_36' => ['finance'],
        'DV_43' => ['marketing', 'pms'],
        'DV_28' => self::ALL_MODULES,
        'DV_44' => self::ALL_MODULES,
        'DV_18' => ['fms'],
        'DV_35' => ['fms', 'ocs', 'kasir', 'marketing'],
        'DV_06' => self::ALL_MODULES,
        'DV_07' => self::ALL_MODULES,
        'DV_25' => ['fms', 'kasir'],
    ];

    /**
     * Endpoint API Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // 1. Cari user di erp_users + join data karyawan
        $user = DB::table('erp_users as eu')
            ->select(
                'eu.id',
                'eu.email',
                'eu.password',
                'eu.erp_role',
                'eu.allowed_modules', // JSONB dari DB
                'eu.is_active',
                'eu.karyawan_id',
                'mk.nama_karyawan',
                'mk.level as level_code',
                'ml.level as level_name',
                'ml.level_sequence',
                'mk.div_id',
                'md.div_name',
                'mk.aktif as karyawan_aktif'
            )
            ->leftJoin('m_karyawan as mk', 'mk.id', '=', 'eu.karyawan_id')
            ->leftJoin('m_level as ml', 'ml.level_code', '=', 'mk.level')
            ->leftJoin('m_division as md', 'md.div_code', '=', 'mk.div_id')
            ->whereRaw('LOWER(eu.email) = ?', [strtolower($email)])
            ->first();

        // 2. Validasi Keberadaan Email & Password
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'error' => 'Email tidak terdaftar sebagai pengguna sistem ERP',
                'code' => 'EMAIL_NOT_FOUND'
            ], 401);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Password yang Anda masukkan salah',
                'code' => 'INVALID_PASSWORD'
            ], 401);
        }

        // 3. Cek status aktif (erp_users dan m_karyawan)
        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'error' => 'Akun ERP Anda dinonaktifkan. Hubungi Administrator.',
                'code' => 'ACCOUNT_INACTIVE'
            ], 401);
        }

        if ($user->karyawan_id && $user->karyawan_aktif !== 'Y') {
            return response()->json([
                'status' => 'error',
                'error' => 'Status Karyawan Anda tidak aktif.',
                'code' => 'ACCOUNT_INACTIVE'
            ], 401);
        }

        // 4. Resolve module access
        $role = $user->erp_role ?: 'user';
        $levelSequence = (int) ($user->level_sequence ?: 0);
        $divisionCode = $user->div_id ?: '';

        // Decode JSONB allowed_modules
        $customModules = [];
        if (!empty($user->allowed_modules)) {
            // Karena ini string dari DB (JSONB), kita decode menjadi array
            $decoded = json_decode($user->allowed_modules, true);
            if (is_array($decoded)) {
                $customModules = $decoded;
            }
        }

        $allowedModules = $this->resolveModuleAccess($role, $levelSequence, $divisionCode, $customModules);

        // 5. Update last_login_at
        DB::table('erp_users')
            ->where('id', $user->id)
            ->update(['last_login_at' => now()]);

        // 6. Build Data User yang Akan Dikembalikan
        $authUser = [
            'id' => (int) $user->id,
            'name' => $user->nama_karyawan ?: explode('@', $user->email)[0],
            'email' => $user->email,
            'role' => $role,
            'level' => $user->level_name ?: 'Unknown',
            'levelSequence' => $levelSequence,
            'division' => $user->div_name ?: 'Unknown',
            'divisionCode' => $divisionCode,
            'allowedModules' => $allowedModules,
        ];

        // 7. Generate JWT Token
        try {
            $userModel = \App\Models\User::find($user->id);
            if (!$userModel) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'User model not found for ID: ' . $user->id,
                    'code' => 'USER_NOT_FOUND'
                ], 404);
            }
            $token = \Illuminate\Support\Facades\Auth::guard('api')->login($userModel);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Server Auth Error: ' . $e->getMessage(),
                'code' => 'SERVER_AUTH_ERROR',
                'debug_trace' => $e->getTraceAsString(),
                'debug_file' => $e->getFile(),
                'debug_line' => $e->getLine()
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $authUser,
                'token' => $token
            ]
        ], 200);
    }

    /**
     * Helper Method: Resolve Modul yang bisa diakses user
     */
    private function resolveModuleAccess(string $role, int $levelSequence, string $divisionCode, array $customModules): array
    {
        // 1. Jika ada custom override bintang (all access)
        if (in_array('*', $customModules) || in_array($role, self::ADMIN_ROLES)) {
            return self::ALL_MODULES;
        }

        $modules = [];

        // 2. Terapkan custom modules jika tidak kosong
        if (count($customModules) > 0) {
            foreach ($customModules as $m) {
                if (in_array($m, self::ALL_MODULES)) {
                    $modules[] = $m;
                }
            }
        } else {
            // 3. Fallback ke Role Spesifik
            if (array_key_exists($role, self::ROLE_MODULE_MAP)) {
                $modules = self::ROLE_MODULE_MAP[$role];
            } else {
                // 4. Fallback ke Divisi jika role biasa
                if (array_key_exists($divisionCode, self::DIVISION_MODULE_MAP)) {
                    $modules = self::DIVISION_MODULE_MAP[$divisionCode];
                }
            }
        }

        $modules = array_unique($modules);

        // 5. Khusus OCS: cek level minimum (Supervisor = sequence 4)
        if (in_array('ocs', $modules) && $levelSequence < self::OCS_MIN_LEVEL_SEQUENCE) {
            $modules = array_values(array_filter($modules, fn($m) => $m !== 'ocs'));
        }

        return array_values($modules);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout()
    {
        \Illuminate\Support\Facades\Auth::guard('api')->logout();
        return $this->successResponse(null, 'Successfully logged out');
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh()
    {
        $token = \Illuminate\Support\Facades\Auth::guard('api')->refresh();
        return $this->respondWithToken($token, 'Token refreshed successfully');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me()
    {
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if ($user) {
            $user->load('employee.department');
        }
        return $this->successResponse($user);
    }

    /**
     * Build token response (for refresh).
     */
    protected function respondWithToken(string $token, string $message = 'Login successful')
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => \Illuminate\Support\Facades\Auth::guard('api')->factory()->getTTL() * 60,
            'user'         => \Illuminate\Support\Facades\Auth::guard('api')->user(),
        ], $message);
    }
}
