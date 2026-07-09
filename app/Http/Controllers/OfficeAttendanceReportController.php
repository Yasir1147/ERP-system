<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class OfficeAttendanceReportController extends Controller
{
    private const OFFICE_RULE_DEFAULTS = [
        'office_start_time' => '09:00',
        'office_end_time' => '19:00',
        'break_start_time' => '13:00',
        'break_end_time' => '15:00',
        'break_included' => true,
        'late_grace_minutes' => 30,
        'overtime_enabled' => true,
    ];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('OfficeAttendance/Report', [
            'staff' => $this->staffOptions(),
            'workModes' => OfficeStaffAttendance::MODES,
            'filters' => $filters,
            'summaryRows' => $this->summaryRows($filters),
            'officeRules' => $this->officeRules(),
        ]);
    }

    public function print(Request $request): View
    {
        $filters = $this->filters($request, false);
        $rules = $this->officeRules();
        $rows = $this->attendanceQuery($filters)
            ->orderBy('attendance_date')
            ->orderBy('office_staff.code')
            ->get()
            ->map(fn (OfficeStaffAttendance $attendance) => $this->attendanceRow($attendance, $rules));

        return view('office-attendance.report', [
            'filters' => $filters,
            'summaryRows' => $this->summaryRows($filters),
            'attendanceRows' => $rows,
            'workModes' => OfficeStaffAttendance::MODES,
            'staffLabel' => $filters['staffId']
                ? OfficeStaff::query()->find($filters['staffId'])?->name
                : 'All Staff',
            'fromLabel' => Carbon::parse($filters['from'])->format('d/m/Y'),
            'toLabel' => Carbon::parse($filters['to'])->format('d/m/Y'),
            'officeRules' => $rules,
            'reportTotals' => $this->reportTotals($rows),
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'office_start_time' => ['required', 'date_format:H:i'],
            'office_end_time' => ['required', 'date_format:H:i'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i'],
            'break_included' => ['nullable', 'boolean'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'overtime_enabled' => ['nullable', 'boolean'],
        ]);

        if ($this->minutesFromTime($data['office_start_time']) >= $this->minutesFromTime($data['office_end_time'])) {
            throw ValidationException::withMessages([
                'office_end_time' => 'Office end time must be after start time.',
            ]);
        }

        if (($data['break_start_time'] ?? null) && ($data['break_end_time'] ?? null)
            && $this->minutesFromTime($data['break_start_time']) >= $this->minutesFromTime($data['break_end_time'])) {
            throw ValidationException::withMessages([
                'break_end_time' => 'Break end time must be after break start time.',
            ]);
        }

        AppSetting::setValue('office_attendance.office_start_time', $data['office_start_time']);
        AppSetting::setValue('office_attendance.office_end_time', $data['office_end_time']);
        AppSetting::setValue('office_attendance.break_start_time', $data['break_start_time'] ?? '');
        AppSetting::setValue('office_attendance.break_end_time', $data['break_end_time'] ?? '');
        AppSetting::setValue('office_attendance.break_included', $request->boolean('break_included') ? '1' : '0');
        AppSetting::setValue('office_attendance.late_grace_minutes', (string) $data['late_grace_minutes']);
        AppSetting::setValue('office_attendance.overtime_enabled', $request->boolean('overtime_enabled') ? '1' : '0');

        return back()->with('success', 'Office attendance rules saved.');
    }

    public function update(Request $request, OfficeStaffAttendance $officeAttendance): RedirectResponse
    {
        $data = $request->validate([
            'work_mode' => ['required', Rule::in(array_keys(OfficeStaffAttendance::MODES))],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $officeAttendance->update([
            'work_mode' => $data['work_mode'],
            'check_in_time' => $data['check_in_time'] ? $data['check_in_time'].':00' : null,
            'check_out_time' => $data['check_out_time'] ? $data['check_out_time'].':00' : null,
            'note' => $data['note'] ?? null,
        ]);

        $sessions = $officeAttendance->sessions()->orderBy('check_in_time')->orderBy('id')->get();

        if ($data['check_in_time'] && $sessions->isEmpty()) {
            $officeAttendance->sessions()->create([
                'check_in_time' => $data['check_in_time'].':00',
                'check_out_time' => $data['check_out_time'] ? $data['check_out_time'].':00' : null,
            ]);
        } elseif ($sessions->isNotEmpty()) {
            $sessions->first()->forceFill([
                'check_in_time' => $data['check_in_time'] ? $data['check_in_time'].':00' : null,
            ])->save();

            $sessions->last()->forceFill([
                'check_out_time' => $data['check_out_time'] ? $data['check_out_time'].':00' : null,
            ])->save();
        }

        return back()->with('success', 'Office attendance updated.');
    }

    public function details(Request $request, OfficeStaff $officeStaff): JsonResponse
    {
        $filters = $this->filters($request, false);
        $filters['staffId'] = (string) $officeStaff->id;
        $rules = $this->officeRules();

        $rows = $this->attendanceQuery($filters)
            ->orderByDesc('attendance_date')
            ->orderByDesc('office_staff_attendances.id')
            ->get()
            ->map(fn (OfficeStaffAttendance $attendance) => $this->attendanceRow($attendance, $rules));

        return response()->json([
            'staff' => [
                'id' => $officeStaff->id,
                'code' => $officeStaff->code,
                'name' => $officeStaff->name,
                'designation' => $officeStaff->designation,
                'staffTypeLabel' => OfficeStaff::TYPES[$officeStaff->staff_type] ?? $officeStaff->staff_type,
            ],
            'rows' => $rows,
        ]);
    }

    private function filters(Request $request, bool $paginate = true): array
    {
        $perPage = (int) $request->query('per_page', 15);

        return [
            'from' => $request->query('from', Carbon::today()->startOfMonth()->toDateString()),
            'to' => $request->query('to', Carbon::today()->toDateString()),
            'staffId' => (string) $request->query('staff_id', ''),
            'workMode' => (string) $request->query('work_mode', ''),
            'search' => trim((string) $request->query('search', '')),
            'perPage' => $paginate && in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15,
        ];
    }

    private function attendanceQuery(array $filters)
    {
        return OfficeStaffAttendance::query()
            ->select('office_staff_attendances.*')
            ->join('office_staff', 'office_staff.id', '=', 'office_staff_attendances.office_staff_id')
            ->with([
                'officeStaff:id,code,name,designation,staff_type',
                'submitter:id,name',
                'sessions' => fn ($query) => $query->orderBy('check_in_time')->orderBy('id'),
            ])
            ->when($filters['from'], fn ($query) => $query->whereDate('attendance_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($query) => $query->whereDate('attendance_date', '<=', $filters['to']))
            ->when($filters['staffId'] !== '', fn ($query) => $query->where('office_staff_attendances.office_staff_id', $filters['staffId']))
            ->when($filters['workMode'] !== '' && array_key_exists($filters['workMode'], OfficeStaffAttendance::MODES), fn ($query) => $query->where('office_staff_attendances.work_mode', $filters['workMode']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('office_staff.code', 'like', '%'.$search.'%')
                        ->orWhere('office_staff.name', 'like', '%'.$search.'%')
                        ->orWhere('office_staff.designation', 'like', '%'.$search.'%')
                        ->orWhere('office_staff_attendances.note', 'like', '%'.$search.'%');
                });
            });
    }

    private function summaryRows(array $filters)
    {
        return OfficeStaff::query()
            ->withCount([
                'attendances as remote_days' => fn ($query) => $this->summaryFilter($query, $filters)->where('office_staff_attendances.work_mode', OfficeStaffAttendance::MODE_REMOTE),
                'attendances as office_days' => fn ($query) => $this->summaryFilter($query, $filters)->where('office_staff_attendances.work_mode', OfficeStaffAttendance::MODE_OFFICE),
                'attendances as total_days' => fn ($query) => $this->summaryFilter($query, $filters),
            ])
            ->when($filters['staffId'] !== '', fn ($query) => $query->where('office_staff.id', $filters['staffId']))
            ->orderBy('code')
            ->get()
            ->map(fn (OfficeStaff $staff) => [
                'id' => $staff->id,
                'code' => $staff->code,
                'name' => $staff->name,
                'designation' => $staff->designation,
                'staffTypeLabel' => OfficeStaff::TYPES[$staff->staff_type] ?? $staff->staff_type,
                'remoteDays' => (int) $staff->remote_days,
                'officeDays' => (int) $staff->office_days,
                'totalDays' => (int) $staff->total_days,
            ]);
    }

    private function summaryFilter($query, array $filters)
    {
        return $query
            ->when($filters['from'], fn ($query) => $query->whereDate('attendance_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($query) => $query->whereDate('attendance_date', '<=', $filters['to']))
            ->when($filters['workMode'] !== '' && array_key_exists($filters['workMode'], OfficeStaffAttendance::MODES), fn ($query) => $query->where('office_staff_attendances.work_mode', $filters['workMode']));
    }

    private function staffOptions()
    {
        return OfficeStaff::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'designation'])
            ->map(fn (OfficeStaff $staff) => [
                'id' => $staff->id,
                'label' => $staff->code.' - '.$staff->name,
                'designation' => $staff->designation,
            ]);
    }

    private function attendanceRow(OfficeStaffAttendance $attendance, ?array $rules = null): array
    {
        $sessions = $attendance->sessions;
        $rules ??= $this->officeRules();
        $firstSession = $sessions->first();
        $lastSession = $sessions->last();
        $checkInTime = $firstSession?->check_in_time
            ? substr((string) $firstSession->check_in_time, 0, 5)
            : ($attendance->check_in_time ? substr((string) $attendance->check_in_time, 0, 5) : null);
        $checkOutTime = $lastSession?->check_out_time
            ? substr((string) $lastSession->check_out_time, 0, 5)
            : ($attendance->check_out_time ? substr((string) $attendance->check_out_time, 0, 5) : null);
        $sessionSummary = $sessions
            ->map(fn ($session) => (substr((string) $session->check_in_time, 0, 5) ?: '-').' - '.($session->check_out_time ? substr((string) $session->check_out_time, 0, 5) : 'Open'))
            ->implode(', ');
        $sessionDisplaySegments = $sessions
            ->map(fn ($session) => $this->formatDisplayTime($session->check_in_time).' - '.($session->check_out_time ? $this->formatDisplayTime($session->check_out_time) : 'Open'))
            ->values();
        $metrics = $this->attendanceMetrics($attendance, $rules, $sessions, $checkInTime, $checkOutTime);

        return [
            'id' => $attendance->id,
            'date' => $attendance->attendance_date?->toDateString(),
            'dateLabel' => $attendance->attendance_date?->format('d/m/Y'),
            'staffCode' => $attendance->officeStaff?->code,
            'staffName' => $attendance->officeStaff?->name,
            'designation' => $attendance->officeStaff?->designation,
            'staffTypeLabel' => OfficeStaff::TYPES[$attendance->officeStaff?->staff_type] ?? $attendance->officeStaff?->staff_type,
            'workMode' => $attendance->work_mode,
            'workModeLabel' => OfficeStaffAttendance::MODES[$attendance->work_mode] ?? $attendance->work_mode,
            'checkInTime' => $checkInTime,
            'checkOutTime' => $checkOutTime,
            'checkInDisplay' => $this->formatDisplayTime($checkInTime),
            'checkOutDisplay' => $checkOutTime ? $this->formatDisplayTime($checkOutTime) : null,
            'sessionCount' => $sessions->count(),
            'sessionSummary' => $sessionSummary,
            'sessionDisplaySegments' => $sessionDisplaySegments,
            ...$metrics,
            'note' => $attendance->note,
            'submittedBy' => $attendance->submitter?->name,
        ];
    }

    private function officeRules(): array
    {
        $rules = [
            'office_start_time' => AppSetting::getValue('office_attendance.office_start_time', self::OFFICE_RULE_DEFAULTS['office_start_time']),
            'office_end_time' => AppSetting::getValue('office_attendance.office_end_time', self::OFFICE_RULE_DEFAULTS['office_end_time']),
            'break_start_time' => AppSetting::getValue('office_attendance.break_start_time', self::OFFICE_RULE_DEFAULTS['break_start_time']),
            'break_end_time' => AppSetting::getValue('office_attendance.break_end_time', self::OFFICE_RULE_DEFAULTS['break_end_time']),
            'break_included' => AppSetting::getValue('office_attendance.break_included', self::OFFICE_RULE_DEFAULTS['break_included'] ? '1' : '0') === '1',
            'late_grace_minutes' => (int) AppSetting::getValue('office_attendance.late_grace_minutes', (string) self::OFFICE_RULE_DEFAULTS['late_grace_minutes']),
            'overtime_enabled' => AppSetting::getValue('office_attendance.overtime_enabled', self::OFFICE_RULE_DEFAULTS['overtime_enabled'] ? '1' : '0') === '1',
        ];

        $officeMinutes = max(0, $this->minutesFromTime($rules['office_end_time']) - $this->minutesFromTime($rules['office_start_time']));
        $breakMinutes = ($rules['break_start_time'] && $rules['break_end_time'])
            ? max(0, $this->minutesFromTime($rules['break_end_time']) - $this->minutesFromTime($rules['break_start_time']))
            : 0;
        $scheduledMinutes = $rules['break_included'] ? $officeMinutes : max(0, $officeMinutes - $breakMinutes);

        return [
            ...$rules,
            'scheduled_minutes' => $scheduledMinutes,
            'scheduled_label' => $this->formatMinutesLabel($scheduledMinutes),
            'late_after_time' => $this->minutesToTime($this->minutesFromTime($rules['office_start_time']) + $rules['late_grace_minutes']),
        ];
    }

    private function attendanceMetrics(OfficeStaffAttendance $attendance, array $rules, $sessions, ?string $checkInTime, ?string $checkOutTime): array
    {
        if ($attendance->work_mode !== OfficeStaffAttendance::MODE_OFFICE) {
            return [
                'workMinutes' => 0,
                'workHoursLabel' => '-',
                'overtimeMinutes' => 0,
                'overtimeLabel' => '-',
                'lateMinutes' => 0,
                'lateLabel' => '-',
                'isLate' => false,
            ];
        }

        $workMinutes = $this->workedMinutes($attendance, $rules, $sessions);
        $firstCheckIn = $this->minutesFromTime($checkInTime);
        $lastCheckOut = $this->minutesFromTime($checkOutTime);
        $officeStart = $this->minutesFromTime($rules['office_start_time']);
        $officeEnd = $this->minutesFromTime($rules['office_end_time']);
        $lateCutoff = $officeStart + (int) $rules['late_grace_minutes'];
        $lateMinutes = $checkInTime && $firstCheckIn > $lateCutoff ? $firstCheckIn - $officeStart : 0;
        $overtimeMinutes = 0;

        if ($rules['overtime_enabled'] && $checkOutTime) {
            if ($checkInTime && $lastCheckOut < $firstCheckIn) {
                $lastCheckOut += 1440;
            }

            $overtimeMinutes = max(0, $lastCheckOut - $officeEnd);
        }

        return [
            'workMinutes' => $workMinutes,
            'workHoursLabel' => $workMinutes > 0 ? $this->formatMinutesLabel($workMinutes) : '-',
            'overtimeMinutes' => $overtimeMinutes,
            'overtimeLabel' => $overtimeMinutes > 0 ? $this->formatMinutesLabel($overtimeMinutes) : '-',
            'lateMinutes' => $lateMinutes,
            'lateLabel' => $lateMinutes > 0 ? $this->formatMinutesLabel($lateMinutes) : 'On time',
            'isLate' => $lateMinutes > 0,
        ];
    }

    private function workedMinutes(OfficeStaffAttendance $attendance, array $rules, $sessions): int
    {
        $ranges = $sessions->isNotEmpty()
            ? $sessions->map(fn ($session) => [$session->check_in_time, $session->check_out_time])
            : collect([[$attendance->check_in_time, $attendance->check_out_time]]);

        $today = Carbon::today()->toDateString();
        $isToday = $attendance->attendance_date?->toDateString() === $today;
        $breakStart = $this->minutesFromTime($rules['break_start_time']);
        $breakEnd = $this->minutesFromTime($rules['break_end_time']);

        return (int) $ranges->sum(function (array $range) use ($rules, $isToday, $breakStart, $breakEnd) {
            [$rawStart, $rawEnd] = $range;

            if (! $rawStart) {
                return 0;
            }

            $start = $this->minutesFromTime($rawStart);
            $end = $rawEnd ? $this->minutesFromTime($rawEnd) : ($isToday ? (Carbon::now()->hour * 60) + Carbon::now()->minute : $start);

            if ($end < $start) {
                $end += 1440;
            }

            $minutes = max(0, $end - $start);

            if (! $rules['break_included'] && $breakStart !== null && $breakEnd !== null) {
                $minutes -= $this->overlapMinutes($start, $end, $breakStart, $breakEnd);
            }

            return max(0, $minutes);
        });
    }

    private function reportTotals($rows): array
    {
        $workMinutes = (int) $rows->sum('workMinutes');
        $overtimeMinutes = (int) $rows->sum('overtimeMinutes');
        $lateMinutes = (int) $rows->sum('lateMinutes');

        return [
            'workMinutes' => $workMinutes,
            'workLabel' => $this->formatMinutesLabel($workMinutes),
            'overtimeMinutes' => $overtimeMinutes,
            'overtimeLabel' => $this->formatMinutesLabel($overtimeMinutes),
            'lateCount' => $rows->where('isLate', true)->count(),
            'lateMinutes' => $lateMinutes,
            'lateLabel' => $this->formatMinutesLabel($lateMinutes),
        ];
    }

    private function overlapMinutes(int $start, int $end, int $blockStart, int $blockEnd): int
    {
        return max(0, min($end, $blockEnd) - max($start, $blockStart));
    }

    private function minutesFromTime(?string $time): ?int
    {
        if (! $time) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hour * 60) + $minute;
    }

    private function minutesToTime(int $minutes): string
    {
        $minutes %= 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatMinutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return trim(($hours ? $hours.'h' : '').($remainingMinutes ? ' '.$remainingMinutes.'m' : ''));
    }

    private function formatDisplayTime($time): ?string
    {
        if (! $time) {
            return null;
        }

        return Carbon::createFromFormat('H:i', substr((string) $time, 0, 5))->format('g:i A');
    }
}
