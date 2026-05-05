<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Hris\AttendanceController;
use App\Http\Controllers\Api\V1\Hris\DashboardController;
use App\Http\Controllers\Api\V1\Hris\EmployeeController;
use App\Http\Controllers\Api\V1\Hris\LeaveController;
use App\Http\Controllers\Api\V1\Hris\RecruitmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — ERP BCS Backend
|--------------------------------------------------------------------------
*/

// ── Auth (Public) ───────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// ── Protected Routes (JWT) ──────────────────────────────
Route::prefix('v1')->middleware('auth:api')->group(function () {

    // Auth (authenticated)
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // ── HRIS Module ─────────────────────────────────────

    // Dashboard
    Route::prefix('hris/dashboard')->group(function () {
        Route::get('/metrics', [DashboardController::class, 'metrics']);
        Route::get('/attendance-trend', [DashboardController::class, 'attendanceTrend']);
        Route::get('/anniversaries', [DashboardController::class, 'anniversaries']);
        Route::get('/activities', [DashboardController::class, 'activities']);
    });

    // Employees
    Route::get('/hris/employees', [EmployeeController::class, 'index']);
    Route::post('/hris/employees', [EmployeeController::class, 'store']);
    Route::get('/hris/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/hris/employees/{id}', [EmployeeController::class, 'update']);

    // Attendance
    Route::get('/hris/attendance', [AttendanceController::class, 'index']);
    Route::get('/hris/attendance/stats', [AttendanceController::class, 'stats']);

    // Leave Requests
    Route::get('/hris/leaves', [LeaveController::class, 'index']);
    Route::get('/hris/leaves/stats', [LeaveController::class, 'stats']);
    Route::put('/hris/leaves/{id}/status', [LeaveController::class, 'updateStatus']);

    // Recruitment
    Route::get('/hris/recruitment/pipeline', [RecruitmentController::class, 'pipeline']);
    Route::put('/hris/recruitment/candidates/{id}/stage', [RecruitmentController::class, 'updateStage']);
});
