<?php

use App\Models\EmployeeExpense;
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
