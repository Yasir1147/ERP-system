<?php

use App\Models\AttendanceRecord;
use App\Models\ContractingDutyAssignment;
use App\Models\ContractingDutyPlan;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;

test('an attendance user can finalize a reviewed contracting duty with overtime', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $employee = Employee::query()->create([
        'code' => '901',
        'name' => 'Duty Employee',
        'profession' => 'Mason',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $mainProject = Project::query()->create([
        'name' => 'Main Duty Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $overtimeProject = Project::query()->create([
        'name' => 'Overtime Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);

    $this->actingAs($user)->post('/contracting-duty-plans/assignments', [
        'duty_date' => now()->toDateString(),
        'project_id' => $mainProject->id,
        'employee_ids' => [$employee->id],
    ])->assertSessionHasNoErrors();

    $plan = ContractingDutyPlan::query()->firstOrFail();
    $assignment = ContractingDutyAssignment::query()->firstOrFail();

    $this->actingAs($user)->post("/contracting-duty-plans/{$plan->id}/publish")
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->put("/contracting-duty-assignments/{$assignment->id}", [
        'project_id' => $mainProject->id,
        'status' => ContractingDutyAssignment::STATUS_PRESENT,
        'has_overtime' => true,
        'overtime_hours' => 3,
        'overtime_project_id' => $overtimeProject->id,
        'note' => 'Worked overtime on another project.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post("/contracting-duty-plans/{$plan->id}/finalize")
        ->assertSessionHasNoErrors();

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($record->status)->toBe(AttendanceRecord::STATUS_PRESENT)
        ->and($record->project_id)->toBe($mainProject->id)
        ->and($record->has_overtime)->toBeTrue()
        ->and($record->overtime_hours)->toBe(3)
        ->and($record->overtime_project_id)->toBe($overtimeProject->id)
        ->and($plan->fresh()->status)->toBe(ContractingDutyPlan::STATUS_FINALIZED)
        ->and($assignment->fresh()->attendance_record_id)->toBe($record->id);
});

test('removed duty employees do not create attendance records', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $employee = Employee::query()->create([
        'code' => '902',
        'name' => 'Removed Employee',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Duty Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);

    $this->actingAs($user)->post('/contracting-duty-plans/assignments', [
        'duty_date' => now()->toDateString(),
        'project_id' => $project->id,
        'employee_ids' => [$employee->id],
    ]);

    $plan = ContractingDutyPlan::query()->firstOrFail();
    $assignment = ContractingDutyAssignment::query()->firstOrFail();

    $this->actingAs($user)->post("/contracting-duty-plans/{$plan->id}/publish");
    $this->actingAs($user)->put("/contracting-duty-assignments/{$assignment->id}", [
        'project_id' => $project->id,
        'status' => ContractingDutyAssignment::STATUS_REMOVED,
        'has_overtime' => false,
        'overtime_hours' => null,
        'overtime_project_id' => null,
        'note' => 'Removed from final duty.',
    ]);
    $this->actingAs($user)->post("/contracting-duty-plans/{$plan->id}/finalize")
        ->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});
