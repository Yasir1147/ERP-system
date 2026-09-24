<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Models\User;
use App\Support\Overtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('saves rope access overtime with every minute intact', function ($hours, $minutes) {
    $user = User::factory()->create(['role' => User::ROLE_ATTENDANCE, 'attendance_employee_type' => 'rope_access']);
    $employee = Employee::create(['code' => 'MIN1', 'name' => 'Minute Worker', 'profession' => 'Technician', 'type' => 'rope_access', 'status' => Employee::STATUS_ACTIVE]);
    $this->actingAs($user)->post('/mark-attendance/rope-access', [
        'employee_ids' => [(string) $employee->id], 'status' => 'present', 'attendance_fraction' => '1',
        'project_id' => 'other', 'project_name' => 'Minute Site', 'attendance_date' => now()->toDateString(),
        'has_overtime' => true, 'overtime_hours' => (string) $hours, 'overtime_minutes' => (string) $minutes,
    ])->assertSessionHasNoErrors();
    $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();
    expect((int) round($record->overtime_hours * 60))->toBe($hours * 60 + $minutes);
    expect(Overtime::hours($record->overtime_hours) * 60)->toEqualWithDelta($hours * 60 + $minutes, 0.000001);
})->with([[2, 25], [0, 1], [0, 30], [10, 0]]);

it('rejects invalid overtime durations', function ($hours, $minutes) {
    $request = Request::create('/', 'POST', ['status' => 'present', 'has_overtime' => true, 'overtime_hours' => $hours, 'overtime_minutes' => $minutes]);
    expect(fn () => Overtime::prepare($request, 'rope_access'))->toThrow(ValidationException::class);
})->with([[0, 0], [10, 1], [2, 60], [2, -1], [2, 2.5]]);

it('formats minutes and keeps contracting restricted to whole hours', function () {
    expect(Overtime::label(145 / 60))->toBe('2 hr 25 min');
    expect(Overtime::label(0.5))->toBe('30 min');
    expect(Validator::make(['ot' => 2.5], ['ot' => Overtime::rules('contracting')])->fails())->toBeTrue();
});

it('preserves minutes in admin edits and payroll money', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = Employee::create(['code' => 'MIN2', 'name' => 'Payroll Minute Worker', 'profession' => 'Technician', 'type' => 'rope_access', 'status' => Employee::STATUS_ACTIVE]);
    $project = Project::create(['name' => 'Minute Site', 'type' => 'rope_access', 'status' => 'ongoing']);
    EmployeePayrollSetting::create(['employee_id' => $employee->id, 'daily_salary' => 480, 'standard_hours_per_day' => 8, 'is_overtime_enabled' => true]);
    $record = AttendanceRecord::create(['employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => '2026-09-23', 'status' => 'present', 'has_overtime' => false]);
    $this->actingAs($admin)->put('/attendance/'.$record->id, [
        'employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => '2026-09-23',
        'status' => 'present', 'has_overtime' => true, 'overtime_hours' => '2', 'overtime_minutes' => '25',
    ])->assertSessionHasNoErrors();
    expect(Overtime::label($record->fresh()->overtime_hours))->toBe('2 hr 25 min');
    $this->get('/payroll/report?type=rope_access&month=2026-09')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('payrollRows.0.overtimeAmount', 145)
        ->where('payrollRows.0.overtimeHours', fn ($value) => abs($value * 60 - 145) < 0.000001));
});

it('ignores unused zero hour fields when overtime is disabled', function () {
    $request = Request::create('/', 'POST', ['status' => 'present', 'has_overtime' => false, 'overtime_hours' => '0', 'overtime_minutes' => '0']);
    Overtime::prepare($request, 'rope_access');
    expect($request->input('overtime_hours'))->toBeNull();
});
