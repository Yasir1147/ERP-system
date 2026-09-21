<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\OfficeLeaveRequest;
use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PersonalOfficeAttendanceController extends Controller
{
    private function staff(Request $request, ?OfficeStaff $officeStaff = null): OfficeStaff
    {
        if ($officeStaff) {
            abort_unless($officeStaff->status === 'active' && $officeStaff->attendance_mode === 'fixed', 404);

            return $officeStaff;
        }

        return OfficeStaff::where('user_id', $request->user()->id)
            ->where('status', 'active')->where('attendance_mode', 'fixed')->firstOrFail();
    }

    public function index(Request $request, ?OfficeStaff $officeStaff = null)
    {
        $staff = $this->staff($request, $officeStaff);

        return Inertia::render('OfficeAttendance/Personal', [
            'markUrl' => $officeStaff ? route('office-attendance.staff.store', $staff, false) : route('office-attendance.personal.mark', absolute: false),
            'leaveUrl' => $officeStaff ? route('office-attendance.staff.leave', $staff, false) : route('office-attendance.personal.leave', absolute: false),
            'publicProfile' => (bool) $officeStaff,
            'leaveTemplate' => OfficeLeaveRequest::messageTemplate(),
            'staff' => $staff->only('code', 'name', 'fixed_start_time', 'fixed_end_time'),
            'today' => now('Asia/Dubai')->toDateString(),
            'attendance' => $staff->attendances()->whereDate('attendance_date', now('Asia/Dubai')->toDateString())->first(),
            'todayLeave' => OfficeLeaveRequest::where('office_staff_id', $staff->id)->whereDate('leave_date', now('Asia/Dubai')->toDateString())->whereIn('status', ['pending', 'approved'])->first(['status']),
            'leaves' => OfficeLeaveRequest::where('office_staff_id', $staff->id)->latest('leave_date')->limit(30)->get()->map(fn ($leave) => [
                'id' => $leave->id, 'leave_date' => $leave->leave_date->toDateString(), 'status' => $leave->status,
                'label' => $leave->attendanceLabel(), 'reason' => $officeStaff ? null : $leave->reason,
            ]),
        ]);
    }

    public function mark(Request $request, ?OfficeStaff $officeStaff = null)
    {
        $staff = $this->staff($request, $officeStaff);
        DB::transaction(function () use ($staff) {
            $staff = OfficeStaff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $date = now('Asia/Dubai')->toDateString();
            $existing = $staff->attendances()->whereDate('attendance_date', $date)->first();
            if ($existing && ($existing->check_in_time || $existing->check_out_time || $existing->sessions()->exists())) {
                throw ValidationException::withMessages(['attendance' => 'Attendance is already recorded for today.']);
            }
            if (OfficeLeaveRequest::where('office_staff_id', $staff->id)->whereDate('leave_date', $date)->whereIn('status', ['pending', 'approved'])->exists()) {
                throw ValidationException::withMessages(['attendance' => 'A leave request exists for today. Ask an admin to review it first.']);
            }
            $attendance = $existing ?? $staff->attendances()->make();
            $attendance->fill([
                'submitted_by' => $staff->user_id,
                'attendance_date' => $date,
                'work_mode' => $staff->staff_type === 'remote' ? 'remote' : 'office',
                'check_in_time' => $staff->fixed_start_time,
                'check_out_time' => $staff->fixed_end_time,
                'is_fixed' => true,
                'marked_at' => now(),
                'note' => 'Fixed Attendance',
            ])->save();
            $attendance->sessions()->create([
                'check_in_time' => $staff->fixed_start_time,
                'check_out_time' => $staff->fixed_end_time,
            ]);
        });

        return back()->with('success', 'Attendance completed.');
    }

    public function leave(Request $request, ?OfficeStaff $officeStaff = null)
    {
        $staff = $this->staff($request, $officeStaff);
        $data = $request->validate([
            'leave_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Dubai')->toDateString()],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $leave = DB::transaction(function () use ($staff, $data) {
            OfficeStaff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if ($staff->attendances()->whereDate('attendance_date', $data['leave_date'])->exists()) {
                throw ValidationException::withMessages(['leave_date' => 'Attendance already exists for this date.']);
            }
            if (OfficeLeaveRequest::where('office_staff_id', $staff->id)->whereDate('leave_date', $data['leave_date'])->exists()) {
                throw ValidationException::withMessages(['leave_date' => 'A leave request has already been submitted for this date.']);
            }

            return OfficeLeaveRequest::create([...$data, 'office_staff_id' => $staff->id]);
        });
        $this->sendEmail($leave);

        return back()->with('success', $leave->email_status === 'sent'
            ? 'Leave request submitted and email sent.'
            : 'Leave request saved. Email could not be sent; an admin can retry from Leave Requests.');
    }

    public function requests()
    {
        return Inertia::render('OfficeAttendance/Leaves', [
            'leaveTemplate' => OfficeLeaveRequest::messageTemplate(),
            'leaves' => OfficeLeaveRequest::with('officeStaff:id,code,name')->latest()->paginate(25),
        ]);
    }

    public function updateTemplate(Request $request)
    {
        $data = $request->validate(['template' => ['required', 'string', 'max:2000']]);
        AppSetting::setValue('office_leave_message_template', $data['template']);

        return back()->with('success', 'Default leave message saved.');
    }

    public function review(Request $request, OfficeLeaveRequest $leave)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        DB::transaction(function () use ($request, $leave, $data) {
            OfficeStaff::whereKey($leave->office_staff_id)->lockForUpdate()->firstOrFail();
            $leave = OfficeLeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if ($leave->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
            }
            if ($data['status'] === 'approved' && OfficeStaffAttendance::where('office_staff_id', $leave->office_staff_id)->whereDate('attendance_date', $leave->leave_date)->exists()) {
                throw ValidationException::withMessages(['status' => 'Attendance exists for this date. Resolve it before approving leave.']);
            }
            $leave->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });

        return back()->with('success', 'Leave request reviewed.');
    }

    public function retryEmail(OfficeLeaveRequest $leave)
    {
        if ($leave->email_status !== 'sent') {
            $this->sendEmail($leave);
        }

        return back()->with('success', $leave->email_status === 'sent' ? 'Email sent.' : 'Email could not be sent. Check Settings > Mail.');
    }

    private function sendEmail(OfficeLeaveRequest $leave): void
    {
        try {
            if (! AppSetting::configureMailer()) {
                $leave->update(['email_status' => 'disabled']);

                return;
            }
            $staff = $leave->officeStaff;
            $subject = 'Sick Leave - '.$staff->name;
            if (preg_match('/^Subject:\s*([^\r\n]+)/i', $leave->reason, $match)) {
                $subject = mb_substr(trim($match[1]), 0, 200);
            }
            Mail::raw("Office staff leave request\n\nEmployee: {$staff->code} - {$staff->name}\nDate: {$leave->leave_date->toDateString()}\nReason: {$leave->reason}\n\nReview: ".route('office-leaves.index'), function ($message) use ($subject) {
                $message->to('info@almohafiz.com')->subject($subject);
            });
            $leave->update(['email_status' => 'sent']);
        } catch (\Throwable $exception) {
            $leave->update(['email_status' => 'failed']);
            Log::warning('Office leave email failed.', ['leave_id' => $leave->id, 'message' => $exception->getMessage()]);
        }
    }
}
