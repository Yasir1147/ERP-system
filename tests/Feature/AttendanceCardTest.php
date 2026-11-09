<?php

use App\Models\AttendanceCardTemplate;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function cardEmployee(string $code, string $type = 'contracting'): Employee
{
    return Employee::create(['code' => $code, 'name' => 'Card Worker '.$code, 'profession' => 'Foreman', 'type' => $type, 'status' => 'active']);
}

test('monthly cards include real attendance leaves site and overtime with calendar precision', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = cardEmployee('001');
    $project = Project::create(['name' => 'Main Site', 'project_code' => 'PR 267', 'type' => 'contracting', 'status' => 'ongoing']);
    $otProject = Project::create(['name' => 'OT Site', 'project_code' => 'OT 10', 'type' => 'contracting', 'status' => 'ongoing']);
    EmployeeLeave::create(['employee_id' => $employee->id, 'start_date' => '2024-02-03', 'end_date' => '2024-02-05', 'reason' => 'Home leave', 'created_by' => $admin->id]);
    AttendanceRecord::create(['employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => '2024-02-04', 'status' => 'present', 'attendance_fraction' => 0.5, 'overtime_hours' => 85 / 60, 'overtime_project_id' => $otProject->id, 'submitted_by' => $admin->id]);
    AttendanceRecord::create(['employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => '2024-02-02', 'status' => 'present', 'attendance_fraction' => 1, 'overtime_hours' => 2, 'submitted_by' => $admin->id]);
    AttendanceRecord::create(['employee_id' => $employee->id, 'attendance_date' => '2024-02-06', 'status' => 'absent', 'submitted_by' => $admin->id]);
    AttendanceRecord::create(['employee_id' => $employee->id, 'project_id' => $project->id, 'attendance_date' => '2024-01-31', 'status' => 'present', 'overtime_hours' => 5, 'submitted_by' => $admin->id]);
    Holiday::create(['holiday_date' => '2024-02-07', 'name' => 'Company Holiday', 'is_paid' => true, 'employee_type' => 'contracting']);
    $this->actingAs($admin)->get('/attendance-cards?month=2024-02&employee_ids[]='.$employee->id)->assertInertia(fn (Assert $page) => $page
        ->component('AttendanceCards/Index')->has('report.cards', 1)->has('report.cards.0.days', 29)
        ->where('report.cards.0.code', '001')->where('report.cards.0.presentDays', 1.5)->where('report.cards.0.absentDays', 1)
        ->where('report.cards.0.leaveDays', 2)->where('report.cards.0.overtimeMinutes', 205)->where('report.cards.0.overtimeLabel', '3 hr 25 min')
        ->where('report.cards.0.days.0.mark', '')->where('report.cards.0.days.1.mark', 'P')->where('report.cards.0.days.1.restLabel', null)
        ->where('report.cards.0.days.2.mark', 'L')->where('report.cards.0.days.3.mark', '½')->where('report.cards.0.days.3.site', 'PR 267')
        ->where('report.cards.0.days.3.otSite', 'OT 10')->where('report.cards.0.days.3.overtime', '1:25')
        ->where('report.cards.0.days.5.mark', 'A')->where('report.cards.0.days.6.mark', 'H')->where('report.cards.0.days.8.restLabel', 'Friday'));
    $this->get('/attendance-cards?month=2025-02&employee_ids[]='.$employee->id)->assertInertia(fn (Assert $page) => $page->has('report.cards.0.days', 28));
});

test('card formats save validate geometry preserve concurrent changes and duplicate', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $layout = AttendanceCardTemplate::defaultLayout();
    $layout['text_color'] = '#123ABC';
    $this->post('/attendance-card-templates', ['name' => 'Custom Card', 'layout' => $layout])->assertSessionHasNoErrors()->assertRedirect();
    $template = AttendanceCardTemplate::firstOrFail();
    expect($template->layout['text_color'])->toBe('#123ABC');
    $layout['sections']['title']['x'] = 7;
    $this->put('/attendance-card-templates/'.$template->id, ['name' => 'Custom Card', 'layout' => $layout, 'version' => 1])->assertSessionHasNoErrors();
    $this->put('/attendance-card-templates/'.$template->id, ['name' => 'Lost Update', 'layout' => $layout, 'version' => 1])->assertSessionHasErrors('version');
    expect($template->fresh()->name)->toBe('Custom Card')->and($template->fresh()->version)->toBe(2);
    $layout['sections']['grid']['x'] = 180;
    $this->put('/attendance-card-templates/'.$template->id, ['name' => 'Custom Card', 'layout' => $layout, 'version' => 2])->assertSessionHasErrors('layout.sections.grid');
    $layout['sections']['grid']['x'] = 3;
    $layout['text_color'] = 'url(example.com)';
    $this->put('/attendance-card-templates/'.$template->id, ['name' => 'Custom Card', 'layout' => $layout, 'version' => 2])->assertSessionHasErrors('layout.text_color');
    $this->post('/attendance-card-templates/'.$template->id.'/duplicate')->assertRedirect();
    $copy = AttendanceCardTemplate::whereKeyNot($template->id)->firstOrFail();
    expect($copy->layout)->toBe($template->fresh()->layout)->and($copy->name)->toBe('Custom Card Copy')->and($copy->version)->toBe(1);
});

test('card logos and signatures upload safely and reject non image files', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $template = AttendanceCardTemplate::create(['name' => 'Images', 'layout' => AttendanceCardTemplate::defaultLayout()]);
    foreach (['logo', 'signature'] as $kind) {
        $this->post('/attendance-card-templates/'.$template->id.'/asset', ['kind' => $kind, 'image' => UploadedFile::fake()->image($kind.'.png')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($template->fresh()->{$kind.'_path'});
    }
    $this->post('/attendance-card-templates/'.$template->id.'/asset', ['kind' => 'logo', 'image' => UploadedFile::fake()->create('bad.svg', 2, 'image/svg+xml')])->assertSessionHasErrors('image');
});

test('card selection respects employee types supports both print layouts and checks paper fit', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $a = cardEmployee('101');
    $b = cardEmployee('102');
    $left = cardEmployee('103');
    $left->update(['status' => 'left']);
    $rope = cardEmployee('104', 'rope_access');
    $this->actingAs($admin)->get('/attendance-cards')->assertInertia(fn (Assert $page) => $page->has('report.cards', 0));
    $this->get('/attendance-cards?all_employees=1&employee_type=contracting')->assertInertia(fn (Assert $page) => $page->has('report.cards', 2));
    foreach ([1, 2, 4] as $perPage) {
        $this->get('/attendance-cards/print?month=2026-10&per_page='.$perPage.'&employee_ids[]='.$a->id.'&employee_ids[]='.$b->id)
            ->assertInertia(fn (Assert $page) => $page->component('AttendanceCards/Print')->has('report.cards', 2)->where('report.filters.perPage', $perPage));
    }
    $layout = AttendanceCardTemplate::defaultLayout();
    $layout['height'] = 200;
    $template = AttendanceCardTemplate::create(['name' => 'Tall', 'layout' => $layout]);
    $this->getJson('/attendance-cards/print?template_id='.$template->id.'&per_page=2')->assertUnprocessable();
    $this->get('/attendance-cards/print?template_id='.$template->id.'&per_page=1')->assertOk();
    $this->getJson('/attendance-cards?employee_ids[]='.$rope->id.'&employee_type=contracting')->assertUnprocessable();
    $this->getJson('/attendance-cards?month=2026-13')->assertUnprocessable();
});

test('all attendance card and designer routes are admin only', function () {
    $template = AttendanceCardTemplate::create(['name' => 'Protected', 'layout' => AttendanceCardTemplate::defaultLayout()]);
    foreach (['attendance_user', 'office_staff'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['/attendance-cards', '/attendance-cards/print', '/attendance-card-templates/create', '/attendance-card-templates/'.$template->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/attendance-card-templates', [])->assertForbidden();
        $this->put('/attendance-card-templates/'.$template->id, [])->assertForbidden();
        $this->post('/attendance-card-templates/'.$template->id.'/duplicate')->assertForbidden();
        $this->post('/attendance-card-templates/'.$template->id.'/asset', [])->assertForbidden();
    }
});

test('blank cards print the chosen month without employee details or stored attendance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = cardEmployee('Blank-Private');
    AttendanceRecord::create(['employee_id' => $employee->id, 'attendance_date' => '2024-02-03', 'status' => 'absent', 'submitted_by' => $admin->id]);
    $template = AttendanceCardTemplate::create(['name' => 'Blank Signed Cards', 'layout' => AttendanceCardTemplate::defaultLayout(), 'signature_path' => 'attendance-card-assets/signature.png']);
    $query = '?blank_cards=1&blank_count=3&month=2024-02&all_employees=1&template_id='.$template->id;
    $this->actingAs($admin)->get('/attendance-cards'.$query)->assertInertia(fn (Assert $page) => $page
        ->has('report.cards', 3)->where('report.cards.0.blank', true)
        ->where('report.cards.0.employeeId', -1)->where('report.cards.2.employeeId', -3)
        ->where('report.cards.0.name', '')->where('report.cards.0.code', '')->where('report.cards.0.profession', '')
        ->where('report.cards.0.year', 2024)->where('report.cards.0.month', 'Feb')->has('report.cards.0.days', 29)
        ->where('report.cards.0.days.1.restLabel', 'Friday')->where('report.cards.0.days.2.mark', '')
        ->where('report.cards.0.days.2.site', '')->where('report.cards.0.days.2.overtime', '')
        ->where('report.cards.0.overtimeLabel', '')->where('report.filters.employeeIds', [])
        ->where('report.filters.allEmployees', false)
        ->where('report.template.signatureUrl', fn ($url) => str_contains($url, 'attendance-card-assets/signature.png')));
    foreach ([1, 2, 4] as $perPage) {
        $this->get('/attendance-cards/print'.$query.'&per_page='.$perPage)->assertInertia(fn (Assert $page) => $page
            ->component('AttendanceCards/Print')->has('report.cards', 3)->where('report.filters.perPage', $perPage)
            ->where('report.cards.0.name', '')->where('report.cards.0.blank', true));
    }
    foreach ([0, 101, -1] as $invalid) {
        $this->getJson('/attendance-cards?blank_cards=1&blank_count='.$invalid)->assertUnprocessable();
    }
    expect(AttendanceRecord::count())->toBe(1);
});

test('signature positioning is customizable and old saved formats receive compatible placement', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $layout = AttendanceCardTemplate::defaultLayout();
    $layout['sections']['signature']['x'] = 8;
    $layout['sections']['signature']['y'] = 113;
    $layout['sections']['signature']['width'] = 40;
    $layout['sections']['signature']['height'] = 10;
    $this->post('/attendance-card-templates', ['name' => 'Positioned Signature', 'layout' => $layout])->assertSessionHasNoErrors();
    $saved = AttendanceCardTemplate::firstOrFail();
    expect($saved->layout['sections']['signature']['x'])->toBe(8)->and($saved->layout['sections']['signature']['height'])->toBe(10);
    unset($layout['sections']['signature']);
    $layout['sections']['footer']['x'] = 4;
    $layout['sections']['footer']['y'] = 114;
    $saved->update(['layout' => $layout]);
    expect($saved->payload()['layout']['sections']['signature']['x'])->toBe(6)
        ->and($saved->payload()['layout']['sections']['signature']['y'])->toBe(114);
});

test('four card printing fits an A4 landscape grid and preserves template measurements', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get('/attendance-cards/print?blank_cards=1&blank_count=5&per_page=4')->assertInertia(fn (Assert $page) => $page
        ->has('report.cards', 5)->where('report.filters.perPage', 4)
        ->where('printLayout.columns', 2)->where('printLayout.pageWidth', 297)->where('printLayout.pageHeight', 210)
        ->where('printLayout.scale', fn ($scale) => abs($scale - 93 / 132) < 0.000001)
        ->where('report.template.layout.width', 190)->where('report.template.layout.height', 132));
    $layout = AttendanceCardTemplate::defaultLayout();
    $layout['height'] = 277;
    $template = AttendanceCardTemplate::create(['name' => 'Four Tall Cards', 'layout' => $layout]);
    $this->get('/attendance-cards/print?blank_cards=1&blank_count=4&per_page=4&template_id='.$template->id)->assertInertia(fn (Assert $page) => $page
        ->where('printLayout.scale', fn ($scale) => abs($scale - 93 / 277) < 0.000001));
    expect($template->fresh()->layout)->toBe($layout);
    $this->get('/attendance-cards/print?blank_cards=1&per_page=1')->assertInertia(fn (Assert $page) => $page
        ->where('printLayout.scale', 1)->where('printLayout.pageWidth', 210)->where('printLayout.pageHeight', 297));
    $this->getJson('/attendance-cards/print?per_page=3')->assertUnprocessable();
});
