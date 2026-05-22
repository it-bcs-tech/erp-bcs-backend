<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\ActivityLog;
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
            ->select('m_karyawan.*');

        // Jika filter is_driver aktif, lakukan JOIN ke m_title dan cari yang mengandung DRIVER
        if ($request->get('is_driver') === 'true' || $request->get('title') === 'DRIVER') {
            $query->join('m_title', 'm_karyawan.title', '=', 'm_title.title_code')
                  ->where('m_title.title', 'ILIKE', '%DRIVER%')
                  ->addSelect('m_title.title as job_title_name');
        }

        // Filter by status (aktif)
        if ($status = $request->get('status')) {
            $aktif = $status === 'Active' ? 'Y' : 'N';
            $query->where('aktif', $aktif);
        }

        // Search by name
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('nama_karyawan', 'ILIKE', "%{$search}%")
                  ->orWhere('payroll_id', 'ILIKE', "%{$search}%")
                  ->orWhere('telp1', 'ILIKE', "%{$search}%");
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
                'department'    => $emp->departemen ?? 'General',
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
        $employee = Employee::with([
            'department:id,name',
            'manager:id,name,role',
            'subordinates:id,manager_id,name,role',
        ])->find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', 'ERR_NOT_FOUND', 404);
        }

        // Add computed fields
        $data = $employee->toArray();
        $data['leave_used']     = $employee->leaveRequests()->where('status', 'Approved')->count();
        $data['leave_remaining'] = $employee->leave_balance - $data['leave_used'];

        return $this->successResponse($data);
    }

    /**
     * PUT /api/v1/hris/employees/{id}
     * Update employee data.
     */
    public function update(Request $request, string $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', 'ERR_NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|unique:employees,email,' . $id,
            'phone'         => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'manager_id'    => 'nullable|exists:employees,id',
            'role'          => 'sometimes|string|max:255',
            'status'        => 'nullable|in:Active,Inactive,On Leave,Probation',
            'join_date'     => 'sometimes|date',
            'birth_date'    => 'nullable|date',
            'address'       => 'nullable|string',
            'leave_balance' => 'nullable|integer',
            'performance_score' => 'nullable|numeric|min:0|max:5',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                'ERR_VALIDATION',
                422
            );
        }

        $employee->update($request->only([
            'name', 'email', 'phone', 'department_id', 'manager_id',
            'role', 'status', 'join_date', 'birth_date', 'address',
            'leave_balance', 'performance_score',
        ]));

        // Sync user email if changed
        if ($request->has('email') && $employee->user) {
            $employee->user->update(['email' => $request->email]);
        }

        $employee->load('department:id,name', 'manager:id,name');

        return $this->successResponse($employee, 'Employee updated successfully');
    }
}
