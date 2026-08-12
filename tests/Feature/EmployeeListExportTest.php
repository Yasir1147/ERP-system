<?php

use App\Models\Employee;
use App\Models\User;

function exportAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function listEmployee(string $type, string $code, string $name, string $status = Employee::STATUS_ACTIVE): Employee
{
    return Employee::create([
        'code' => $code,
        'name' => $name,
        'profession' => 'Level 1',
        'type' => $type,
        'status' => $status,
    ]);
}

it('downloads an xlsx for rope access employees', function () {
    $admin = exportAdmin();
    listEmployee('rope_access', '0101', 'Rope Worker');

    $response = $this->actingAs($admin)->get('/employees/rope_access/export');

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('content-disposition'))->toContain('employees-rope-access-employee');
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

it('downloads an xlsx for contracting employees', function () {
    $admin = exportAdmin();
    listEmployee('contracting', '0201', 'Contracting Worker');

    $response = $this->actingAs($admin)->get('/employees/contracting/export');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('employees-contracting-employee');
});

it('exports only the requested employee type', function () {
    $admin = exportAdmin();
    listEmployee('rope_access', '0301', 'Rope Only');
    listEmployee('contracting', '0302', 'Contracting Only');

    $this->actingAs($admin)
        ->get('/employees/rope_access/print')
        ->assertOk()
        ->assertSee('Rope Only')
        ->assertDontSee('Contracting Only');
});

it('renders the print view with counts by status', function () {
    $admin = exportAdmin();
    listEmployee('contracting', '0401', 'Active One');
    listEmployee('contracting', '0402', 'Leave One', Employee::STATUS_ON_LEAVE);
    listEmployee('contracting', '0403', 'Left One', Employee::STATUS_LEFT);

    $this->actingAs($admin)
        ->get('/employees/contracting/print')
        ->assertOk()
        ->assertSee('Employee List')
        ->assertSee('Contracting Employee')
        ->assertSee('0401')
        ->assertSee('Active One')
        ->assertSee('On Leave')
        ->assertSee('3 employees listed.');
});

it('orders the export by employee code numerically', function () {
    $admin = exportAdmin();
    listEmployee('contracting', '20', 'Twenty');
    listEmployee('contracting', '3', 'Three');
    listEmployee('contracting', '100', 'Hundred');

    $body = $this->actingAs($admin)->get('/employees/contracting/print')->content();

    // 3 before 20 before 100: a plain string sort would put 100 first.
    expect(strpos($body, 'Three'))->toBeLessThan(strpos($body, 'Twenty'));
    expect(strpos($body, 'Twenty'))->toBeLessThan(strpos($body, 'Hundred'));
});

it('returns 404 for an unknown employee type', function () {
    $this->actingAs(exportAdmin())
        ->get('/employees/banana/export')
        ->assertNotFound();
});

it('blocks non-admin users from the employee exports', function () {
    $attendanceUser = User::factory()->create([
        'role' => User::ROLE_ATTENDANCE,
        'attendance_employee_type' => 'rope_access',
    ]);

    $this->actingAs($attendanceUser)->get('/employees/rope_access/export')->assertForbidden();
    $this->actingAs($attendanceUser)->get('/employees/rope_access/print')->assertForbidden();
});

it('handles an empty employee list without failing', function () {
    $admin = exportAdmin();

    $this->actingAs($admin)->get('/employees/rope_access/export')->assertOk();
    $this->actingAs($admin)
        ->get('/employees/rope_access/print')
        ->assertOk()
        ->assertSee('No employees in this category.');
});
