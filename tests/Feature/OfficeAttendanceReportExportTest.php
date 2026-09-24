<?php

use App\Models\AppSetting;
use App\Models\OfficeLeaveRequest;
use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

function reportStaff(string $mode = 'fixed'): OfficeStaff
{
    $user = User::factory()->create(['role' => 'office_staff']);

    return OfficeStaff::create(['user_id' => $user->id, 'code' => '00'.$user->id, 'name' => 'Report Staff '.$user->id,
        'designation' => 'Marketing Manager', 'staff_type' => 'remote', 'status' => 'active',
        'attendance_mode' => $mode, 'fixed_start_time' => '09:00', 'fixed_end_time' => '17:00']);
}

function reportEntry(OfficeStaff $staff, string $date, string $note = ''): array
{
    return ['office_staff_id' => $staff->id, 'attendance_date' => $date, 'work_mode' => 'remote',
        'check_in_time' => '09:00', 'check_out_time' => '17:00', 'note' => $note];
}

test('admin adds backdated and future complete attendance with own audit identity', function () {
    $staff = reportStaff();
    $admin = User::factory()->create(['role' => 'admin']);
    foreach (['2020-01-02', '2030-02-03'] as $date) {
        $this->actingAs($admin)->post('/office-attendance/report', reportEntry($staff, $date))->assertRedirect()->assertSessionHasNoErrors();
        $record = $staff->attendances()->whereDate('attendance_date', $date)->firstOrFail();
        expect($record->submitted_by)->toBe($admin->id)->and($record->is_fixed)->toBeTrue();
        expect($record->sessions()->count())->toBe(1);
        $this->get("/office-attendance/report/{$staff->id}/details?from={$date}&to={$date}")->assertJsonPath('rows.0.workMinutes', 480);
    }
});

test('admin creation rejects duplicates invalid times and conflicting leave', function () {
    $staff = reportStaff();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $data = reportEntry($staff, '2026-09-01');
    $this->post('/office-attendance/report', $data)->assertSessionHasNoErrors();
    $this->post('/office-attendance/report', $data)->assertSessionHasErrors('attendance_date');
    expect($staff->attendances()->count())->toBe(1);
    $this->post('/office-attendance/report', [...$data, 'attendance_date' => '2026-09-02', 'check_out_time' => '08:00'])->assertSessionHasErrors('check_out_time');
    OfficeLeaveRequest::create(['office_staff_id' => $staff->id, 'leave_date' => '2026-09-03', 'reason' => 'Leave', 'status' => 'approved']);
    $this->post('/office-attendance/report', [...$data, 'attendance_date' => '2026-09-03'])->assertSessionHasErrors('attendance_date');
    $this->post('/office-attendance/report', [...$data, 'attendance_date' => '2026-02-30'])->assertSessionHasErrors('attendance_date');
});

test('staff cannot add admin attendance or download report', function () {
    $staff = reportStaff();
    $this->actingAs($staff->user)->post('/office-attendance/report', reportEntry($staff, '2026-09-01'))->assertForbidden();
    $this->get('/office-attendance/report-export')->assertForbidden();
});

test('legacy remote times count hours without changing historical records', function () {
    $staff = reportStaff('sessions');
    $record = OfficeStaffAttendance::create([...reportEntry($staff, '2026-09-01'), 'submitted_by' => $staff->user_id]);
    AppSetting::setValue('office_attendance.break_included', '0');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get("/office-attendance/report/{$staff->id}/details?from=2026-09-01&to=2026-09-01")
        ->assertJsonPath('rows.0.workMinutes', 480)->assertJsonPath('rows.0.overtimeMinutes', 0);
    expect($record->fresh()->is_fixed)->toBeFalse()->and($record->sessions()->count())->toBe(0);
});

test('single staff PDF moves identity to header and all staff PDF retains identity columns', function () {
    $staff = reportStaff();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->post('/office-attendance/report', reportEntry($staff, '2026-09-01'));
    $response = $this->get("/office-attendance/report-print?staff_id={$staff->id}&from=2026-09-01&to=2026-09-30");
    $response->assertOk()->assertSee('Marketing Manager')->assertDontSee('<th>Staff</th>', false)->assertDontSee('<th>Designation</th>', false);
    expect(substr_count($response->content(), $staff->name))->toBe(1);
    $this->get('/office-attendance/report-print?from=2026-09-01&to=2026-09-30')->assertOk()->assertSee('<th>Staff</th>', false)->assertSee('<th>Designation</th>', false);
});

test('Excel matches filtered report includes hours leave and safe text values', function () {
    $staff = reportStaff();
    $other = reportStaff();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->post('/office-attendance/report', reportEntry($staff, '2026-09-01', '=1+1'));
    $this->post('/office-attendance/report', reportEntry($staff, '2026-08-01', 'Outside range'));
    $this->post('/office-attendance/report', reportEntry($other, '2026-09-01', 'Other staff'));
    OfficeLeaveRequest::create(['office_staff_id' => $staff->id, 'leave_date' => '2026-09-02', 'reason' => 'Leave', 'status' => 'approved']);
    $response = $this->get("/office-attendance/report-export?staff_id={$staff->id}&from=2026-09-01&to=2026-09-30&work_mode=remote");
    $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = tempnam(sys_get_temp_dir(), 'office-export-');
    try {
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        $rows = $sheet->toArray();
        $text = json_encode($rows);
        expect($text)->toContain('Marketing Manager')->toContain('01\/09\/2026')->not->toContain('Outside range')->not->toContain('Other staff');
        $header = collect($rows)->search(fn ($row) => $row[0] === 'Date' && $row[1] === 'Work Mode');
        expect($header)->not->toBeFalse();
        $row = $header + 2;
        $this->assertEqualsWithDelta(480 / 1440, $sheet->getCell('F'.$row)->getValue(), 0.00000001);
        expect($sheet->getCell('I'.$row)->getValue())->toBe('=1+1')->and($sheet->getCell('I'.$row)->getDataType())->toBe('s');
        $this->assertEqualsWithDelta(480 / 1440, $sheet->getCell('F'.($row + 1))->getCalculatedValue(), 0.00000001);
        expect($text)->toContain('Leave in Selected Date Range');
    } finally {
        @unlink($path);
    }
});
