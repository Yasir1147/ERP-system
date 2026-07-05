<?php

namespace App\Http\Controllers;

use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use App\Models\OfficeStaffAttendanceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfficeAttendanceController extends Controller
{
    public function create(Request $request): Response
    {
        $staff = OfficeStaff::query()
            ->where('user_id', $request->user()->id)
            ->where('status', OfficeStaff::STATUS_ACTIVE)
            ->firstOrFail();

        $todayRecord = OfficeStaffAttendance::query()
            ->with(['sessions' => fn ($query) => $query->orderBy('check_in_time')->orderBy('id')])
            ->where('office_staff_id', $staff->id)
            ->whereDate('attendance_date', today())
            ->first();

        $sessions = $todayRecord?->sessions ?? collect();
        $openSession = $sessions->firstWhere('check_out_time', null);
        $lastSession = $sessions->last();
        $sessionCount = $sessions->count();

        $checkInTime = $sessions->first()?->check_in_time
            ? substr((string) $sessions->first()->check_in_time, 0, 5)
            : ($todayRecord?->check_in_time ? substr((string) $todayRecord->check_in_time, 0, 5) : null);

        $checkOutTime = $lastSession?->check_out_time
            ? substr((string) $lastSession->check_out_time, 0, 5)
            : ($todayRecord?->check_out_time ? substr((string) $todayRecord->check_out_time, 0, 5) : null);

        return Inertia::render('OfficeAttendance/Create', [
            'staff' => [
                'code' => $staff->code,
                'name' => $staff->name,
                'designation' => $staff->designation,
                'staffTypeLabel' => OfficeStaff::TYPES[$staff->staff_type] ?? $staff->staff_type,
                'status' => $staff->status,
            ],
            'workModes' => OfficeStaffAttendance::MODES,
            'today' => today()->toDateString(),
            'existingRecord' => $todayRecord ? [
                'workMode' => $todayRecord->work_mode,
                'workModeLabel' => OfficeStaffAttendance::MODES[$todayRecord->work_mode] ?? $todayRecord->work_mode,
                'checkInTime' => $checkInTime,
                'checkOutTime' => $checkOutTime,
                'hasOpenSession' => (bool) $openSession,
                'sessionCount' => $sessionCount,
                'latestCheckInTime' => $lastSession?->check_in_time ? substr((string) $lastSession->check_in_time, 0, 5) : null,
                'latestCheckOutTime' => $lastSession?->check_out_time ? substr((string) $lastSession->check_out_time, 0, 5) : null,
                'note' => $todayRecord->note,
                'submittedAt' => $todayRecord->updated_at?->format('d/m/Y h:i A'),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $staff = OfficeStaff::query()
            ->where('user_id', $request->user()->id)
            ->where('status', OfficeStaff::STATUS_ACTIVE)
            ->firstOrFail();

        $data = $request->validate([
            'work_mode' => ['required', Rule::in(array_keys(OfficeStaffAttendance::MODES))],
            'attendance_action' => ['required', Rule::in(['save', 'check_in', 'check_out'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $existingRecord = OfficeStaffAttendance::query()
            ->with(['sessions' => fn ($query) => $query->orderBy('check_in_time')->orderBy('id')])
            ->where('office_staff_id', $staff->id)
            ->whereDate('attendance_date', today())
            ->first();

        $openSession = $existingRecord?->sessions?->firstWhere('check_out_time', null);

        if ($data['attendance_action'] === 'check_in' && $openSession) {
            throw ValidationException::withMessages([
                'attendance_action' => 'Please checkout before starting another check-in.',
            ]);
        }

        if ($data['attendance_action'] === 'check_out') {
            if (! $existingRecord) {
                throw ValidationException::withMessages([
                    'attendance_action' => 'Please check in before checkout.',
                ]);
            }

            if (! $openSession && ! ($existingRecord->check_in_time && ! $existingRecord->check_out_time)) {
                throw ValidationException::withMessages([
                    'attendance_action' => 'Please check in before checkout.',
                ]);
            }
        }

        $values = [
            'submitted_by' => $request->user()->id,
            'work_mode' => $data['work_mode'],
            'note' => $data['note'] ?? null,
        ];

        DB::transaction(function () use ($staff, $values, $data, $existingRecord, $openSession) {
            $attendance = OfficeStaffAttendance::updateOrCreate([
                'office_staff_id' => $staff->id,
                'attendance_date' => today()->toDateString(),
            ], $values);

            if ($data['attendance_action'] === 'check_in') {
                $checkInTime = now()->format('H:i:s');

                OfficeStaffAttendanceSession::create([
                    'office_staff_attendance_id' => $attendance->id,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => null,
                ]);

                if (! $attendance->check_in_time) {
                    $attendance->forceFill(['check_in_time' => $checkInTime])->save();
                }
            }

            if ($data['attendance_action'] === 'check_out') {
                $checkOutTime = now()->format('H:i:s');

                if ($openSession) {
                    $openSession->forceFill(['check_out_time' => $checkOutTime])->save();
                } elseif ($existingRecord?->check_in_time && ! $existingRecord->check_out_time) {
                    OfficeStaffAttendanceSession::create([
                        'office_staff_attendance_id' => $attendance->id,
                        'check_in_time' => $existingRecord->check_in_time,
                        'check_out_time' => $checkOutTime,
                    ]);
                }

                $attendance->forceFill(['check_out_time' => $checkOutTime])->save();
            }
        });

        $message = match ($data['attendance_action']) {
            'check_in' => 'Check-in time saved.',
            'check_out' => 'Check-out time saved.',
            default => 'Office attendance submitted.',
        };

        return to_route('office-attendance.create')->with('success', $message);
    }
}
