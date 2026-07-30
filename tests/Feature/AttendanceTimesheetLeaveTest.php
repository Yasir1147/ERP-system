<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\EmployeeLeave;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('leave ranges appear as leave on every covered timesheet date while attendance records take precedence', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = Employee::query()->create([
        'code' => '1003',
        'name' => 'Timesheet Leave Employee',
        'profession' => 'Supervisor',
        'type' => 'rope_access',
        'status' => Employee::STATUS_ACTIVE,
    ]);

    EmployeeLeave::query()->create([
        'employee_id' => $employee->id,
        'created_by' => $admin->id,
        'start_date' => '2026-07-20',
        'end_date' => '2026-07-22',
        'reason' => 'Sick leave',
    ]);

    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => null,
        'attendance_date' => '2026-07-21',
        'status' => AttendanceRecord::STATUS_ABSENT,
        'has_overtime' => false,
    ]);

    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => null,
        'attendance_date' => '2026-07-23',
        'status' => AttendanceRecord::STATUS_PRESENT,
        'has_overtime' => false,
    ]);

    $this->actingAs($admin)
        ->get('/attendance/timesheet?type=rope_access&month=2026-07')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Timesheet')
            ->where('employees.0.days.19.status', AttendanceRecord::STATUS_LEAVE)
            ->where('employees.0.days.19.leaveReason', 'Sick leave')
            ->where('employees.0.days.20.status', AttendanceRecord::STATUS_ABSENT)
            ->where('employees.0.days.21.status', AttendanceRecord::STATUS_LEAVE)
            ->where('employees.0.presentDays', 1));
});

test('half day attendance counts as half a present day in timesheet and payroll', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = Employee::query()->create([
        'code' => '1099',
        'name' => 'Half Day Employee',
        'profession' => 'Technician',
        'type' => 'rope_access',
        'status' => Employee::STATUS_ACTIVE,
    ]);

    EmployeePayrollSetting::query()->create([
        'employee_id' => $employee->id,
        'daily_salary' => 100,
        'salary_rule' => EmployeePayrollSetting::RULE_PRESENT_DAYS,
        'standard_hours_per_day' => 8,
        'is_overtime_enabled' => true,
    ]);

    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => null,
        'attendance_date' => '2026-07-30',
        'status' => AttendanceRecord::STATUS_PRESENT,
        'attendance_fraction' => AttendanceRecord::HALF_DAY_FRACTION,
        'has_overtime' => false,
    ]);

    $this->actingAs($admin)
        ->get('/attendance/timesheet?type=rope_access&month=2026-07')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Timesheet')
            ->where('employees.0.days.29.status', AttendanceRecord::STATUS_PRESENT)
            ->where('employees.0.days.29.attendanceFraction', AttendanceRecord::HALF_DAY_FRACTION)
            ->where('employees.0.presentDays', AttendanceRecord::HALF_DAY_FRACTION)
            ->where('employees.0.halfDays', 1));

    $this->actingAs($admin)
        ->get('/payroll/report?type=rope_access&employee_id='.$employee->id.'&month=2026-07')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Report')
            ->where('payrollRows.0.presentDays', AttendanceRecord::HALF_DAY_FRACTION)
            ->where('payrollRows.0.halfDays', 1)
            ->where('payrollRows.0.basicSalary', 50));
});
