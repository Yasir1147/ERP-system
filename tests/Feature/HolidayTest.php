<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function holidayAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function holidayEmployee(string $code, string $name, string $rule, float $dailySalary = 100, ?float $monthlySalary = null): Employee
{
    $employee = Employee::query()->create([
        'code' => $code,
        'name' => $name,
        'profession' => 'Rope Technician',
        'type' => 'rope_access',
        'status' => Employee::STATUS_ACTIVE,
    ]);

    EmployeePayrollSetting::query()->create([
        'employee_id' => $employee->id,
        'daily_salary' => $dailySalary,
        'monthly_salary' => $monthlySalary,
        'salary_rule' => $rule,
        'standard_hours_per_day' => 8,
        'is_overtime_enabled' => true,
    ]);

    return $employee;
}

function holidayProject(): Project
{
    return Project::query()->create([
        'name' => 'Holiday Tower',
        'status' => 'ongoing',
        'type' => 'rope_access',
    ]);
}

function holidayRecord(Employee $employee, ?Project $project, string $date, User $admin, int $overtime = 0, float $fraction = 1.0): void
{
    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => $project?->id,
        'attendance_date' => $date,
        'status' => AttendanceRecord::STATUS_PRESENT,
        'attendance_fraction' => $fraction,
        'overtime_hours' => $overtime,
        'submitted_by' => $admin->id,
    ]);
}

function payrollRowFor(Employee $employee, string $month, User $admin): array
{
    $page = test()->actingAs($admin)
        ->get('/payroll/report?type=rope_access&month='.$month)
        ->viewData('page');

    return collect($page['props']['payrollRows'])
        ->firstWhere('employeeId', $employee->id);
}

it('pays a present days employee for a holiday they did not work', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $employee = holidayEmployee('701', 'Present Days Worker', EmployeePayrollSetting::RULE_PRESENT_DAYS, 100);

    holidayRecord($employee, $project, '2026-08-03', $admin);
    holidayRecord($employee, $project, '2026-08-04', $admin);

    $before = payrollRowFor($employee, '2026-08-01', $admin);
    expect($before['presentDays'])->toBe(2.0)
        ->and($before['basicSalary'])->toBe(200.0);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'National Day',
        'is_paid' => true,
        'created_by' => $admin->id,
    ]);

    // The holiday is a paid day nobody worked, so it adds a payable day.
    $after = payrollRowFor($employee, '2026-08-01', $admin);
    expect($after['holidayDays'])->toBe(1)
        ->and($after['presentDays'])->toBe(3.0)
        ->and($after['basicSalary'])->toBe(300.0);
});

it('counts a holiday worked as overtime instead of a second paid day', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $employee = holidayEmployee('702', 'Came In Anyway', EmployeePayrollSetting::RULE_PRESENT_DAYS, 80);

    holidayRecord($employee, $project, '2026-08-03', $admin);
    holidayRecord($employee, $project, '2026-08-07', $admin);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'National Day',
        'is_paid' => true,
        'created_by' => $admin->id,
    ]);

    $row = payrollRowFor($employee, '2026-08-01', $admin);

    // One ordinary day plus the paid holiday = 2 payable days, never 3, and
    // the day he actually worked becomes 8 hours of overtime.
    expect($row['presentDays'])->toBe(2.0)
        ->and($row['holidayWorkedDays'])->toBe(1)
        ->and($row['overtimeHours'])->toBe(8)
        ->and($row['basicSalary'])->toBe(160.0)
        ->and($row['overtimeAmount'])->toBe(80.0);
});

it('keeps overtime already written against a worked holiday', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $employee = holidayEmployee('703', 'Long Holiday Shift', EmployeePayrollSetting::RULE_PRESENT_DAYS, 80);

    holidayRecord($employee, $project, '2026-08-07', $admin, overtime: 3);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'National Day',
        'is_paid' => true,
        'created_by' => $admin->id,
    ]);

    // 8 hours for the day itself, plus the 3 already recorded.
    $row = payrollRowFor($employee, '2026-08-01', $admin);
    expect($row['overtimeHours'])->toBe(11);
});

it('leaves a fixed 30 days salary alone but still pays the holiday overtime', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $employee = holidayEmployee('704', 'Fixed Month Worker', EmployeePayrollSetting::RULE_FIXED_30_DAYS, 100, 3000);

    holidayRecord($employee, $project, '2026-08-07', $admin);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'National Day',
        'is_paid' => true,
        'created_by' => $admin->id,
    ]);

    // The month is paid in full either way; only the overtime moves.
    $row = payrollRowFor($employee, '2026-08-01', $admin);
    expect($row['basicSalary'])->toBe(3000.0)
        ->and($row['overtimeHours'])->toBe(8);
});

it('does not pay an unpaid holiday', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $employee = holidayEmployee('705', 'Unpaid Holiday Worker', EmployeePayrollSetting::RULE_PRESENT_DAYS, 100);

    holidayRecord($employee, $project, '2026-08-03', $admin);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'Unpaid Closure',
        'is_paid' => false,
        'created_by' => $admin->id,
    ]);

    $row = payrollRowFor($employee, '2026-08-01', $admin);
    expect($row['holidayDays'])->toBe(0)
        ->and($row['presentDays'])->toBe(1.0);
});

it('applies a type-specific holiday only to that type', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $ropeAccess = holidayEmployee('706', 'Rope Worker', EmployeePayrollSetting::RULE_PRESENT_DAYS, 100);

    holidayRecord($ropeAccess, $project, '2026-08-03', $admin);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'Contracting Only Day',
        'is_paid' => true,
        'employee_type' => 'contracting',
        'created_by' => $admin->id,
    ]);

    $row = payrollRowFor($ropeAccess, '2026-08-01', $admin);
    expect($row['holidayDays'])->toBe(0)
        ->and($row['presentDays'])->toBe(1.0);
});

it('marks the holiday in the attendance grid', function () {
    $admin = holidayAdmin();
    $project = holidayProject();
    $employee = holidayEmployee('707', 'Grid Worker', EmployeePayrollSetting::RULE_PRESENT_DAYS, 100);

    holidayRecord($employee, $project, '2026-08-06', $admin);
    holidayRecord($employee, $project, '2026-08-10', $admin);

    Holiday::query()->create([
        'holiday_date' => '2026-08-07',
        'name' => 'National Day',
        'is_paid' => true,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=project&project_id='.$project->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page
            ->where('statement.matrix.dates.1.value', '2026-08-07')
            ->where('statement.matrix.dates.1.holiday', 'National Day')
            ->where('statement.matrix.people.0.cells.1.code', 'H')
            // A holiday is not a day this man went unlisted.
            ->where('statement.matrix.people.0.notListed', 0));
});

it('lets an admin declare and remove a holiday', function () {
    $admin = holidayAdmin();

    $this->actingAs($admin)
        ->post('/holidays', [
            'holiday_date' => '2026-12-02',
            'name' => 'National Day',
            'is_paid' => true,
            'employee_type' => '',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('holidays', ['name' => 'National Day', 'employee_type' => null]);

    $holiday = Holiday::query()->firstOrFail();

    $this->actingAs($admin)->delete('/holidays/'.$holiday->id)->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
});

it('refuses the same date twice for the same employees', function () {
    $admin = holidayAdmin();

    Holiday::query()->create([
        'holiday_date' => '2026-12-02',
        'name' => 'National Day',
        'is_paid' => true,
        'created_by' => $admin->id,
    ]);

    // Declaring it twice would pay the day twice.
    $this->actingAs($admin)
        ->post('/holidays', ['holiday_date' => '2026-12-02', 'name' => 'Duplicate', 'is_paid' => true, 'employee_type' => ''])
        ->assertSessionHasErrors('holiday_date');
});

it('blocks non-admin users from holidays', function () {
    $user = User::factory()->create(['role' => User::ROLE_ATTENDANCE]);

    $this->actingAs($user)->get('/holidays')->assertStatus(403);
    $this->actingAs($user)->post('/holidays', [])->assertStatus(403);
});
