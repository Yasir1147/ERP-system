<?php

namespace App\Http\Controllers;

use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use App\Models\OfficeStaffAttendanceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfficeAttendanceController extends Controller
{
    public function index(): Response
    {
        $todayRecords = OfficeStaffAttendance::query()
            ->with(['sessions' => fn ($query) => $query->orderBy('check_in_time')->orderBy('id')])
            ->whereDate('attendance_date', today())
            ->get()
            ->keyBy('office_staff_id');

        return Inertia::render('OfficeAttendance/StaffList', [
            'staffMembers' => OfficeStaff::query()
                ->with('user:id,username')
                ->where('status', OfficeStaff::STATUS_ACTIVE)
                ->orderByRaw('cast(code as unsigned), code')
                ->get()
                ->map(fn (OfficeStaff $staff) => $this->staffListPayload($staff, $todayRecords->get($staff->id))),
            'today' => today()->toDateString(),
        ]);
    }

    public function create(Request $request, ?OfficeStaff $officeStaff = null): Response
    {
        $staff = $this->resolveStaff($request, $officeStaff);

        $todayRecord = OfficeStaffAttendance::query()
            ->with(['sessions' => fn ($query) => $query->orderBy('check_in_time')->orderBy('id')])
            ->where('office_staff_id', $staff->id)
            ->whereDate('attendance_date', today())
            ->first();

        return Inertia::render('OfficeAttendance/Create', [
            'staff' => [
                'id' => $staff->id,
                'code' => $staff->code,
                'name' => $staff->name,
                'designation' => $staff->designation,
                'staffTypeLabel' => OfficeStaff::TYPES[$staff->staff_type] ?? $staff->staff_type,
                'status' => $staff->status,
                'photoUrl' => $this->photoUrl($staff),
            ],
            'workModes' => OfficeStaffAttendance::MODES,
            'today' => today()->toDateString(),
            'existingRecord' => $this->todayRecordPayload($todayRecord),
            'submitUrl' => $officeStaff ? route('office-attendance.staff.store', $staff, false) : route('office-attendance.store', absolute: false),
            'backUrl' => $officeStaff ? route('office-attendance.staff.index', absolute: false) : null,
        ]);
    }

    public function store(Request $request, ?OfficeStaff $officeStaff = null): RedirectResponse
    {
        $staff = $this->resolveStaff($request, $officeStaff);

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

        $submitterId = $officeStaff ? $staff->user_id : $request->user()?->id;

        if (! $submitterId) {
            throw ValidationException::withMessages([
                'staff' => 'This staff profile has no login user assigned.',
            ]);
        }

        $values = [
            'submitted_by' => $submitterId,
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

        return $officeStaff
            ? to_route('office-attendance.staff.index')->with('success', $message)
            : to_route('office-attendance.create')->with('success', $message);
    }

    private function resolveStaff(Request $request, ?OfficeStaff $officeStaff = null): OfficeStaff
    {
        if ($officeStaff) {
            abort_unless($officeStaff->status === OfficeStaff::STATUS_ACTIVE, 404);

            return $officeStaff;
        }

        $user = $request->user();
        abort_unless($user, 403);

        return OfficeStaff::query()
            ->where('user_id', $user->id)
            ->where('status', OfficeStaff::STATUS_ACTIVE)
            ->firstOrFail();
    }

    private function todayRecordPayload(?OfficeStaffAttendance $todayRecord): ?array
    {
        if (! $todayRecord) {
            return null;
        }

        $sessions = $todayRecord->sessions ?? collect();
        $openSession = $sessions->firstWhere('check_out_time', null);
        $lastSession = $sessions->last();

        $checkInTime = $sessions->first()?->check_in_time
            ? substr((string) $sessions->first()->check_in_time, 0, 5)
            : ($todayRecord->check_in_time ? substr((string) $todayRecord->check_in_time, 0, 5) : null);

        $checkOutTime = $lastSession?->check_out_time
            ? substr((string) $lastSession->check_out_time, 0, 5)
            : ($todayRecord->check_out_time ? substr((string) $todayRecord->check_out_time, 0, 5) : null);

        return [
            'workMode' => $todayRecord->work_mode,
            'workModeLabel' => OfficeStaffAttendance::MODES[$todayRecord->work_mode] ?? $todayRecord->work_mode,
            'checkInTime' => $checkInTime,
            'checkOutTime' => $checkOutTime,
            'hasOpenSession' => (bool) $openSession,
            'sessionCount' => $sessions->count(),
            'latestCheckInTime' => $lastSession?->check_in_time ? substr((string) $lastSession->check_in_time, 0, 5) : null,
            'latestCheckOutTime' => $lastSession?->check_out_time ? substr((string) $lastSession->check_out_time, 0, 5) : null,
            'note' => $todayRecord->note,
            'submittedAt' => $todayRecord->updated_at?->format('d/m/Y h:i A'),
        ];
    }

    private function staffListPayload(OfficeStaff $staff, ?OfficeStaffAttendance $todayRecord): array
    {
        $record = $this->todayRecordPayload($todayRecord);
        $status = ! $record ? 'not_marked' : ($record['hasOpenSession'] ? 'checked_in' : 'checked_out');

        return [
            'id' => $staff->id,
            'code' => $staff->code,
            'name' => $staff->name,
            'designation' => $staff->designation,
            'staffTypeLabel' => OfficeStaff::TYPES[$staff->staff_type] ?? $staff->staff_type,
            'photoUrl' => $this->photoUrl($staff),
            'markUrl' => route('office-attendance.staff.create', $staff, false),
            'status' => $status,
            'statusLabel' => match ($status) {
                'checked_in' => 'Checked In',
                'checked_out' => 'Checked Out',
                default => 'Not Marked',
            },
            'todayRecord' => $record,
        ];
    }

    private function photoUrl(OfficeStaff $staff): ?string
    {
        return $staff->photo_path ? Storage::disk('public')->url($staff->photo_path) : null;
    }
}
