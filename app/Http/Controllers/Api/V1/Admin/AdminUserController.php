<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * 1. GET List Users
     */
    public function index(): JsonResponse
    {
        try {
            $usersList = DB::table('erp_users as eu')
                ->select(
                    'eu.id',
                    'eu.email',
                    'eu.erp_role',
                    'eu.allowed_modules',
                    'eu.is_active',
                    'eu.last_login_at',
                    'eu.created_at',
                    'mk.id as karyawan_id',
                    'mk.payroll_id as nik',
                    'mk.nama_karyawan',
                    'ml.level as level_name',
                    'mt.title as title_name'
                )
                ->leftJoin('m_karyawan as mk', 'mk.id', '=', 'eu.karyawan_id')
                ->leftJoin('m_level as ml', 'ml.level_code', '=', 'mk.level')
                ->leftJoin('m_title as mt', 'mt.title_code', '=', 'mk.title')
                ->orderBy('eu.id', 'desc')
                ->get();

            // Decode allowed_modules JSONB if necessary
            $formattedUsers = $usersList->map(function ($user) {
                // Di Laravel, is_active PostgreSQL biasanya di-cast ke boolean
                $user->is_active = (bool) $user->is_active; 
                return $user;
            });

            return response()->json([
                'status' => 'success',
                'data'   => $formattedUsers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

    /**
     * 2. POST Create User
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email'           => 'required|email',
            'password'        => 'required',
            'role'            => 'required|exists:pgsql_master.roles,name',
            'karyawan_id'     => 'nullable|integer',
            'allowed_modules' => 'nullable|string'
        ]);

        try {
            $email = trim($request->input('email'));
            $role = trim($request->input('role'));
            $password = $request->input('password');
            $customModulesStr = $request->input('allowed_modules', '[]');
            $karyawanId = $request->input('karyawan_id');

            // Cek duplikasi email
            $existing = DB::table('erp_users')
                ->where('email', $email)
                ->exists();

            if ($existing) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Email already registered in ERP'
                ], 400);
            }

            // Validasi string JSON untuk allowed_modules
            $allowedModules = '[]';
            if (json_decode($customModulesStr) !== null) {
                $allowedModules = $customModulesStr;
            }

            // Insert
            $userId = DB::table('erp_users')->insertGetId([
                'karyawan_id'     => $karyawanId ?: null,
                'email'           => $email,
                'password'        => Hash::make($password),
                'erp_role'        => $role,
                'allowed_modules' => $allowedModules, 
                'created_at'      => now(),
                'updated_at'      => now()
            ]);

            // Sync role to Spatie tables
            $userModel = \App\Models\User::find($userId);
            if ($userModel) {
                $userModel->syncRoles($role);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'User successfully created'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. PUT Update User
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'role'            => 'required|exists:pgsql_master.roles,name',
            'allowed_modules' => 'nullable|string',
            'reset_password'  => 'nullable|string'
        ]);

        try {
            $role = trim($request->input('role'));
            $customModulesStr = $request->input('allowed_modules', '[]');
            $resetPassword = $request->input('reset_password');

            // Validasi string JSON
            $allowedModules = '[]';
            if (json_decode($customModulesStr) !== null) {
                $allowedModules = $customModulesStr;
            }

            $updateData = [
                'erp_role'        => $role,
                'allowed_modules' => $allowedModules,
                'updated_at'      => now()
            ];

            // Jika ada permintaan reset password
            if (!empty(trim($resetPassword))) {
                $updateData['password'] = Hash::make($resetPassword);
            }

            DB::table('erp_users')
                ->where('id', $id)
                ->update($updateData);

            // Sync role to Spatie tables
            $userModel = \App\Models\User::find($id);
            if ($userModel) {
                $userModel->syncRoles($role);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'User updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. PATCH Toggle Status (Active/Inactive)
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'current_status' => 'required|boolean'
        ]);

        try {
            $currentStatus = filter_var($request->input('current_status'), FILTER_VALIDATE_BOOLEAN);
            $newStatus = !$currentStatus;

            DB::table('erp_users')
                ->where('id', $id)
                ->update([
                    'is_active'  => $newStatus,
                    'updated_at' => now()
                ]);

            $actionText = $newStatus ? 'activated' : 'deactivated';

            return response()->json([
                'status'  => 'success',
                'message' => "User successfully {$actionText}"
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
