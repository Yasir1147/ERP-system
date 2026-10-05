<?php

use App\Models\AppSetting;
use App\Models\OfficeLeaveRequest;
use App\Models\OfficeStaff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

function timesheetPerson(string $code = '001'): OfficeStaff
{
    $user = User::factory()->create(['role' => 'office_staff']);

    return OfficeStaff::create(['user_id' => $user->id, 'code' => $code, 'name' => 'Timesheet Staff '.$code,
        'designation' => 'Inspector', 'staff_type' => 'remote', 'status' => 'active', 'attendance_mode' => 'fixed']);
}

test('office timesheet distinguishes attendance approved pending and missing days and shares report hours', function () {
    $person = timesheetPerson();
    $empty = timesheetPerson('002');
    $person->attendances()->create(['attendance_date' => '2026-09-01', 'work_mode' => 'remote', 'check_in_time' => '09:00', 'check_out_time' => '17:00', 'is_fixed' => true, 'submitted_by' => $person->user_id]);
    $person->attendances()->create(['attendance_date' => '2026-09-05', 'work_mode' => 'remote', 'submitted_by' => $person->user_id]);
    foreach (['2026-09-02' => 'approved', '2026-09-03' => 'pending', '2026-09-04' => 'rejected', '2026-09-01' => 'approved'] as $date => $status) {
        OfficeLeaveRequest::create(['office_staff_id' => $person->id, 'leave_date' => $date, 'reason' => 'Private reason', 'status' => $status]);
    }
    AppSetting::setValue('office_attendance.break_included', '0');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get('/office-attendance/timesheet?month=2026-09')->assertInertia(fn (Assert $page) => $page
        ->component('OfficeAttendance/Timesheet')->has('days', 30)->has('rows', 2)
        ->where('rows.0.cells.0.status', 'P')->where('rows.0.cells.1.status', 'L')->where('rows.0.cells.2.status', 'LP')
        ->where('rows.0.cells.3.status', '-')->where('rows.0.cells.4.status', '-')->where('rows.0.cells.5.status', '-')
        ->where('rows.0.present', 1)->where('rows.0.leave', 1)->where('rows.0.pending', 1)->where('rows.0.workMinutes', 480)
        ->where('rows.1.present', 0)->where('dailyPresent.0', 1)->where('totals.workMinutes', 480));
    $this->get("/office-attendance/report/{$person->id}/details?from=2026-09-01&to=2026-09-01")->assertJsonPath('rows.0.workMinutes', 480);
    $this->get('/office-attendance/timesheet?month=2026-09')->assertDontSee('Private reason');
});

test('office timesheet sums multiple sessions applies office break and keeps historical inactive staff', function () {
    $person = timesheetPerson();
    $person->update(['status' => 'inactive']);
    $record = $person->attendances()->create(['attendance_date' => '2026-09-01', 'work_mode' => 'office', 'submitted_by' => $person->user_id]);
    // Insert out of time order to ensure the detail is chronologically ordered.
    $record->sessions()->create(['check_in_time' => '15:00', 'check_out_time' => '18:00']);
    $record->sessions()->create(['check_in_time' => '09:00', 'check_out_time' => '14:00']);
    AppSetting::setValue('office_attendance.break_included', '0');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/office-attendance/timesheet?month=2026-09')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.workMinutes', 420)
            ->where('rows.0.cells.0.detail.checkInDisplay', '9:00 AM')->where('rows.0.cells.0.detail.checkOutDisplay', '6:00 PM'));
});

test('office timesheet filters and exports agree and February includes leap day', function () {
    $person = timesheetPerson();
    timesheetPerson('002');
    $person->attendances()->create(['attendance_date' => '2024-02-29', 'work_mode' => 'remote', 'check_in_time' => '09:00', 'check_out_time' => '17:00', 'submitted_by' => $person->user_id]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $query = '?month=2024-02&staff_id='.$person->id.'&search=001';
    $this->get('/office-attendance/timesheet'.$query)->assertInertia(fn (Assert $page) => $page->has('days', 29)->has('rows', 1)->where('rows.0.cells.28.status', 'P')->where('totals.present', 1));
    $this->get('/office-attendance/timesheet-print'.$query)->assertOk()->assertSee($person->name)->assertDontSee('Timesheet Staff 002')->assertSee('8h');
    $response = $this->get('/office-attendance/timesheet-export'.$query)->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'timesheet-');
    try {
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();
        $index = collect($rows)->search(fn ($row) => $row[0] === 'Staff');
        expect($index)->not->toBeFalse();
        $row = $index + 2;
        expect($sheet->getCell('A'.$row)->getValue())->toBe('001 - '.$person->name);
        expect($sheet->getCell('AD'.$row)->getValue())->toBe('P');
        expect($sheet->getCell('AE'.$row)->getValue())->toBe(1);
        $this->assertEqualsWithDelta(480 / 1440, $sheet->getCell('AH'.$row)->getValue(), 0.00000001);
        expect(json_encode($rows))->not->toContain('Timesheet Staff 002');
    } finally {
        @unlink($path);
    }
});

test('office timesheet validates filters and protects every output from non admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    foreach (['timesheet', 'timesheet-print', 'timesheet-export'] as $route) {
        $this->getJson('/office-attendance/'.$route.'?month=2026-13')->assertUnprocessable();
        $this->getJson('/office-attendance/'.$route.'?staff_id=99999')->assertUnprocessable();
    }
    foreach (['office_staff', 'attendance_user'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['timesheet', 'timesheet-print', 'timesheet-export'] as $route) {
            $this->get('/office-attendance/'.$route)->assertForbidden();
        }
    }
});

test('multiple staff selection all and empty selections match across the timesheet and exports', function () {
    $first = timesheetPerson('001');
    $second = timesheetPerson('002');
    $third = timesheetPerson('003');
    $third->update(['status' => 'inactive']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $query = '?'.http_build_query(['month' => '2026-09', 'selection' => 'selected', 'staff_ids' => [$first->id, $second->id]]);
    $this->get('/office-attendance/timesheet'.$query)->assertInertia(fn (Assert $page) => $page
        ->has('rows', 2)->where('rows.0.id', $first->id)->where('rows.1.id', $second->id)
        ->where('filters.staffIds', [$first->id, $second->id])->where('filters.allStaff', false));
    $this->get('/office-attendance/timesheet-print'.$query)->assertOk()->assertSee($first->name)->assertSee($second->name)->assertDontSee($third->name);
    $response = $this->get('/office-attendance/timesheet-export'.$query)->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'timesheet-multi-');
    try {
        file_put_contents($path, $response->streamedContent());
        $text = json_encode(IOFactory::load($path)->getActiveSheet()->toArray());
        expect($text)->toContain($first->name)->toContain($second->name)->not->toContain($third->name);
    } finally {
        @unlink($path);
    }
    $this->get('/office-attendance/timesheet?month=2026-09&selection=all')->assertInertia(fn (Assert $page) => $page->has('rows', 3)->where('filters.allStaff', true));
    $this->get('/office-attendance/timesheet?month=2026-09&selection=selected')->assertInertia(fn (Assert $page) => $page->has('rows', 0)->where('totals.staff', 0));
    $this->get('/office-attendance/timesheet-print?selection=selected')->assertOk()->assertDontSee($first->name);
    $this->getJson('/office-attendance/timesheet?staff_ids[]=99999')->assertUnprocessable();
});
