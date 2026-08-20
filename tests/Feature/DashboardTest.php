<?php

use App\Models\ContractingDutyAssignment;
use App\Models\ContractingDutyPlan;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('an admin can visit the dashboard', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('an attendance user cannot visit the admin dashboard', function () {
    $user = User::factory()->create(['role' => User::ROLE_ATTENDANCE]);
    $this->actingAs($user);

    $this->get('/dashboard')->assertStatus(403);
});

test('the dashboard shows contracting duties from every planner with their people', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $planner = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);

    $employee = Employee::query()->create([
        'code' => '971',
        'name' => 'Overview Worker',
        'profession' => 'Mason',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Overview Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);

    $plan = ContractingDutyPlan::query()->create([
        'duty_date' => now()->toDateString(),
        'created_by' => $planner->id,
        'status' => ContractingDutyPlan::STATUS_DRAFT,
    ]);
    ContractingDutyAssignment::query()->create([
        'contracting_duty_plan_id' => $plan->id,
        'duty_date' => now()->toDateString(),
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'status' => ContractingDutyAssignment::STATUS_PLANNED,
        'has_overtime' => false,
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contractingDuty.summary.open', 1)
            ->where('contractingDuty.summary.submitted', 0)
            ->where('contractingDuty.plans.0.createdBy', $planner->name)
            ->where('contractingDuty.plans.0.employeeCount', 1)
            ->where('contractingDuty.plans.0.people.0.employeeName', 'Overview Worker')
            ->where('contractingDuty.plans.0.people.0.projectName', 'Overview Project'));
});

test('removed duty people are left out of the dashboard overview', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $planner = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);

    $employee = Employee::query()->create([
        'code' => '972',
        'name' => 'Dropped Worker',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $project = Project::query()->create([
        'name' => 'Dropped Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);

    $plan = ContractingDutyPlan::query()->create([
        'duty_date' => now()->toDateString(),
        'created_by' => $planner->id,
        'status' => ContractingDutyPlan::STATUS_DRAFT,
    ]);
    ContractingDutyAssignment::query()->create([
        'contracting_duty_plan_id' => $plan->id,
        'duty_date' => now()->toDateString(),
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'status' => ContractingDutyAssignment::STATUS_REMOVED,
        'has_overtime' => false,
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contractingDuty.plans.0.employeeCount', 0)
            ->where('contractingDuty.plans.0.people', []));
});

test('the duty overview groups people by project and lists contracting planners', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $contractingPlanner = User::factory()->create([
        'name' => 'Contracting Planner',
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);
    User::factory()->create([
        'name' => 'Rope Only User',
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'rope_access',
    ]);

    $project = Project::query()->create([
        'name' => 'Shared Project',
        'status' => 'ongoing',
        'type' => 'contracting',
    ]);
    $plan = ContractingDutyPlan::query()->create([
        'duty_date' => now()->toDateString(),
        'created_by' => $contractingPlanner->id,
        'status' => ContractingDutyPlan::STATUS_DRAFT,
    ]);

    foreach (['981', '982'] as $code) {
        $employee = Employee::query()->create([
            'code' => $code,
            'name' => 'Worker '.$code,
            'profession' => 'Mason',
            'type' => 'contracting',
            'status' => Employee::STATUS_ACTIVE,
        ]);
        ContractingDutyAssignment::query()->create([
            'contracting_duty_plan_id' => $plan->id,
            'duty_date' => now()->toDateString(),
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'status' => ContractingDutyAssignment::STATUS_PLANNED,
            'has_overtime' => false,
        ]);
    }

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contractingDuty.plans.0.projectCount', 1)
            ->where('contractingDuty.plans.0.projects.0.name', 'Shared Project')
            ->where('contractingDuty.plans.0.projects.0.employeeCount', 2)
            ->where('contractingDuty.planners', fn ($planners) => collect($planners)->pluck('name')->contains('Contracting Planner')
                && ! collect($planners)->pluck('name')->contains('Rope Only User')));
});

test('a duty on the selected date is listed even when it is older than the recent window', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $planner = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'contracting',
    ]);

    $oldDate = now()->subDays(200)->toDateString();
    ContractingDutyPlan::query()->create([
        'duty_date' => $oldDate,
        'created_by' => $planner->id,
        'status' => ContractingDutyPlan::STATUS_FINALIZED,
    ]);

    // Thirty newer plans, so the old one falls outside the recent window.
    foreach (range(1, 30) as $day) {
        ContractingDutyPlan::query()->create([
            'duty_date' => now()->subDays($day)->toDateString(),
            'created_by' => $planner->id,
            'status' => ContractingDutyPlan::STATUS_DRAFT,
        ]);
    }

    $this->actingAs($admin)
        ->get('/dashboard?date='.$oldDate)
        ->assertInertia(fn (Assert $page) => $page
            ->where('contractingDuty.plans', fn ($plans) => collect($plans)->pluck('date')->contains($oldDate)));
});
