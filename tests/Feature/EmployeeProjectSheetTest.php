<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\PayrollAdjustment;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('employee sheet pivots selected people with separate overtime projects and matching workbook totals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $people = collect(['Selected', 'No work', 'Excluded'])->map(fn ($name) => Employee::create(['name' => $name, 'code' => $name, 'profession' => 'Worker', 'type' => 'contracting', 'status' => 'active']));
    [$worker, $empty, $excluded] = $people->all();
    EmployeePayrollSetting::create(['employee_id' => $worker->id, 'daily_salary' => 50, 'monthly_salary' => 3000, 'salary_rule' => 'fixed_30_days', 'standard_hours_per_day' => 8, 'is_overtime_enabled' => true]);
    [$alpha, $beta, $hidden] = collect(['Alpha', 'Beta', 'Hidden'])->map(fn ($name) => Project::create(['name' => $name, 'type' => 'contracting', 'status' => 'ongoing']))->all();
    $record = fn ($employee, $project, $date, $ot, $fraction = 1, $otProject = null, $status = 'present') => AttendanceRecord::create([
        'employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => $date, 'overtime_hours' => $ot,
        'attendance_fraction' => $fraction, 'overtime_project_id' => $otProject?->id, 'status' => $status, 'submitted_by' => $admin->id,
    ]);
    $record($worker, $alpha, '2026-09-01', 1.5, 0.5, $beta);
    $record($worker, $alpha, '2026-09-30', 2);
    $record($worker, $hidden, '2026-08-31', 8);
    $record($worker, $hidden, '2026-09-04', 8, 1, null, 'absent');
    $record($excluded, $hidden, '2026-09-02', 8);
    PayrollAdjustment::create(['employee_id' => $worker->id, 'month' => '2026-09-01', 'bonus_extra' => 75]);
    PayrollAdjustment::create(['employee_id' => $worker->id, 'month' => '2026-08-01', 'bonus_extra' => 999]);
    $query = '?'.http_build_query(['mode' => 'employee', 'employee_ids' => [$worker->id, $empty->id], 'from' => '2026-09-01', 'to' => '2026-09-30']);
    $response = $this->actingAs($admin)->get('/attendance/statement'.$query);
    $response->assertInertia(fn (Assert $p) => $p->component('Attendance/EmployeeProjectSheet')->has('report.rows', 2)->has('report.projects', 2)
        ->where('report.projects.0.name', 'Alpha')->where('report.projects.1.name', 'Beta')
        ->where('report.totals.basicCost', 150)->where('report.totals.overtimeCost', 43.75)->where('report.totals.bonus', 75)
        ->where('report.totals.totalCost', 268.75)->where('report.totals.labourDays', 1.5)->where('report.totals.overtimeHours', 3.5)
        ->where('report.totals.projects.0.basicCost', 150)->where('report.totals.projects.0.overtimeCost', 25)
        ->where('report.totals.projects.1.basicCost', 0)->where('report.totals.projects.1.overtimeHours', 1.5)
        ->where('report.missingPayroll', 1));
    $this->get('/attendance/statement/print'.$query)->assertOk()->assertSee('Selected')->assertSee('No work')->assertDontSee('Excluded')->assertDontSee('Hidden')->assertSee('268.75');
    $export = $this->get('/attendance/statement/export'.$query)->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'employee-sheet-');
    try {
        file_put_contents($path, $export->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        expect($sheet->getCell('G5')->getValue())->toBe('Alpha');
        expect($sheet->getCell('K5')->getValue())->toBe('Beta');
        expect($sheet->getCell('Q9')->getCalculatedValue())->toBe(268.75);
        expect($sheet->getCell('P9')->getCalculatedValue())->toBe(3.5);
        expect($sheet->getCell('E9')->getValue())->toBeNull();
        expect($sheet->getFreezePane())->toBe('C7');
        expect(json_encode($sheet->toArray()))->not->toContain('Excluded')->not->toContain('Hidden');
    } finally {
        @unlink($path);
    }
    $this->get('/attendance/statement?mode=employee')->assertInertia(fn (Assert $p) => $p->has('report.rows', 0)->has('report.projects', 0));
});

test('employee sheet validates selection and dates and protects salary outputs', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (['', '/export', '/print'] as $suffix) {
        $this->getJson('/attendance/statement'.$suffix.'?mode=employee&employee_ids[]=999999')->assertUnprocessable();
        $this->getJson('/attendance/statement'.$suffix.'?mode=employee&from=2026-09-30&to=2026-09-01')->assertUnprocessable();
        $this->getJson('/attendance/statement'.$suffix.'?mode=employee&from=2026-02-30')->assertUnprocessable();
    }
    foreach (['attendance_user', 'office_staff'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['', '/export', '/print'] as $suffix) {
            $this->get('/attendance/statement'.$suffix.'?mode=employee')->assertForbidden();
        }
    }
});
