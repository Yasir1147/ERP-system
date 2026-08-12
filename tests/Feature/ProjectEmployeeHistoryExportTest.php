<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectEmployeeHistoryService;

function historyAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function historyProject(): Project
{
    return Project::create([
        'name' => 'Marina Tower',
        'project_code' => 'RA1001',
        'status' => 'ongoing',
        'type' => 'rope_access',
    ]);
}

function historyEmployee(string $code, string $name, ?float $dailySalary = 200): Employee
{
    $employee = Employee::create([
        'code' => $code,
        'name' => $name,
        'profession' => 'Level 1',
        'type' => 'rope_access',
        'status' => Employee::STATUS_ACTIVE,
    ]);

    if ($dailySalary !== null) {
        EmployeePayrollSetting::create([
            'employee_id' => $employee->id,
            'daily_salary' => $dailySalary,
            'standard_hours_per_day' => 8,
        ]);
    }

    return $employee;
}

function historyRecord(Project $project, Employee $employee, User $user, array $attributes = []): AttendanceRecord
{
    return AttendanceRecord::create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'submitted_by' => $user->id,
        'status' => AttendanceRecord::STATUS_PRESENT,
        'attendance_date' => $attributes['date'] ?? today()->toDateString(),
        'attendance_fraction' => $attributes['fraction'] ?? 1,
        'has_overtime' => ($attributes['overtime'] ?? 0) > 0,
        'overtime_hours' => $attributes['overtime'] ?? null,
    ]);
}

it('orders the summary by cost and shares add up to 100 percent', function () {
    $admin = historyAdmin();
    $project = historyProject();

    $small = historyEmployee('101', 'Small Cost', 100);
    $large = historyEmployee('102', 'Large Cost', 400);

    historyRecord($project, $small, $admin);
    historyRecord($project, $large, $admin);

    $history = app(ProjectEmployeeHistoryService::class)->build($project);
    $summary = $history['employeeSummary'];

    expect($summary->first()['employeeName'])->toBe('Large Cost');
    expect($summary->first()['costShare'])->toBe(80.0);
    expect($summary->last()['costShare'])->toBe(20.0);
    expect(round($summary->sum('costShare'), 1))->toBe(100.0);
});

it('includes the employee code with the name', function () {
    $admin = historyAdmin();
    $project = historyProject();

    historyRecord($project, historyEmployee('0142', 'Imran Haider'), $admin);

    $history = app(ProjectEmployeeHistoryService::class)->build($project);

    expect($history['employeeSummary']->first()['employeeCode'])->toBe('0142');
});

it('flags employees that have no payroll setting', function () {
    $admin = historyAdmin();
    $project = historyProject();

    historyRecord($project, historyEmployee('201', 'Costed Worker', 200), $admin);
    historyRecord($project, historyEmployee('202', 'Uncosted Worker', null), $admin);

    $history = app(ProjectEmployeeHistoryService::class)->build($project);

    expect($history['missingPayrollEmployees']->all())->toBe(['202 - Uncosted Worker']);
    expect($history['employeeSummary']->firstWhere('employeeCode', '202')['missingPayrollSetting'])->toBeTrue();
    expect($history['employeeSummary']->firstWhere('employeeCode', '201')['missingPayrollSetting'])->toBeFalse();
});

it('counts a half day as half the daily salary', function () {
    $admin = historyAdmin();
    $project = historyProject();

    historyRecord($project, historyEmployee('301', 'Half Day', 200), $admin, ['fraction' => 0.5]);

    $history = app(ProjectEmployeeHistoryService::class)->build($project);

    expect($history['totals']['basicCost'])->toBe(100.0);
    expect($history['employeeSummary']->first()['workedDays'])->toBe(0.5);
});

it('adds overtime cost from the daily salary and standard hours', function () {
    $admin = historyAdmin();
    $project = historyProject();

    // 200 / 8 hours = 25 per hour, 2 hours = 50 overtime on top of 200 basic.
    historyRecord($project, historyEmployee('401', 'Overtime Worker', 200), $admin, ['overtime' => 2]);

    $history = app(ProjectEmployeeHistoryService::class)->build($project);

    expect($history['totals']['basicCost'])->toBe(200.0);
    expect($history['totals']['overtimeCost'])->toBe(50.0);
    expect($history['totals']['totalCost'])->toBe(250.0);
});

it('respects the date range filter', function () {
    $admin = historyAdmin();
    $project = historyProject();
    $employee = historyEmployee('501', 'Ranged Worker', 100);

    historyRecord($project, $employee, $admin, ['date' => '2026-01-10']);
    historyRecord($project, $employee, $admin, ['date' => '2026-02-10']);

    $service = app(ProjectEmployeeHistoryService::class);

    expect($service->build($project)['totals']['entries'])->toBe(2);
    expect($service->build($project, '2026-02-01', null)['totals']['entries'])->toBe(1);
    expect($service->build($project, null, '2026-01-31')['totals']['entries'])->toBe(1);
    expect($service->build($project, '2026-02-01', '2026-02-28')['rangeLabel'])
        ->toBe('01/02/2026 — 28/02/2026');
});

it('downloads an xlsx workbook', function () {
    $admin = historyAdmin();
    $project = historyProject();

    historyRecord($project, historyEmployee('601', 'Export Worker'), $admin);

    $response = $this->actingAs($admin)
        ->get('/projects/'.$project->id.'/employee-history/export');

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('content-disposition'))
        ->toContain('employee-history-marina-tower');

    // A real xlsx is a zip; PK is its magic number.
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

it('renders the print view with the totals and share column', function () {
    $admin = historyAdmin();
    $project = historyProject();

    historyRecord($project, historyEmployee('701', 'Printed Worker', 250), $admin);

    $this->actingAs($admin)
        ->get('/projects/'.$project->id.'/employee-history/print')
        ->assertOk()
        ->assertSee('Project Employee History')
        ->assertSee('Marina Tower')
        ->assertSee('701')
        ->assertSee('Printed Worker')
        ->assertSee('250.00')
        ->assertSee('Share');
});

it('warns in the print view when cost is incomplete', function () {
    $admin = historyAdmin();
    $project = historyProject();

    historyRecord($project, historyEmployee('801', 'Uncosted', null), $admin);

    $this->actingAs($admin)
        ->get('/projects/'.$project->id.'/employee-history/print')
        ->assertOk()
        ->assertSee('Cost is incomplete')
        ->assertSee('801 - Uncosted');
});

it('blocks non-admin users from the exports', function () {
    $project = historyProject();

    $attendanceUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'rope_access',
    ]);

    $this->actingAs($attendanceUser)
        ->get('/projects/'.$project->id.'/employee-history/export')
        ->assertForbidden();

    $this->actingAs($attendanceUser)
        ->get('/projects/'.$project->id.'/employee-history/print')
        ->assertForbidden();
});
