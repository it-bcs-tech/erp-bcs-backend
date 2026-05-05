<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Create Departments ───────────────────────
        $departments = collect([
            'Engineering', 'Marketing', 'Human Resources', 'Finance',
            'Operations', 'Sales', 'Product', 'Legal',
        ])->map(fn($name) => Department::create([
            'name'        => $name,
            'description' => "The {$name} department of BCS Logistics.",
        ]));

        // ── 2. Create Admin User + Employee ─────────────
        $adminUser = User::create([
            'name'     => 'Admin BCS',
            'email'    => 'admin@bcs-logistics.co.id',
            'password' => bcrypt('password123'),
        ]);

        $adminEmployee = Employee::create([
            'user_id'       => $adminUser->id,
            'department_id' => $departments->firstWhere('name', 'Human Resources')->id,
            'employee_code' => 'EMP-0001',
            'name'          => 'Admin BCS',
            'email'         => 'admin@bcs-logistics.co.id',
            'phone'         => '081234567890',
            'role'          => 'HR Director',
            'status'        => 'Active',
            'join_date'     => '2020-01-15',
            'birth_date'    => '1985-06-20',
            'leave_balance' => 12,
            'performance_score' => 4.5,
        ]);

        // ── 3. Create Employees ─────────────────────────
        $employeeData = [
            ['Adi Nugroho',    'adi.nugroho@bcs.co.id',    'Engineering',      'Senior Backend Developer',  '2021-03-10', '1990-04-15'],
            ['Siti Rahma',     'siti.rahma@bcs.co.id',     'Engineering',      'Frontend Developer',        '2022-06-01', '1993-08-22'],
            ['Budi Santoso',   'budi.santoso@bcs.co.id',   'Marketing',        'Marketing Manager',         '2019-11-20', '1988-12-05'],
            ['Dewi Lestari',   'dewi.lestari@bcs.co.id',   'Finance',          'Financial Analyst',         '2023-01-15', '1995-02-28'],
            ['Raka Pratama',   'raka.pratama@bcs.co.id',   'Operations',       'Operations Lead',           '2020-07-08', '1991-09-10'],
            ['Maya Putri',     'maya.putri@bcs.co.id',     'Human Resources',  'HR Specialist',             '2021-09-12', '1994-11-03'],
            ['Fajar Hidayat',  'fajar.hidayat@bcs.co.id',  'Sales',            'Sales Executive',           '2022-02-14', '1992-05-17'],
            ['Lina Marlina',   'lina.marlina@bcs.co.id',   'Product',          'Product Manager',           '2020-04-22', '1989-07-30'],
            ['Dimas Arya',     'dimas.arya@bcs.co.id',     'Engineering',      'DevOps Engineer',           '2023-08-05', '1996-01-12'],
            ['Anisa Fitri',    'anisa.fitri@bcs.co.id',    'Legal',            'Legal Counsel',             '2021-05-18', '1990-10-25'],
            ['Hendra Wijaya',  'hendra.wijaya@bcs.co.id',  'Engineering',      'Mobile Developer',          '2022-11-30', '1993-03-08'],
            ['Putri Ayu',      'putri.ayu@bcs.co.id',      'Marketing',        'Content Specialist',        '2023-04-10', '1997-06-14'],
            ['Agus Setiawan',  'agus.setiawan@bcs.co.id',  'Finance',          'Accounting Manager',        '2018-09-01', '1986-12-20'],
            ['Rina Susanti',   'rina.susanti@bcs.co.id',   'Operations',       'Logistics Coordinator',     '2022-07-25', '1994-08-11'],
            ['Yusuf Rahman',   'yusuf.rahman@bcs.co.id',   'Sales',            'Business Development',      '2021-12-03', '1991-04-28'],
        ];

        $employees = [$adminEmployee];
        foreach ($employeeData as $data) {
            $user = User::create([
                'name'     => $data[0],
                'email'    => $data[1],
                'password' => bcrypt('password123'),
            ]);

            $dept = $departments->firstWhere('name', $data[2]);

            $employees[] = Employee::create([
                'user_id'       => $user->id,
                'department_id' => $dept->id,
                'manager_id'    => $adminEmployee->id,
                'employee_code' => 'EMP-' . str_pad(count($employees) + 1, 4, '0', STR_PAD_LEFT),
                'name'          => $data[0],
                'email'         => $data[1],
                'phone'         => '08' . rand(1000000000, 9999999999),
                'role'          => $data[3],
                'status'        => 'Active',
                'join_date'     => $data[4],
                'birth_date'    => $data[5],
                'leave_balance' => rand(5, 12),
                'performance_score' => round(rand(30, 50) / 10, 1),
            ]);
        }

        // ── 4. Create Attendance Logs (last 6 months) ───
        $statuses   = ['On Time', 'On Time', 'On Time', 'Late', 'On Time']; // weighted
        $workTypes  = ['On-Site', 'On-Site', 'On-Site', 'Remote'];          // weighted

        for ($m = 5; $m >= 0; $m--) {
            $monthStart = Carbon::now()->subMonths($m)->startOfMonth();
            $monthEnd   = Carbon::now()->subMonths($m)->endOfMonth();
            if ($monthEnd->gt(Carbon::today())) $monthEnd = Carbon::today();

            $date = $monthStart->copy();
            while ($date->lte($monthEnd)) {
                if ($date->isWeekday()) {
                    foreach ($employees as $emp) {
                        // 90% chance of attendance
                        if (rand(1, 100) <= 90) {
                            $status   = $statuses[array_rand($statuses)];
                            $workType = $workTypes[array_rand($workTypes)];
                            $checkIn  = $date->copy()->setTime(rand(7, 9), rand(0, 59));

                            AttendanceLog::create([
                                'employee_id' => $emp->id,
                                'date'        => $date->toDateString(),
                                'check_in'    => $checkIn,
                                'check_out'   => $checkIn->copy()->addHours(rand(8, 9))->addMinutes(rand(0, 30)),
                                'status'      => $status,
                                'work_type'   => $workType,
                            ]);
                        }
                    }
                }
                $date->addDay();
            }
        }

        // ── 5. Create Leave Requests ────────────────────
        $leaveTypes   = ['Annual', 'Sick', 'Personal'];
        $leaveStatuses = ['Pending', 'Approved', 'Approved', 'Rejected'];

        for ($i = 0; $i < 20; $i++) {
            $emp       = $employees[array_rand($employees)];
            $startDate = Carbon::now()->subDays(rand(0, 60));
            $endDate   = $startDate->copy()->addDays(rand(1, 5));

            LeaveRequest::create([
                'employee_id' => $emp->id,
                'type'        => $leaveTypes[array_rand($leaveTypes)],
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'reason'      => 'Leave request reason #' . ($i + 1),
                'status'      => $leaveStatuses[array_rand($leaveStatuses)],
            ]);
        }

        // ── 6. Create Recruitment Jobs & Candidates ─────
        $jobs = [
            ['title' => 'Senior Laravel Developer',    'dept' => 'Engineering'],
            ['title' => 'UI/UX Designer',              'dept' => 'Product'],
            ['title' => 'Digital Marketing Specialist', 'dept' => 'Marketing'],
        ];

        $candidateNames = [
            'Ahmad Fauzi', 'Bella Christina', 'Charlie Wibowo',
            'Diana Sari', 'Eko Prasetyo', 'Fitria Handayani',
            'Galih Pratama', 'Hani Rahayu', 'Ivan Kurniawan',
            'Julia Anggraini', 'Kevin Susanto', 'Laila Nur',
        ];

        $stages = ['Applied', 'Screening', 'Interview', 'Offered'];

        foreach ($jobs as $jobData) {
            $dept = $departments->firstWhere('name', $jobData['dept']);
            $job  = RecruitmentJob::create([
                'title'           => $jobData['title'],
                'department_id'   => $dept->id,
                'description'     => "We're looking for a talented {$jobData['title']} to join our {$jobData['dept']} team.",
                'requirements'    => "3+ years experience, strong communication skills.",
                'location'        => 'Jakarta',
                'employment_type' => 'Full-time',
                'status'          => 'Open',
            ]);

            // Create 4 candidates per job
            for ($c = 0; $c < 4; $c++) {
                $name = $candidateNames[array_rand($candidateNames)];
                RecruitmentCandidate::create([
                    'job_id'         => $job->id,
                    'name'           => $name,
                    'email'          => strtolower(str_replace(' ', '.', $name)) . '@gmail.com',
                    'phone'          => '08' . rand(1000000000, 9999999999),
                    'role'           => $jobData['title'],
                    'experience'     => rand(1, 8) . ' years',
                    'pipeline_stage' => $stages[$c],
                ]);
            }
        }

        // ── 7. Create Activity Logs ─────────────────────
        $activities = [
            ['employee_joined',  'Adi Nugroho joined as Senior Backend Developer'],
            ['leave_approved',   "Siti Rahma's Annual leave has been approved"],
            ['policy_changed',   'Remote work policy updated for Engineering team'],
            ['employee_joined',  'Dimas Arya joined as DevOps Engineer'],
            ['leave_approved',   "Budi Santoso's Sick leave has been approved"],
        ];

        foreach ($activities as $i => $act) {
            ActivityLog::create([
                'type'        => $act[0],
                'description' => $act[1],
                'employee_id' => $employees[$i + 1]->id ?? $employees[0]->id,
                'created_at'  => Carbon::now()->subHours(rand(1, 72)),
            ]);
        }

        $this->command->info('✅ ERP BCS HRIS database seeded successfully!');
        $this->command->info('   Login: admin@bcs-logistics.co.id / password123');
    }
}
