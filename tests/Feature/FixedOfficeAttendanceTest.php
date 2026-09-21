<?php

use App\Models\AppSetting;
use App\Models\OfficeLeaveRequest;
use App\Models\OfficeStaff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

function fixedStaff(string $mode = 'fixed'): OfficeStaff
{
    $user = User::factory()->create(['role' => 'office_staff', 'username' => fake()->unique()->userName()]);

    return OfficeStaff::create(['user_id' => $user->id, 'code' => (string) $user->id, 'name' => $user->name,
        'staff_type' => 'remote', 'status' => 'active', 'attendance_mode' => $mode,
        'fixed_start_time' => '09:00', 'fixed_end_time' => '17:00']);
}

test('fixed staff sign in with username only and reach their personal page', function () {
    $staff = fixedStaff();
    $this->post('/login', ['email' => $staff->user->username])
        ->assertRedirect('/office-attendance/personal');
    $this->assertAuthenticatedAs($staff->user);
});

test('fixed attendance is complete once and report credits eight hours even for remote staff', function () {
    $staff = fixedStaff();
    $this->actingAs($staff->user)->post('/office-attendance/personal')->assertSessionHasNoErrors();
    $record = $staff->attendances()->firstOrFail();
    expect($record->check_in_time)->toBe('09:00')->and($record->check_out_time)->toBe('17:00')
        ->and($record->is_fixed)->toBeTrue()->and($record->marked_at)->not->toBeNull();
    expect($record->sessions()->count())->toBe(1);
    $this->post('/office-attendance/personal')->assertSessionHasErrors('attendance');
    expect($staff->attendances()->count())->toBe(1);
    AppSetting::setValue('office_attendance.break_included', '0');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get("/office-attendance/report/{$staff->id}/details")
        ->assertOk()->assertJsonPath('rows.0.workMinutes', 480)->assertJsonPath('rows.0.lateLabel', 'Fixed Attendance');
});

test('public board and ordinary check in remain public', function () {
    $staff = fixedStaff('sessions');
    $this->get('/office-attendance/staff')->assertOk();
    $this->get("/office-attendance/mark/{$staff->id}")->assertOk();
    $this->post("/office-attendance/mark/{$staff->id}", ['work_mode' => 'office', 'attendance_action' => 'check_in'])
        ->assertSessionHasNoErrors()->assertRedirect('/office-attendance/staff');
    expect($staff->attendances()->count())->toBe(1);
    $this->get('/office-attendance/personal')->assertRedirect('/login');
    $this->actingAs($staff->user)->get('/office-attendance/personal')->assertNotFound();
});

test('leave is saved without smtp and admin approval prevents attendance on public and personal routes', function () {
    $staff = fixedStaff();
    $data = ['leave_date' => now('Asia/Dubai')->toDateString(), 'reason' => 'Family appointment'];
    $this->actingAs($staff->user)->post('/office-attendance/personal/leave', $data)->assertSessionHasNoErrors();
    $leave = OfficeLeaveRequest::firstOrFail();
    expect($leave->status)->toBe('pending')->and($leave->email_status)->toBe('disabled');
    $this->post('/office-attendance/personal/leave', $data)->assertSessionHasErrors('leave_date');
    $this->post('/office-attendance/personal')->assertSessionHasErrors('attendance');
    $this->put("/office-leave-requests/{$leave->id}", ['status' => 'approved'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->put("/office-leave-requests/{$leave->id}", ['status' => 'approved'])->assertSessionHasNoErrors();
    $this->post("/office-attendance/mark/{$staff->id}", ['work_mode' => 'remote', 'attendance_action' => 'check_in'])->assertSessionHasErrors('attendance');
    expect($leave->fresh()->status)->toBe('approved');
});

test('leave validation and existing attendance protect conflicting dates', function () {
    $staff = fixedStaff();
    $this->actingAs($staff->user)->post('/office-attendance/personal/leave', ['leave_date' => now()->subDay()->toDateString(), 'reason' => ''])->assertSessionHasErrors(['leave_date', 'reason']);
    $this->post('/office-attendance/personal');
    $this->post('/office-attendance/personal/leave', ['leave_date' => now('Asia/Dubai')->toDateString(), 'reason' => 'Appointment'])->assertSessionHasErrors('leave_date');
    $this->post("/office-attendance/mark/{$staff->id}", ['work_mode' => 'remote', 'attendance_action' => 'check_in'])->assertSessionHasErrors('attendance');
});

test('leave email uses the configured recipient and delivery failure preserves request', function () {
    $staff = fixedStaff();
    AppSetting::setValue('mail_enabled', '1');
    AppSetting::setValue('mail_host', 'smtp.example.test');
    AppSetting::setValue('mail_from_address', 'noreply@example.test');
    Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) {
        expect($body)->toContain('Family appointment');
        $message = new Message(new Email);
        $callback($message);
        expect($message->getSymfonyMessage()->getTo()[0]->getAddress())->toBe('info@almohafiz.com');

        return true;
    });
    $this->actingAs($staff->user)->post('/office-attendance/personal/leave', ['leave_date' => now('Asia/Dubai')->toDateString(), 'reason' => 'Family appointment'])->assertSessionHasNoErrors();
    expect(OfficeLeaveRequest::first()->email_status)->toBe('sent');
    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Mail unavailable'));
    $this->post('/office-attendance/personal/leave', ['leave_date' => now('Asia/Dubai')->addDay()->toDateString(), 'reason' => 'Appointment'])->assertSessionHasNoErrors();
    expect(OfficeLeaveRequest::latest('id')->first()->email_status)->toBe('failed');
});

test('admin enables fixed mode without a password', function () {
    $staff = fixedStaff('sessions');
    $data = ['code' => $staff->code, 'name' => $staff->name, 'username' => $staff->user->username,
        'staff_type' => 'remote', 'status' => 'active', 'attendance_mode' => 'fixed',
        'fixed_start_time' => '09:00', 'fixed_end_time' => '17:00'];
    $this->actingAs(User::factory()->create(['role' => 'admin']))->put("/office-staff/{$staff->id}", $data)->assertRedirect('/office-staff')->assertSessionHasNoErrors();
    expect($staff->fresh()->attendance_mode)->toBe('fixed');
});

test('personal page isolates staff and keeps UAE leave dates intact', function () {
    $staff = fixedStaff();
    $other = fixedStaff();
    $date = now('Asia/Dubai')->toDateString();
    OfficeLeaveRequest::create(['office_staff_id' => $staff->id, 'leave_date' => $date, 'reason' => 'Appointment']);
    OfficeLeaveRequest::create(['office_staff_id' => $other->id, 'leave_date' => $date, 'reason' => 'Private reason']);
    $this->actingAs($staff->user)->get('/office-attendance/personal')->assertOk()->assertInertia(fn ($page) => $page
        ->component('OfficeAttendance/Personal')->where('staff.code', $staff->code)
        ->has('leaves', 1)->where('leaves.0.leave_date', $date)->where('todayLeave.status', 'pending'));
    $this->get('/office-leave-requests')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/office-leave-requests')->assertOk();
    $staff->update(['status' => 'inactive']);
    $this->actingAs($staff->user)->post('/office-attendance/personal')->assertNotFound();
});

test('public fixed profile completes a blank attendance with scheduled times and report hours', function () {
    $staff = fixedStaff();
    $blank = $staff->attendances()->create(['attendance_date' => now('Asia/Dubai')->toDateString(), 'submitted_by' => $staff->user_id, 'work_mode' => 'remote']);
    $this->get("/office-attendance/mark/{$staff->id}")->assertOk()->assertInertia(fn ($page) => $page->component('OfficeAttendance/Personal')->where('publicProfile', true)->where('markUrl', "/office-attendance/mark/{$staff->id}"));
    $this->post("/office-attendance/mark/{$staff->id}")->assertSessionHasNoErrors();
    expect($blank->fresh()->check_in_time)->toBe('09:00')->and($blank->fresh()->check_out_time)->toBe('17:00');
    expect($staff->attendances()->count())->toBe(1);
    expect($blank->sessions()->count())->toBe(1);
    $this->get('/office-attendance/staff')->assertInertia(fn ($page) => $page->where('staffMembers.0.todayRecord.checkInTime', '09:00')->where('staffMembers.0.todayRecord.checkOutTime', '17:00'));
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get("/office-attendance/report/{$staff->id}/details")->assertOk()->assertJsonPath('rows.0.workMinutes', 480);
});

test('public fixed profile can submit leave without login', function () {
    $staff = fixedStaff();
    $this->post("/office-attendance/mark/{$staff->id}/leave", ['leave_date' => now('Asia/Dubai')->toDateString(), 'reason' => 'Appointment'])->assertSessionHasNoErrors();
    expect(OfficeLeaveRequest::first()->office_staff_id)->toBe($staff->id);
});

test('staff photos use current origin when APP_URL points to another local hostname', function () {
    config(['filesystems.disks.public.url' => 'http://localhost:8000/storage']);
    $staff = fixedStaff('sessions');
    $staff->update(['photo_path' => 'office-staff/portrait.png']);
    $this->get('/office-attendance/staff')->assertInertia(fn ($page) => $page
        ->where('staffMembers.0.photoUrl', '/storage/office-staff/portrait.png'));
});

test('admin edits the default leave message and staff receive the saved template', function () {
    $staff = fixedStaff();
    $this->get("/office-attendance/mark/{$staff->id}")->assertInertia(fn ($page) => $page
        ->where('leaveTemplate', OfficeLeaveRequest::messageTemplate()));
    $this->put('/office-leave-template', ['template' => 'Test'])->assertRedirect('/login');
    $this->actingAs($staff->user)->put('/office-leave-template', ['template' => 'Test'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->put('/office-leave-template', [
        'template' => "Subject: Leave - [Your Name]\nHi Manager, I need leave on [Leave Date].",
    ])->assertSessionHasNoErrors();
    $this->get("/office-attendance/mark/{$staff->id}")->assertInertia(fn ($page) => $page
        ->where('leaveTemplate', "Subject: Leave - [Your Name]\nHi Manager, I need leave on [Leave Date]."));
});

test('leave appears on its date and remains in historical reports without worked hours', function () {
    $staff = fixedStaff();
    $this->travelTo(Carbon::parse('2026-09-24 12:00:00', 'Asia/Dubai'));
    $leave = OfficeLeaveRequest::create(['office_staff_id' => $staff->id, 'leave_date' => '2026-09-25', 'reason' => 'Private medical reason']);
    $this->get('/office-attendance/staff')->assertInertia(fn ($page) => $page->where('staffMembers.0.status', 'not_marked'));
    $this->travelTo(Carbon::parse('2026-09-25 12:00:00', 'Asia/Dubai'));
    $this->get('/office-attendance/staff')->assertInertia(fn ($page) => $page
        ->where('staffMembers.0.status', 'on_leave')->where('staffMembers.0.statusLabel', 'On Leave (Pending approval)'));
    $leave->update(['status' => 'approved']);
    $this->get('/office-attendance/staff')->assertInertia(fn ($page) => $page->where('staffMembers.0.statusLabel', 'On Leave'));
    $this->travelTo(Carbon::parse('2026-09-26 12:00:00', 'Asia/Dubai'));
    $this->get("/office-attendance/mark/{$staff->id}")->assertInertia(fn ($page) => $page
        ->where('leaves.0.label', 'Was on leave')->where('leaves.0.reason', null));
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get("/office-attendance/report/{$staff->id}/details?from=2026-09-25&to=2026-09-25")
        ->assertOk()->assertJsonCount(0, 'rows')->assertJsonPath('leaveRows.0.label', 'Was on leave');
    $this->get('/office-attendance/report-print?from=2026-09-25&to=2026-09-25')->assertOk()->assertSee('Was on leave');
    $leave->update(['status' => 'rejected']);
    $this->get("/office-attendance/report/{$staff->id}/details?from=2026-09-25&to=2026-09-25")->assertJsonCount(0, 'leaveRows');
    expect($staff->attendances()->count())->toBe(0);
});
