<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
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
