<?php

use App\Models\AttendanceRecord;
use App\Models\ContractingDutyAssignment;
use App\Models\ContractingDutyPlan;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function fieldUser(string $type = 'contracting'): User
{
    return User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => $type,
    ]);
}

function siteEmployee(string $code, string $type = 'contracting'): Employee
{
    return Employee::query()->create([
        'code' => $code,
        'name' => 'Site Worker '.$code,
        'profession' => 'Mason',
        'type' => $type,
        'status' => Employee::STATUS_ACTIVE,
    ]);
}

it('raises a real project when a field user names a site that is not listed', function () {
    $user = fieldUser();
    $employee = siteEmployee('801');

    $this->actingAs($user)
        ->post('/mark-attendance/contracting', [
            'employee_ids' => [(string) $employee->id],
            'status' => AttendanceRecord::STATUS_PRESENT,
            'attendance_fraction' => '1',
            'project_id' => 'other',
            'project_name' => 'Marina Bay Tower',
            'attendance_date' => now()->toDateString(),
            'has_overtime' => false,
        ])
        ->assertSessionHasNoErrors();

    $project = Project::query()->where('name', 'Marina Bay Tower')->firstOrFail();

    // A real project, not loose text: every cost figure hangs off project_id.
    expect($project->is_provisional)->toBeTrue()
        ->and($project->type)->toBe('contracting')
        ->and($project->created_by)->toBe($user->id);

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'project_id' => $project->id,
    ]);
});

it('reuses the same project when the name is typed again in another case', function () {
    $user = fieldUser();
    $first = siteEmployee('802');
    $second = siteEmployee('803');

    foreach ([['Marina Bay Tower', $first], ['  marina  bay   tower ', $second]] as [$name, $employee]) {
        $this->actingAs($user)
            ->post('/mark-attendance/contracting', [
                'employee_ids' => [(string) $employee->id],
                'status' => AttendanceRecord::STATUS_PRESENT,
                'attendance_fraction' => '1',
                'project_id' => 'other',
                'project_name' => $name,
                'attendance_date' => now()->toDateString(),
                'has_overtime' => false,
            ])
            ->assertSessionHasNoErrors();
    }

    // Otherwise one site's cost splits across near-duplicate rows.
    expect(Project::query()->where('type', 'contracting')->count())->toBe(1);
});

it('keeps the same name apart for a different employee type', function () {
    $contractingUser = fieldUser();
    $ropeUser = fieldUser('rope_access');

    $this->actingAs($contractingUser)
        ->post('/mark-attendance/contracting', [
            'employee_ids' => [(string) siteEmployee('804')->id],
            'status' => AttendanceRecord::STATUS_PRESENT,
            'attendance_fraction' => '1',
            'project_id' => 'other',
            'project_name' => 'Shared Site',
            'attendance_date' => now()->toDateString(),
            'has_overtime' => false,
        ])->assertSessionHasNoErrors();

    $this->actingAs($ropeUser)
        ->post('/mark-attendance/rope-access', [
            'employee_ids' => [(string) siteEmployee('805', 'rope_access')->id],
            'status' => AttendanceRecord::STATUS_PRESENT,
            'attendance_fraction' => '1',
            'project_id' => 'other',
            'project_name' => 'Shared Site',
            'attendance_date' => now()->toDateString(),
            'has_overtime' => false,
        ])->assertSessionHasNoErrors();

    expect(Project::query()->where('name', 'Shared Site')->count())->toBe(2);
});

it('refuses a blank or too short project name', function () {
    $user = fieldUser();
    $employee = siteEmployee('806');

    $this->actingAs($user)
        ->post('/mark-attendance/contracting', [
            'employee_ids' => [(string) $employee->id],
            'status' => AttendanceRecord::STATUS_PRESENT,
            'attendance_fraction' => '1',
            'project_id' => 'other',
            'project_name' => 'PR',
            'attendance_date' => now()->toDateString(),
            'has_overtime' => false,
        ])
        ->assertSessionHasErrors('project_name');

    expect(Project::query()->count())->toBe(0);
});

it('lets a duty plan name a site that is not listed', function () {
    $user = fieldUser();
    $employee = siteEmployee('807');

    $this->actingAs($user)
        ->post('/contracting-duty-plans/assignments', [
            'duty_date' => now()->toDateString(),
            'project_id' => 'other',
            'project_name' => 'Duty Site Tower',
            'employee_ids' => [$employee->id],
        ])
        ->assertSessionHasNoErrors();

    $project = Project::query()->where('name', 'Duty Site Tower')->firstOrFail();

    expect($project->is_provisional)->toBeTrue();
    $this->assertDatabaseHas('contracting_duty_assignments', [
        'employee_id' => $employee->id,
        'project_id' => $project->id,
    ]);
});

it('merges a provisional project into the real one and moves its records', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $employee = siteEmployee('808');

    $real = Project::query()->create(['name' => 'Sobha Opulence', 'type' => 'contracting', 'status' => 'ongoing']);
    $typo = Project::query()->create(['name' => 'sobha opulance', 'type' => 'contracting', 'status' => 'ongoing', 'is_provisional' => true]);

    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => $typo->id,
        'overtime_project_id' => $typo->id,
        'attendance_date' => now()->toDateString(),
        'status' => AttendanceRecord::STATUS_PRESENT,
        'attendance_fraction' => 1,
        'overtime_hours' => 2,
        'submitted_by' => $admin->id,
    ]);

    $plan = ContractingDutyPlan::query()->create([
        'duty_date' => now()->toDateString(),
        'created_by' => $admin->id,
        'status' => ContractingDutyPlan::STATUS_DRAFT,
    ]);
    ContractingDutyAssignment::query()->create([
        'contracting_duty_plan_id' => $plan->id,
        'duty_date' => now()->toDateString(),
        'employee_id' => $employee->id,
        'project_id' => $typo->id,
        'status' => ContractingDutyAssignment::STATUS_PLANNED,
        'has_overtime' => false,
    ]);

    $this->actingAs($admin)
        ->post('/projects/'.$typo->id.'/merge', ['target_project_id' => $real->id])
        ->assertSessionHasNoErrors();

    // Nothing is lost: the records move first, then the empty project goes.
    $this->assertDatabaseMissing('projects', ['id' => $typo->id]);
    $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->id, 'project_id' => $real->id, 'overtime_project_id' => $real->id]);
    $this->assertDatabaseHas('contracting_duty_assignments', ['employee_id' => $employee->id, 'project_id' => $real->id]);
});

it('refuses to merge a project into itself', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $project = Project::query()->create(['name' => 'Lone Site', 'type' => 'contracting', 'status' => 'ongoing', 'is_provisional' => true]);

    $this->actingAs($admin)
        ->post('/projects/'.$project->id.'/merge', ['target_project_id' => $project->id])
        ->assertSessionHasErrors('target_project_id');

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});

it('shows the admin which projects came from an attendance form', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    Project::query()->create(['name' => 'Registered Site', 'type' => 'contracting', 'status' => 'ongoing']);
    Project::query()->create(['name' => 'Named By Foreman', 'type' => 'contracting', 'status' => 'ongoing', 'is_provisional' => true]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('provisionalProjects', 1)
            ->where('provisionalProjects.0.name', 'Named By Foreman'));

    $this->actingAs($admin)
        ->get('/projects/contracting')
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects', fn ($projects) => collect($projects)
                ->firstWhere('name', 'Named By Foreman')['isProvisional'] === true
                && collect($projects)->firstWhere('name', 'Registered Site')['isProvisional'] === false));
});

it('blocks a non-admin from merging projects', function () {
    $user = fieldUser();
    $project = Project::query()->create(['name' => 'Guarded Site', 'type' => 'contracting', 'status' => 'ongoing', 'is_provisional' => true]);
    $target = Project::query()->create(['name' => 'Real Site', 'type' => 'contracting', 'status' => 'ongoing']);

    $this->actingAs($user)
        ->post('/projects/'.$project->id.'/merge', ['target_project_id' => $target->id])
        ->assertStatus(403);

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});
