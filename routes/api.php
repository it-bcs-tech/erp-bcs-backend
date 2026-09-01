<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Hris\AttendanceController;
use App\Http\Controllers\Api\V1\Hris\DashboardController;
use App\Http\Controllers\Api\V1\Hris\EmployeeController;
use App\Http\Controllers\Api\V1\Hris\LeaveController;
use App\Http\Controllers\Api\V1\Hris\LifecycleController;
use App\Http\Controllers\Api\V1\Hris\PerformanceController;
use App\Http\Controllers\Api\V1\Hris\PayrollController;
use App\Http\Controllers\Api\V1\Hris\LoanController;
use App\Http\Controllers\Api\V1\Hris\RecruitmentController;
use App\Http\Controllers\Api\V1\Fms\DriverController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
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

// Temporary Public Test Routes Removed

// ── Protected Routes (JWT) ──────────────────────────────
Route::prefix('v1')->middleware('auth:api')->group(function () {

    // Auth (authenticated)
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // ── HRIS Module ─────────────────────────────────────

    // Dashboard
    Route::prefix('hris/dashboard')->group(function () {
        Route::get('/overview', [DashboardController::class, 'overview']);
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
    Route::get('/hris/attendance/overtimes', [AttendanceController::class, 'overtimes']);
    Route::post('/hris/attendance/overtimes', [AttendanceController::class, 'storeOvertime']);
    Route::post('/hris/attendance/overtimes/{id}/approve', [AttendanceController::class, 'approveOvertime']);
    Route::post('/hris/attendance/overtimes/{id}/reject', [AttendanceController::class, 'rejectOvertime']);
    Route::get('/hris/attendance/roster', [AttendanceController::class, 'roster']);
    Route::put('/hris/attendance/roster/{employeeId}', [AttendanceController::class, 'updateRoster']);

    // Leave Requests
    Route::get('/hris/leaves', [LeaveController::class, 'index']);
    Route::get('/hris/leaves/stats', [LeaveController::class, 'stats']);
    Route::post('/hris/leaves', [LeaveController::class, 'store']);
    Route::post('/hris/leaves/{id}/approve', [LeaveController::class, 'approve']);
    Route::post('/hris/leaves/{id}/reject', [LeaveController::class, 'reject']);
    Route::get('/hris/leaves/balances/{id}', [LeaveController::class, 'balance']);
    Route::put('/hris/leaves/{id}/status', [LeaveController::class, 'updateStatus']);

    // Recruitment
    Route::get('/hris/recruitment/pipeline', [RecruitmentController::class, 'pipeline']);
    Route::put('/hris/recruitment/candidates/{id}/stage', [RecruitmentController::class, 'updateStage']);

    // Lifecycle
    Route::get('/hris/lifecycle', [LifecycleController::class, 'index']);
    Route::post('/hris/lifecycle', [LifecycleController::class, 'store']);

    // Performance
    Route::get('/hris/performance', [PerformanceController::class, 'index']);
    Route::post('/hris/performance/kpi', [PerformanceController::class, 'storeKpi']);
    Route::post('/hris/performance/training', [PerformanceController::class, 'storeTraining']);

    // Payroll & Reimbursements
    Route::get('/hris/payroll', [PayrollController::class, 'index']);
    Route::post('/hris/payroll/calculate', [PayrollController::class, 'calculate']);
    Route::post('/hris/payroll/commit', [PayrollController::class, 'commit']);
    Route::put('/hris/payroll/slips/{id}', [PayrollController::class, 'updateSlip']);
    Route::get('/hris/payroll/reimbursements', [PayrollController::class, 'reimbursements']);
    Route::post('/hris/payroll/reimbursements', [PayrollController::class, 'storeReimbursement']);
    Route::post('/hris/payroll/reimbursements/{id}/approve', [PayrollController::class, 'approveReimbursement']);
    Route::post('/hris/payroll/reimbursements/{id}/reject', [PayrollController::class, 'rejectReimbursement']);

    // Loans & Kasbon
    Route::get('/hris/payroll/loans', [LoanController::class, 'index']);
    Route::post('/hris/payroll/loans', [LoanController::class, 'store']);
    Route::post('/hris/payroll/loans/{id}/approve', [LoanController::class, 'approve']);
    Route::post('/hris/payroll/loans/{id}/reject', [LoanController::class, 'reject']);

    // ── FMS Module ──────────────────────────────────────
    Route::get('/fms/drivers', [DriverController::class, 'index']);

    // ── Admin Module ────────────────────────────────────
    Route::prefix('admin')->middleware('role:superadmin|administrator')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::patch('/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    });
});
