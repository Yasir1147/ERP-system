<?php

use App\Models\AttendanceRecord;
use App\Models\ContractingDutyAssignment;
use App\Models\ContractingDutyPlan;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('deleting finalized contracting attendance releases the employee for duty planning', function () {
    $attendanceUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = Employee::query()->create([
        'code' => '908',
        'name' => 'Released Duty Employee',
        'profession' => 'Electrician',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Released Duty Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $date = now()->toDateString();

    $this->actingAs($attendanceUser)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$employee->id],
    ])->assertSessionHasNoErrors();

    $plan = ContractingDutyPlan::query()->firstOrFail();

    $this->actingAs($attendanceUser)
        ->post("/contracting-duty-plans/{$plan->id}/finalize")
        ->assertSessionHasNoErrors();

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->firstOrFail();

    $this->actingAs($admin)
        ->delete("/attendance/{$record->id}")
        ->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()->whereKey($record->id)->exists())->toBeFalse()
        ->and(ContractingDutyAssignment::query()->where('employee_id', $employee->id)->exists())->toBeFalse()
        ->and(ContractingDutyPlan::query()->whereKey($plan->id)->exists())->toBeFalse();

    $this->actingAs($attendanceUser)
        ->get('/contracting-duty-plans/create?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContractingDuties/Index')
            ->where('employees.0.id', $employee->id));
});

test('previously orphaned finalized assignments are released when duty is created again', function () {
    $firstUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $secondUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $employee = Employee::query()->create([
        'code' => '909',
        'name' => 'Previously Released Employee',
        'profession' => 'Mason',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Orphan Cleanup Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $date = now()->toDateString();

    $this->actingAs($firstUser)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$employee->id],
    ])->assertSessionHasNoErrors();

    $firstPlan = ContractingDutyPlan::query()->firstOrFail();
    $this->actingAs($firstUser)
        ->post("/contracting-duty-plans/{$firstPlan->id}/finalize")
        ->assertSessionHasNoErrors();

    AttendanceRecord::query()->where('employee_id', $employee->id)->firstOrFail()->delete();

    $this->actingAs($secondUser)
        ->get('/contracting-duty-plans/create?date='.$date)
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContractingDuties/Index')
            ->where('employees.0.id', $employee->id));

    $this->actingAs($secondUser)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$employee->id],
    ])->assertSessionHasNoErrors();

    expect(ContractingDutyPlan::query()->whereKey($firstPlan->id)->exists())->toBeFalse()
        ->and(ContractingDutyPlan::query()->where('created_by', $secondUser->id)->exists())->toBeTrue()
        ->and(ContractingDutyAssignment::query()->where('employee_id', $employee->id)->count())->toBe(1);
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

test('contracting attendance users only see and manage their own duty plans', function () {
    $owner = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $otherUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $employee = Employee::query()->create([
        'code' => '903',
        'name' => 'Private Duty Employee',
        'profession' => 'Technician',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Private Duty Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);

    $this->actingAs($owner)->post('/contracting-duty-plans/assignments', [
        'duty_date' => now()->toDateString(),
        'project_id' => $project->id,
        'employee_ids' => [$employee->id],
    ])->assertSessionHasNoErrors();

    $plan = ContractingDutyPlan::query()->firstOrFail();
    $assignment = ContractingDutyAssignment::query()->firstOrFail();

    $this->actingAs($owner)
        ->get("/contracting-duty-plans/{$plan->id}/edit?step=2")
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContractingDuties/Index')
            ->where('initialStep', 2)
            ->where('plan.createdBy', $owner->name));

    $this->actingAs($otherUser)
        ->get('/contracting-duty-plans')
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContractingDuties/Dashboard')
            ->where('summary.open', 0)
            ->has('plans', 0));

    $this->actingAs($otherUser)
        ->get("/contracting-duty-plans/{$plan->id}/edit")
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->put("/contracting-duty-assignments/{$assignment->id}", [
            'project_id' => $project->id,
            'status' => ContractingDutyAssignment::STATUS_PRESENT,
            'has_overtime' => false,
        ])
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->post("/contracting-duty-plans/{$plan->id}/finalize")
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->delete("/contracting-duty-plans/{$plan->id}")
        ->assertForbidden();
});

test('different users can create plans on one date but cannot assign the same employee twice', function () {
    $firstUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $secondUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $firstEmployee = Employee::query()->create([
        'code' => '904',
        'name' => 'First User Employee',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $secondEmployee = Employee::query()->create([
        'code' => '905',
        'name' => 'Second User Employee',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Shared Date Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $date = now()->toDateString();

    $this->actingAs($firstUser)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$firstEmployee->id],
    ])->assertSessionHasNoErrors();

    $this->actingAs($secondUser)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$secondEmployee->id],
    ])->assertSessionHasNoErrors();

    expect(ContractingDutyPlan::query()->whereDate('duty_date', $date)->count())->toBe(2);

    $this->actingAs($secondUser)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$firstEmployee->id],
    ])->assertSessionHasErrors('employee_ids');

    expect(ContractingDutyAssignment::query()
        ->whereDate('duty_date', $date)
        ->where('employee_id', $firstEmployee->id)
        ->count())->toBe(1);
});

test('a user can repeat their previous duty with fresh attendance values', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $employee = Employee::query()->create([
        'code' => '906',
        'name' => 'Repeated Duty Employee',
        'profession' => 'Mason',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $removedEmployee = Employee::query()->create([
        'code' => '907',
        'name' => 'Removed Repeat Employee',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Repeated Duty Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $sourceDate = now()->subDay()->toDateString();
    $targetDate = now()->toDateString();
    $sourcePlan = ContractingDutyPlan::query()->create([
        'duty_date' => $sourceDate,
        'status' => ContractingDutyPlan::STATUS_DRAFT,
        'created_by' => $user->id,
    ]);
    $sourcePlan->assignments()->createMany([
        [
            'duty_date' => $sourceDate,
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'status' => ContractingDutyAssignment::STATUS_ABSENT,
            'has_overtime' => true,
            'overtime_hours' => 3,
            'overtime_project_id' => $project->id,
            'note' => 'Old note',
        ],
        [
            'duty_date' => $sourceDate,
            'employee_id' => $removedEmployee->id,
            'project_id' => $project->id,
            'status' => ContractingDutyAssignment::STATUS_REMOVED,
            'has_overtime' => false,
        ],
    ]);

    $this->actingAs($user)->post("/contracting-duty-plans/{$sourcePlan->id}/repeat", [
        'target_date' => $targetDate,
    ])->assertSessionHasNoErrors();

    $targetPlan = ContractingDutyPlan::query()
        ->whereDate('duty_date', $targetDate)
        ->where('created_by', $user->id)
        ->firstOrFail();
    $copied = $targetPlan->assignments()->firstOrFail();

    expect($targetPlan->status)->toBe(ContractingDutyPlan::STATUS_DRAFT)
        ->and($targetPlan->assignments()->count())->toBe(1)
        ->and($copied->employee_id)->toBe($employee->id)
        ->and($copied->project_id)->toBe($project->id)
        ->and($copied->status)->toBe(ContractingDutyAssignment::STATUS_PRESENT)
        ->and($copied->has_overtime)->toBeFalse()
        ->and($copied->overtime_hours)->toBeNull()
        ->and($copied->note)->toBeNull();
});

test('a user can add new employees after submitting attendance without resubmitting existing employees', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    $submittedEmployee = Employee::query()->create([
        'code' => '910',
        'name' => 'Already Submitted Employee',
        'profession' => 'Mason',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $newEmployee = Employee::query()->create([
        'code' => '911',
        'name' => 'Additional Employee',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Additional Attendance Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $date = now()->toDateString();

    $this->actingAs($user)->post('/contracting-duty-plans/assignments', [
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$submittedEmployee->id],
    ])->assertSessionHasNoErrors();

    $plan = ContractingDutyPlan::query()->firstOrFail();
    $this->actingAs($user)
        ->post("/contracting-duty-plans/{$plan->id}/finalize")
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get("/contracting-duty-plans/{$plan->id}/edit?step=2&extend=1")
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContractingDuties/Index')
            ->where('extensionMode', true)
            ->where('employees.0.id', $newEmployee->id)
            ->missing('employees.1'));

    $this->actingAs($user)->post('/contracting-duty-plans/assignments', [
        'workflow' => 'wizard',
        'extend_finalized' => true,
        'duty_date' => $date,
        'project_id' => $project->id,
        'employee_ids' => [$newEmployee->id],
    ])->assertSessionHasNoErrors();

    $submittedAssignment = ContractingDutyAssignment::query()
        ->where('employee_id', $submittedEmployee->id)
        ->firstOrFail();

    expect($plan->fresh()->status)->toBe(ContractingDutyPlan::STATUS_DRAFT)
        ->and($plan->assignments()->count())->toBe(2)
        ->and($submittedAssignment->attendance_record_id)->not->toBeNull();

    $this->actingAs($user)->put("/contracting-duty-assignments/{$submittedAssignment->id}", [
        'project_id' => $project->id,
        'status' => ContractingDutyAssignment::STATUS_ABSENT,
        'has_overtime' => false,
    ])->assertSessionHasErrors('assignment');

    $this->actingAs($user)
        ->post("/contracting-duty-plans/{$plan->id}/finalize")
        ->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()->whereDate('attendance_date', $date)->count())->toBe(2)
        ->and($plan->fresh()->status)->toBe(ContractingDutyPlan::STATUS_FINALIZED);
});
