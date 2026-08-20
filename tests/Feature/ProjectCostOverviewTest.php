<?php

use App\Models\AppSetting;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeExpense;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can save project planning values without changing existing project requirements', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->post('/projects', [
            'project_code' => 'PRJ-1001',
            'name' => 'Planning Test Project',
            'client_name' => 'Test Client',
            'location' => 'Dubai',
            'project_manager' => 'Project Manager',
            'status' => 'ongoing',
            'type' => 'rope_access',
            'start_date' => '2026-07-01',
            'expected_end_date' => '2026-12-31',
            'contract_value' => 100000,
            'cost_budget' => 75000,
            'progress_percentage' => 25,
            'description' => 'Safe additive project planning data.',
        ])
        ->assertRedirect('/projects/rope_access');

    $this->assertDatabaseHas('projects', [
        'project_code' => 'PRJ-1001',
        'name' => 'Planning Test Project',
        'contract_value' => 100000,
        'cost_budget' => 75000,
        'progress_percentage' => 25,
    ]);
});

test('project overview combines purchase bills and approved expenses into actual cost', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $project = Project::query()->create([
        'project_code' => 'PRJ-COST',
        'name' => 'Cost Test Project',
        'status' => 'ongoing',
        'type' => 'contracting',
        'contract_value' => 5000,
        'cost_budget' => 3000,
        'progress_percentage' => 40,
    ]);
    $supplier = Supplier::query()->create([
        'name' => 'Cost Test Supplier',
        'created_by' => $admin->id,
    ]);

    PurchaseBill::query()->create([
        'supplier_id' => $supplier->id,
        'project_id' => $project->id,
        'bill_number' => 'BILL-001',
        'bill_date' => '2026-07-20',
        'subtotal' => 1000,
        'vat_rate' => 0,
        'vat_amount' => 0,
        'total_amount' => 1000,
        'status' => 'unpaid',
        'created_by' => $admin->id,
    ]);

    foreach ([
        [EmployeeExpense::STATUS_APPROVED, 350],
        [EmployeeExpense::STATUS_PENDING, 900],
    ] as [$status, $amount]) {
        EmployeeExpense::query()->create([
            'submitted_by' => $admin->id,
            'project_id' => $project->id,
            'employee_type' => 'contracting',
            'expense_date' => '2026-07-21',
            'purpose' => 'Material purchase',
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    $this->actingAs($admin)
        ->get("/projects/overview?type=contracting&project_id={$project->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Overview')
            ->where('overviewRows.0.purchaseCost', 1000)
            ->where('overviewRows.0.expenseCost', 350)
            ->where('overviewRows.0.totalCost', 1350)
            ->where('overviewRows.0.budgetRemaining', 1650)
            ->where('overviewRows.0.expectedProfit', 3650)
            ->where('selectedProjectDetails.purchaseBills.0.billNumber', 'BILL-001')
            ->has('selectedProjectDetails.approvedExpenses', 1));
});

test('project with linked cost records cannot be deleted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $project = Project::query()->create([
        'name' => 'Protected Project',
        'status' => 'ongoing',
        'type' => 'contracting',
        'progress_percentage' => 0,
    ]);
    $supplier = Supplier::query()->create(['name' => 'Protected Supplier']);

    PurchaseBill::query()->create([
        'supplier_id' => $supplier->id,
        'project_id' => $project->id,
        'bill_number' => 'PROTECTED-001',
        'bill_date' => '2026-07-20',
        'subtotal' => 100,
        'vat_rate' => 0,
        'vat_amount' => 0,
        'total_amount' => 100,
        'status' => 'unpaid',
    ]);

    $this->actingAs($admin)
        ->delete("/projects/{$project->id}")
        ->assertSessionHasErrors('project');

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});

test('overhead multiplies basic salary into actual cost when it is switched on', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $project = Project::query()->create([
        'name' => 'Overhead Project',
        'status' => 'ongoing',
        'type' => 'contracting',
        'progress_percentage' => 0,
    ]);
    $employee = Employee::query()->create([
        'code' => '991',
        'name' => 'Overhead Worker',
        'profession' => 'Mason',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    EmployeePayrollSetting::query()->create([
        'employee_id' => $employee->id,
        'daily_salary' => 100,
        'standard_hours_per_day' => 8,
        'is_overtime_enabled' => true,
    ]);
    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'attendance_date' => '2026-08-10',
        'status' => AttendanceRecord::STATUS_PRESENT,
        'attendance_fraction' => 1,
        'overtime_hours' => 4,
        'submitted_by' => $admin->id,
    ]);

    // Off by default: nothing about an existing project's cost may move
    // until an admin deliberately switches overhead on.
    $this->actingAs($admin)
        ->get("/projects/overview?type=contracting&project_id={$project->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('overviewRows.0.basicCost', 100)
            ->where('overviewRows.0.overtimeCost', 50)
            ->where('overviewRows.0.overheadCost', 0)
            ->where('overviewRows.0.totalCost', 150));

    $this->actingAs($admin)
        ->post('/projects/overhead-settings', ['enabled' => true, 'multiplier' => 2])
        ->assertSessionHasNoErrors();

    // Basic 100 at 2x is a labour cost of 200, not 300 - the loaded figure
    // replaces basic pay. Overtime stays at its own 50.
    $this->actingAs($admin)
        ->get("/projects/overview?type=contracting&project_id={$project->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('overviewRows.0.basicCost', 100)
            ->where('overviewRows.0.overheadCost', 100)
            ->where('overviewRows.0.labourCost', 250)
            ->where('overviewRows.0.totalCost', 250));
});

test('the employee history total agrees with the overview once overhead is on', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $project = Project::query()->create([
        'name' => 'History Overhead Project',
        'status' => 'ongoing',
        'type' => 'contracting',
        'progress_percentage' => 0,
    ]);
    $employee = Employee::query()->create([
        'code' => '992',
        'name' => 'History Worker',
        'profession' => 'Helper',
        'type' => 'contracting',
        'status' => Employee::STATUS_ACTIVE,
    ]);
    EmployeePayrollSetting::query()->create([
        'employee_id' => $employee->id,
        'daily_salary' => 100,
        'standard_hours_per_day' => 8,
        'is_overtime_enabled' => true,
    ]);
    AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'attendance_date' => '2026-08-10',
        'status' => AttendanceRecord::STATUS_PRESENT,
        'attendance_fraction' => 1,
        'overtime_hours' => 4,
        'submitted_by' => $admin->id,
    ]);

    AppSetting::setValue('project_overhead_enabled', '1');
    AppSetting::setValue('project_overhead_multiplier', '2');

    $history = $this->actingAs($admin)
        ->getJson("/projects/{$project->id}/employee-history")
        ->assertOk()
        ->json();

    expect((float) $history['totals']['overheadCost'])->toBe(100.0)
        ->and((float) $history['totals']['totalCost'])->toBe(250.0)
        ->and((float) $history['employeeSummary'][0]['overheadCost'])->toBe(100.0)
        ->and((float) $history['employeeSummary'][0]['costShare'])->toBe(100.0);
});
