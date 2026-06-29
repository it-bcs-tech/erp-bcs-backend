<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\LeaveRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/hris/employees
     * List all employees with search, filter, and pagination.
     */
    public function index(Request $request)
    {
        $query = Employee::query()
            ->leftJoin('m_title', 'm_karyawan.title', '=', 'm_title.title_code')
            ->leftJoin('m_dept', 'm_karyawan.dept_id', '=', 'm_dept.dept_code')
            ->select(
                'm_karyawan.*',
                'm_title.title as job_title_name',
                'm_dept.dept_name as department_name'
            );

        // Jika filter is_driver aktif, cari yang mengandung DRIVER
        if ($request->get('is_driver') === 'true' || $request->get('title') === 'DRIVER') {
            $query->where('m_title.title', 'ILIKE', '%DRIVER%');
        }

        // Filter by status (aktif)
        if ($status = $request->get('status')) {
            $aktif = $status === 'Active' ? 'Y' : 'N';
            $query->where('m_karyawan.aktif', $aktif);
        }

        // Search by name
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('m_karyawan.nama_karyawan', 'ILIKE', "%{$search}%")
                  ->orWhere('m_karyawan.payroll_id', 'ILIKE', "%{$search}%")
                  ->orWhere('m_karyawan.telp1', 'ILIKE', "%{$search}%");
            });
        }

        $employees = $query->orderBy('nama_karyawan')->get()->map(function ($emp) {
            $status = ($emp->aktif == 'Y') ? 'Active' : 'Inactive';
            
            // Resolve SIM Information dynamically (B2 Umum > B1 > A)
            $licenseType = '-';
            $licenseExpiry = null;
            if (!empty($emp->no_sim_b2_umum)) {
                $licenseType = 'SIM B2 Umum';
                $licenseExpiry = $emp->no_sim_b2_umum_expiredate;
            } elseif (!empty($emp->no_sim_b1)) {
                $licenseType = 'SIM B1';
                $licenseExpiry = $emp->no_sim_b1_expiredate;
            } elseif (!empty($emp->no_sim_a)) {
                $licenseType = 'SIM A';
                $licenseExpiry = $emp->no_sim_a_expiredate;
            }
            
            return [
                'id'            => $emp->id ? 'EMP-' . str_pad($emp->id, 3, '0', STR_PAD_LEFT) : 'Unknown',
                'name'          => $emp->nama_karyawan ?? $emp->nama ?? 'Unknown',
                'role'          => $emp->job_title_name ?? $emp->jabatan ?? 'Staff',
                'department'    => $emp->department_name ?? $emp->departemen ?? 'General',
                'email'         => $emp->email ?? strtolower(str_replace(' ', '.', $emp->nama_karyawan ?? 'user')) . '@bcslabs.tech',
                'phone'         => $emp->telp1 ?? $emp->telp2 ?? '-',
                'licenseType'   => $licenseType,
                'licenseExpiry' => $licenseExpiry,
                'status'        => $status,
                'joinDate'      => $emp->tgl_masuk ? \Carbon\Carbon::parse($emp->tgl_masuk)->format('Y-m-d') : '2020-01-15',
                'avatar'        => $emp->foto ?? 'https://ui-avatars.com/api/?name=' . urlencode($emp->nama_karyawan ?? 'User')
            ];
        });

        return $this->successResponse($employees, 'Employees retrieved successfully');
    }

    /**
     * POST /api/v1/hris/employees
     * Create a new employee.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:employees,email',
            'phone'         => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'manager_id'    => 'nullable|exists:employees,id',
            'role'          => 'required|string|max:255',
            'status'        => 'nullable|in:Active,Inactive,On Leave,Probation',
            'join_date'     => 'required|date',
            'birth_date'    => 'nullable|date',
            'address'       => 'nullable|string',
            'leave_balance' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        try {
            $employee = DB::transaction(function () use ($request) {
                // Create user account for the employee
                $user = User::create([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'password' => bcrypt('password123'), // Default password
                ]);

                // Generate employee code
                $lastCode = Employee::max('id') ?? 0;
                $employeeCode = 'EMP-' . str_pad($lastCode + 1, 4, '0', STR_PAD_LEFT);

                // Create employee record
                $employee = Employee::create([
                    'user_id'       => $user->id,
                    'department_id' => $request->department_id,
                    'manager_id'    => $request->manager_id,
                    'employee_code' => $employeeCode,
                    'name'          => $request->name,
                    'email'         => $request->email,
                    'phone'         => $request->phone,
                    'role'          => $request->role,
                    'status'        => $request->status ?? 'Active',
                    'join_date'     => $request->join_date,
                    'birth_date'    => $request->birth_date,
                    'address'       => $request->address,
                    'leave_balance' => $request->leave_balance ?? 12,
                ]);

                // Log activity
                ActivityLog::create([
                    'type'        => 'employee_joined',
                    'description' => "{$employee->name} joined as {$employee->role}",
                    'employee_id' => $employee->id,
                ]);

                return $employee;
            });

            $employee->load('department:id,name', 'manager:id,name');

            return $this->successResponse($employee, 'Employee created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create employee: ' . $e->getMessage(),
                'ERR_SERVER',
                500
            );
        }
    }

    /**
     * GET /api/v1/hris/employees/{id}
     * Show full employee profile.
     */
    public function show(string $id)
    {
        $id = str_replace('EMP-', '', $id);

        $employee = Employee::find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', 'ERR_NOT_FOUND', 404);
        }

        // Get job title from m_title table
        $jobTitle = 'Staff';
        if (!empty($employee->title)) {
            $titleRecord = DB::connection('pgsql_master')
                ->table('m_title')
                ->where('title_code', $employee->title)
                ->first();
            if ($titleRecord) {
                $jobTitle = $titleRecord->title;
            } else {
                $jobTitle = $employee->title;
            }
        }

        // Get department from m_dept table
        $departmentName = 'General';
        if (!empty($employee->dept_id)) {
            $deptRecord = DB::connection('pgsql_master')
                ->table('m_dept')
                ->where('dept_code', $employee->dept_id)
                ->first();
            if ($deptRecord) {
                $departmentName = $deptRecord->dept_name;
            }
        }

        // SIM License information (same logic as index)
        $licenseType = '-';
        $licenseExpiry = null;
        if (!empty($employee->no_sim_b2_umum)) {
            $licenseType = 'SIM B2 Umum';
            $licenseExpiry = $employee->no_sim_b2_umum_expiredate;
        } elseif (!empty($employee->no_sim_b1)) {
            $licenseType = 'SIM B1';
            $licenseExpiry = $employee->no_sim_b1_expiredate;
        } elseif (!empty($employee->no_sim_a)) {
            $licenseType = 'SIM A';
            $licenseExpiry = $employee->no_sim_a_expiredate;
        }

        // Calculate leaves from LeaveRequest using user_id mapping
        $leaveUsed = LeaveRequest::where('user_id', $employee->id)
            ->where('status', 'Approved')
            ->count();
            
        $leaveBalance = 12; // Default limit
        $leaveRemaining = $leaveBalance - $leaveUsed;

        // 1. Performance Average from hris.performance_kpi
        $perfAvg = DB::connection('pgsql')->table('hris.performance_kpi')
            ->where('payroll_id', $employee->payroll_id)
            ->where('kpi_type', 'PERSONAL')
            ->avg('score');
        $performanceVal = $perfAvg !== null ? (string) round($perfAvg, 1) : "0.0";

        // 2. Leave Balance from presensi.leave_balances
        $leaveBalanceVal = 12; // Default
        $erpUser = DB::connection('pgsql')->table('erp_users')->where('karyawan_id', $employee->id)->first();
        if ($erpUser) {
            $lb = DB::connection('pgsql_presensi')->table('leave_balances')
                ->where('user_id', $erpUser->id)
                ->first();
            if ($lb) {
                $leaveBalanceVal = max(0, $lb->quota - $lb->used);
            }
        }

        // 3. Manager name from master.m_atasan (title-based hierarchy)
        $managerName = 'No Manager Assigned';
        if (!empty($employee->title)) {
            $atasan = DB::connection('pgsql_master')->table('m_atasan as a')
                ->join('m_karyawan as k', 'a.title_atasan', '=', 'k.title')
                ->where('a.title_bawahan', $employee->title)
                ->where('k.aktif', 'Y')
                ->select('k.nama_karyawan')
                ->first();
            if ($atasan) {
                $managerName = $atasan->nama_karyawan;
            }
        }

        // 4. Skills from hris.training_programs
        $skills = DB::connection('pgsql')->table('hris.training_participants as tp')
            ->join('hris.training_programs as t', 't.id', '=', 'tp.program_id')
            ->where('tp.payroll_id', $employee->payroll_id)
            ->whereNotNull('t.category')
            ->distinct()
            ->pluck('t.category')
            ->toArray();

        // 5. Timeline from hris.employee_lifecycle
        $timeline = DB::connection('pgsql')->table('hris.employee_lifecycle')
            ->where('payroll_id', $employee->payroll_id)
            ->orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                $type = $t->action_type;
                if (strcasecmp($type, 'MUTASI') === 0 || strcasecmp($type, 'MUTATION') === 0) {
                    $type = 'Mutation';
                } elseif (strcasecmp($type, 'PROMOSI') === 0 || strcasecmp($type, 'PROMOTION') === 0) {
                    $type = 'Promotion';
                } elseif (strcasecmp($type, 'DEMOSI') === 0 || strcasecmp($type, 'DEMOTION') === 0) {
                    $type = 'Demotion';
                }
                return [
                    'type' => $type,
                    'desc' => $t->action_description ?? '',
                    'date' => $t->start_date ? \Carbon\Carbon::parse($t->start_date)->format('Y-m-d') : '',
                ];
            })
            ->toArray();

        // Build the formatted response with both camelCase and snake_case for maximum compatibility
        $data = [
            'id'            => 'EMP-' . str_pad($employee->id, 3, '0', STR_PAD_LEFT),
            'dbId'          => (int) $employee->id,
            'titleCode'     => $employee->title,
            'name'          => $employee->nama_karyawan ?? 'Unknown',
            'role'          => $jobTitle,
            'department'    => $departmentName,
            'email'         => $employee->email ?? (strtolower(str_replace(' ', '.', $employee->nama_karyawan ?? 'user')) . '@bcslabs.tech'),
            'phone'         => $employee->telp1 ?? $employee->telp2 ?? '-',
            'status'        => ($employee->aktif == 'Y') ? 'Active' : 'Inactive',
            'join_date'     => $employee->tgl_masuk ? \Carbon\Carbon::parse($employee->tgl_masuk)->format('Y-m-d') : '2020-01-15',
            'joinDate'      => $employee->tgl_masuk ? \Carbon\Carbon::parse($employee->tgl_masuk)->format('Y-m-d') : '2020-01-15',
            'birth_date'    => $employee->tgl_lahir ? \Carbon\Carbon::parse($employee->tgl_lahir)->format('Y-m-d') : null,
            'birthDate'     => $employee->tgl_lahir ? \Carbon\Carbon::parse($employee->tgl_lahir)->format('Y-m-d') : null,
            'address'       => $employee->alamat_ktp ?? $employee->alamat_ktp2 ?? '-',
            'avatar'        => $employee->foto ?? ('https://ui-avatars.com/api/?name=' . urlencode($employee->nama_karyawan ?? 'User')),
            'licenseType'   => $licenseType,
            'licenseExpiry' => $licenseExpiry,
            'leave_used'    => $leaveUsed,
            'leave_balance' => $leaveBalance,
            'leave_remaining' => $leaveRemaining,
            
            // New Issue #10 fields:
            'performance'   => $performanceVal,
            'leaveBalance'  => (int) $leaveBalanceVal,
            'manager'       => $managerName,
            'skills'        => $skills,
            'timeline'      => $timeline,
            
            'subordinates'  => [],   // Not supported in m_karyawan table
        ];

        return $this->successResponse($data);
    }

    /**
     * PUT /api/v1/hris/employees/{id}
     * Update employee data.
     */
    public function update(Request $request, string $id)
    {
        $id = str_replace('EMP-', '', $id);

        $employee = Employee::find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', 'ERR_NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|unique:pgsql_master.m_karyawan,email,' . $id,
            'phone'         => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:pgsql_master.m_dept,dept_code',
            'role'          => 'sometimes|string|max:255',
            'status'        => 'nullable|in:Active,Inactive',
            'join_date'     => 'sometimes|date',
            'birth_date'    => 'nullable|date',
            'address'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        // Map input fields to m_karyawan table columns
        $updateData = [];

        if ($request->has('name')) {
            $updateData['nama_karyawan'] = $request->name;
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('phone')) {
            $updateData['telp1'] = $request->phone;
        }
        if ($request->has('department_id')) {
            $updateData['dept_id'] = $request->department_id;
        }
        if ($request->has('role')) {
            $updateData['title'] = $request->role;
            $updateData['jabatan'] = $request->role; // Keep both updated
        }
        if ($request->has('status')) {
            $updateData['aktif'] = $request->status === 'Active' ? 'Y' : 'N';
        }
        if ($request->has('join_date')) {
            $updateData['tgl_masuk'] = $request->join_date;
        }
        if ($request->has('birth_date')) {
            $updateData['tgl_lahir'] = $request->birth_date;
        }
        if ($request->has('address')) {
            $updateData['alamat_ktp'] = $request->address;
        }

        if (!empty($updateData)) {
            $employee->update($updateData);
        }

        // Sync user email if changed (using safe direct DB update since user relation has no user_id column in m_karyawan)
        if ($request->has('email')) {
            DB::table('erp_users')
                ->where('karyawan_id', $employee->id)
                ->update(['email' => $request->email]);
        }

        // Return the updated employee details using the same show logic format
        return $this->show($id);
    }
}
