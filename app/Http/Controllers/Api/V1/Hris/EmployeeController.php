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
        $employees = [
            [
                'id'         => 'EMP-001',
                'name'       => 'Budi Santoso',
                'role'       => 'Manager Operations',
                'department' => 'Operations',
                'email'      => 'budi.s@bcslabs.tech',
                'status'     => 'Active',
                'joinDate'   => '2020-01-15',
                'avatar'     => 'https://ui-avatars.com/api/?name=Budi+Santoso'
            ],
            [
                'id'         => 'EMP-002',
                'name'       => 'Siti Aminah',
                'role'       => 'Senior Engineer',
                'department' => 'Engineering',
                'email'      => 'siti.a@bcslabs.tech',
                'status'     => 'Active',
                'joinDate'   => '2021-03-10',
                'avatar'     => 'https://ui-avatars.com/api/?name=Siti+Aminah'
            ]
        ];

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
