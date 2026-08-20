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
