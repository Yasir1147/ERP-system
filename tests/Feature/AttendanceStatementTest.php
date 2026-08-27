<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function statementAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function statementEmployee(string $code, string $name, ?float $dailySalary = 100): Employee
{
    $employee = Employee::query()->create([
        'code' => $code,
        'name' => $name,
        'profession' => 'Rope Technician',
        'type' => 'rope_access',
        'status' => Employee::STATUS_ACTIVE,
    ]);

    if ($dailySalary !== null) {
        EmployeePayrollSetting::query()->create([
            'employee_id' => $employee->id,
            'daily_salary' => $dailySalary,
            'standard_hours_per_day' => 8,
            'is_overtime_enabled' => true,
        ]);
    }

    return $employee;
}

function statementProject(string $name = 'Statement Tower'): Project
{
    return Project::query()->create([
        'name' => $name,
        'project_code' => 'RA-'.substr(md5($name), 0, 6),
        'status' => 'ongoing',
        'type' => 'rope_access',
    ]);
}

function statementRecord(Employee $employee, ?Project $project, string $date, string $status, User $admin, int $overtime = 0, float $fraction = 1.0): AttendanceRecord
{
    return AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => $status === AttendanceRecord::STATUS_PRESENT ? $project?->id : null,
        'attendance_date' => $date,
        'status' => $status,
        'attendance_fraction' => $fraction,
        'overtime_hours' => $overtime,
        'submitted_by' => $admin->id,
    ]);
}

it('lists an employee day by day with its totals', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('601', 'Statement Worker');
    $project = statementProject();

    statementRecord($employee, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin, overtime: 3);
    statementRecord($employee, $project, '2026-08-04', AttendanceRecord::STATUS_PRESENT, $admin, fraction: 0.5);
    statementRecord($employee, null, '2026-08-05', AttendanceRecord::STATUS_ABSENT, $admin);

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Statement')
            ->where('statement.subject.name', 'Statement Worker')
            ->has('statement.rows', 3)
            ->where('statement.rows.0.date', '03/08/2026')
            ->where('statement.rows.0.projectName', 'Statement Tower')
            // A half day is half a present day, not a whole one.
            ->where('statement.totals.presentDays', 1.5)
            ->where('statement.totals.absent', 1)
            ->where('statement.totals.overtimeHours', 3));
});

it('shows leave range days that have no daily record', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('602', 'Leave Worker');

    EmployeeLeave::query()->create([
        'employee_id' => $employee->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
        'reason' => 'Home visit',
        'created_by' => $admin->id,
    ]);

    // Without this the statement would show a silent gap on the days the
    // employee was formally on leave.
    $this->actingAs($admin)
        ->get('/attendance/statement?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page
            ->has('statement.rows', 3)
            ->where('statement.totals.leave', 3)
            ->where('statement.rows.0.note', 'Home visit'));
});

it('keeps a real record for a day the leave range also covers', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('603', 'Worked Anyway');
    $project = statementProject();

    EmployeeLeave::query()->create([
        'employee_id' => $employee->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-11',
        'reason' => 'Home visit',
        'created_by' => $admin->id,
    ]);
    statementRecord($employee, $project, '2026-08-10', AttendanceRecord::STATUS_PRESENT, $admin);

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page
            ->has('statement.rows', 2)
            ->where('statement.totals.presentDays', 1)
            ->where('statement.totals.leave', 1));
});

it('hides salary until it is asked for', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('604', 'Paid Worker', 200);
    $project = statementProject();

    statementRecord($employee, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin, overtime: 4);

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page
            ->where('statement.withSalary', false)
            ->where('statement.rows.0.totalCost', null)
            ->where('statement.totals.totalCost', null));

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31&with_salary=1')
        ->assertInertia(fn (Assert $page) => $page
            ->where('statement.withSalary', true)
            ->where('statement.rows.0.basicCost', 200)
            ->where('statement.rows.0.overtimeCost', 100)
            ->where('statement.totals.totalCost', 300));
});

it('lists a project day by day across everyone who worked it', function () {
    $admin = statementAdmin();
    $project = statementProject();
    $other = statementProject('Another Tower');
    $first = statementEmployee('605', 'First Worker');
    $second = statementEmployee('606', 'Second Worker');

    statementRecord($first, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin);
    statementRecord($second, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin);
    statementRecord($first, $other, '2026-08-04', AttendanceRecord::STATUS_PRESENT, $admin);

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=project&project_id='.$project->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page
            ->has('statement.rows', 2)
            ->where('statement.totals.uniqueEmployees', 2)
            ->where('statement.totals.presentDays', 2));
});

it('keeps the statement inside the requested date range', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('607', 'Range Worker');
    $project = statementProject();

    statementRecord($employee, $project, '2026-07-31', AttendanceRecord::STATUS_PRESENT, $admin);
    statementRecord($employee, $project, '2026-08-01', AttendanceRecord::STATUS_PRESENT, $admin);
    statementRecord($employee, $project, '2026-09-01', AttendanceRecord::STATUS_PRESENT, $admin);

    $this->actingAs($admin)
        ->get('/attendance/statement?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page->has('statement.rows', 1));
});

it('downloads the statement as a workbook', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('608', 'Export Worker');
    $project = statementProject();

    statementRecord($employee, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin);

    $response = $this->actingAs($admin)
        ->get('/attendance/statement/export?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31');

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect(strlen($response->streamedContent()))->toBeGreaterThan(2000);
});

it('renders the print view with the days and totals', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('609', 'Printed Worker');
    $project = statementProject();

    statementRecord($employee, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin, overtime: 2);

    $this->actingAs($admin)
        ->get('/attendance/statement/print?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31')
        ->assertOk()
        ->assertSee('Employee Attendance Statement')
        ->assertSee('Printed Worker')
        ->assertSee('03/08/2026')
        ->assertSee('Statement Tower')
        ->assertSee('August 2026');
});

it('says so when the employee has no salary setting', function () {
    $admin = statementAdmin();
    $employee = statementEmployee('610', 'Uncosted Worker', null);
    $project = statementProject();

    statementRecord($employee, $project, '2026-08-03', AttendanceRecord::STATUS_PRESENT, $admin);

    $this->actingAs($admin)
        ->get('/attendance/statement/print?mode=employee&employee_id='.$employee->id.'&from=2026-08-01&to=2026-08-31&with_salary=1')
        ->assertOk()
        ->assertSee('Cost is incomplete');
});

it('blocks non-admin users from the statement', function () {
    $user = User::factory()->create(['role' => User::ROLE_ATTENDANCE]);

    $this->actingAs($user)->get('/attendance/statement')->assertStatus(403);
    $this->actingAs($user)->get('/attendance/statement/export')->assertStatus(403);
    $this->actingAs($user)->get('/attendance/statement/print')->assertStatus(403);
});
