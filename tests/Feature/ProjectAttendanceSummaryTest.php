<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('multi project summary attributes salary and overtime and exports the applied selection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $projects = collect(['Alpha', 'Beta', 'Empty', 'Excluded'])->map(fn ($name) => Project::create(['name' => $name, 'type' => 'rope_access', 'status' => 'ongoing']));
    [$alpha, $beta, $empty, $excluded] = $projects->all();
    $people = collect(['Daily', 'Monthly', 'Missing'])->map(fn ($name) => Employee::create(['code' => $name, 'name' => $name, 'profession' => 'Technician', 'type' => 'rope_access', 'status' => 'active']));
    [$daily, $monthly, $missing] = $people->all();
    EmployeePayrollSetting::create(['employee_id' => $daily->id, 'daily_salary' => 100, 'salary_rule' => 'present_days', 'standard_hours_per_day' => 8, 'is_overtime_enabled' => true]);
    EmployeePayrollSetting::create(['employee_id' => $monthly->id, 'daily_salary' => 56.65, 'monthly_salary' => 1700, 'salary_rule' => 'fixed_30_days', 'standard_hours_per_day' => 8, 'is_overtime_enabled' => false]);
    $record = fn ($employee, $project, $date, $ot, $fraction = 1, $otProject = null, $status = 'present') => AttendanceRecord::create([
        'employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => $date, 'overtime_hours' => $ot,
        'attendance_fraction' => $fraction, 'overtime_project_id' => $otProject?->id, 'status' => $status, 'submitted_by' => $admin->id,
    ]);
    $record($daily, $alpha, '2026-08-03', 85 / 60, 0.5, $beta);
    $record($daily, $alpha, '2026-08-04', 2);
    $record($monthly, $beta, '2026-08-04', 3);
    $record($missing, $alpha, '2026-08-03', 0);
    $record($daily, $alpha, '2026-07-31', 10);
    $record($daily, $alpha, '2026-08-05', 0, 1, null, 'absent');
    $record($daily, $excluded, '2026-08-05', 1);
    $query = '?'.http_build_query(['project_ids' => [$alpha->id, $beta->id, $empty->id], 'from' => '2026-08-01', 'to' => '2026-08-31']);
    $this->actingAs($admin)->get('/attendance/project-summary'.$query)->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/ProjectSummary')->has('report.rows', 3)
        ->where('report.rows.0.name', 'Alpha')->where('report.rows.0.workedDays', 2)->where('report.rows.0.persons', 2)
        ->where('report.rows.0.labourDays', 2.5)->where('report.rows.0.overtimeHours', 2)
        ->where('report.rows.0.basicCost', 150)->where('report.rows.0.overtimeCost', 25)->where('report.rows.0.totalCost', 175)
        ->where('report.rows.0.missingPayroll', 1)->where('report.rows.1.persons', 2)->where('report.rows.1.workedDays', 2)
        ->where('report.rows.1.basicCost', 56.67)->where('report.rows.1.overtimeCost', 17.71)->where('report.rows.1.totalCost', 74.38)
        ->where('report.rows.1.overtimeHours', fn ($hours) => abs($hours - 265 / 60) < 0.00001)
        ->where('report.rows.2.totalCost', 0)->where('report.rows.2.persons', 0)
        ->where('report.totals.persons', 3)->where('report.totals.totalCost', 249.38));
    $this->get('/attendance/project-summary/print'.$query)->assertOk()->assertSee('Alpha')->assertSee('Beta')->assertSee('Empty')->assertDontSee('Excluded')->assertSee('249.38')->assertSee('Incomplete cost');
    $response = $this->get('/attendance/project-summary/export'.$query)->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'projects-summary-');
    try {
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $headerIndex = collect($rows)->search(fn ($row) => $row[0] === 'Project');
        expect($headerIndex)->not->toBeFalse();
        $first = $headerIndex + 2;
        expect($sheet->getCell('A'.$first)->getValue())->toBe('Alpha');
        expect((float) $sheet->getCell('H'.$first)->getValue())->toBe(175.0);
        expect((float) $sheet->getCell('H'.($first + 3))->getValue())->toBe(249.38);
        $this->assertEqualsWithDelta(265 / 1440, $sheet->getCell('E'.($first + 1))->getValue(), 0.000001);
        expect(json_encode($rows))->not->toContain('Excluded');
    } finally {
        @unlink($path);
    }
    $this->get('/attendance/project-summary')->assertInertia(fn (Assert $page) => $page->has('report.rows', 0));
    $historyUrl = '/attendance/project-summary/'.$beta->id.'/history';
    $range = '?from=2026-08-01&to=2026-08-31';
    $history = $this->getJson($historyUrl.$range)->assertOk()
        ->assertJsonPath('totals.totalCost', 74.38)->assertJsonPath('totals.basicCost', 56.67)
        ->assertJsonPath('totals.persons', 2)->assertJsonCount(2, 'employees')->assertJsonCount(2, 'records')->json();
    $otOnly = collect($history['employees'])->firstWhere('employeeId', $daily->id);
    expect($otOnly['labourDays'])->toBe(0)->and($otOnly['basicCost'])->toBe(0)
        ->and($otOnly['overtimeCost'])->toBe(17.71)->and($otOnly['submittedBy'])->toBe($admin->name);
    $this->getJson('/attendance/project-summary/'.$alpha->id.'/history'.$range)
        ->assertJsonPath('totals.totalCost', 175)->assertJsonPath('totals.missingPayroll', 1)->assertJsonCount(3, 'records');
    $this->getJson($historyUrl.'?from=2026-08-03&to=2026-08-03')
        ->assertJsonPath('totals.totalCost', 17.71)->assertJsonPath('totals.persons', 1)->assertJsonCount(1, 'records');
    $this->getJson('/attendance/project-summary/'.$empty->id.'/history'.$range)
        ->assertJsonPath('totals.totalCost', 0)->assertJsonCount(0, 'employees')->assertJsonCount(0, 'records');
    $this->get($historyUrl.'/print'.$range)->assertOk()->assertSee('Employee Summary')->assertSee('Daily Attendance')->assertSee('74.38')->assertSee($admin->name)->assertDontSee('Missing');
    $response = $this->get($historyUrl.'/export'.$range)->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'project-history-');
    try {
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        expect($book->getSheetNames())->toBe(['Employee Summary', 'Daily Attendance']);
        $text = json_encode($book->getSheet(0)->toArray());
        expect($text)->toContain('Daily')->toContain('Monthly');
        $rows = $book->getSheet(0)->toArray();
        expect(array_column($rows, 1))->not->toContain('Missing');
        $totalRow = collect($rows)->first(fn ($row) => $row[0] === 'Project Total');
        expect($totalRow[9])->toBe('74.38');
    } finally {
        @unlink($path);
    }
});

test('project summary validates selection and date range and restricts all outputs to admins', function () {
    $project = Project::create(['name' => 'Protected Project', 'type' => 'rope_access', 'status' => 'ongoing']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (['', '/export', '/print'] as $suffix) {
        $this->getJson('/attendance/project-summary'.$suffix.'?project_ids[]=99999')->assertUnprocessable();
        $this->getJson('/attendance/project-summary'.$suffix.'?from=2026-08-30&to=2026-08-01')->assertUnprocessable();
        $this->getJson('/attendance/project-summary'.$suffix.'?from=2026-02-30')->assertUnprocessable();
    }
    foreach (['', '/export', '/print'] as $suffix) {
        $this->getJson('/attendance/project-summary/'.$project->id.'/history'.$suffix.'?from=2026-08-30&to=2026-08-01')->assertUnprocessable();
    }
    $this->getJson('/attendance/project-summary/99999/history')->assertNotFound();
    foreach (['attendance_user', 'office_staff'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['', '/export', '/print'] as $suffix) {
            $this->get('/attendance/project-summary'.$suffix)->assertForbidden();
            $this->get('/attendance/project-summary/'.$project->id.'/history'.$suffix)->assertForbidden();
        }
    }
});
