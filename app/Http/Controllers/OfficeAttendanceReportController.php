<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\OfficeLeaveRequest;
use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use App\Services\Excel\OfficeAttendanceExporter;
use App\Services\Excel\OfficeTimesheetExporter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'leaveRows' => $this->leaveRows($filters),
            'officeRules' => $this->officeRules(),
        ]);
    }

    public function print(Request $request): View
    {
        return view('office-attendance.report', $this->reportData($request));
    }

    public function timesheet(Request $request): Response
    {
        return Inertia::render('OfficeAttendance/Timesheet', [
            ...$this->timesheetData($request),
            'staff' => $this->staffOptions(),
        ]);
    }

    public function timesheetPrint(Request $request): View
    {
        return view('office-attendance.timesheet', $this->timesheetData($request));
    }

    public function timesheetExport(Request $request, OfficeTimesheetExporter $exporter)
    {
        return $exporter->download($this->timesheetData($request));
    }

    private function timesheetData(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'staff_id' => ['nullable', 'integer', 'exists:office_staff,id'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['required', 'integer', 'distinct', 'exists:office_staff,id'],
            'selection' => ['nullable', Rule::in(['all', 'selected'])],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now('Asia/Dubai')->format('Y-m'), 'Asia/Dubai');
        $staffIds = array_map('intval', $validated['staff_ids'] ?? (isset($validated['staff_id']) ? [$validated['staff_id']] : []));
        $allStaff = ($validated['selection'] ?? ($staffIds ? 'selected' : 'all')) === 'all';
        $filters = [
            'month' => $month->format('Y-m'),
            'staffId' => (string) ($validated['staff_id'] ?? ''),
            'staffIds' => $allStaff ? [] : $staffIds,
            'allStaff' => $allStaff,
            'search' => trim($validated['search'] ?? ''),
        ];
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $days = [];
        for ($day = $month->copy(); $day->toDateString() <= $to; $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'number' => $day->day, 'weekday' => $day->format('D')];
        }
        $inMonth = fn ($query) => $query->whereDate('attendance_date', '>=', $from)->whereDate('attendance_date', '<=', $to);
        $staff = OfficeStaff::query()
            ->when(! $allStaff, fn ($q) => $q->whereIn('id', $staffIds))
            ->when($filters['search'] !== '', fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('code', 'like', '%'.$filters['search'].'%')->orWhere('name', 'like', '%'.$filters['search'].'%')->orWhere('designation', 'like', '%'.$filters['search'].'%');
            }))
            ->with(['attendances' => fn ($q) => $inMonth($q)->with(['sessions' => fn ($sessions) => $sessions->orderBy('check_in_time')->orderBy('id'), 'officeStaff', 'submitter'])])
            ->orderBy('code')->get();
        $leaves = OfficeLeaveRequest::whereIn('office_staff_id', $staff->pluck('id'))
            ->whereBetween('leave_date', [$from, $to])->whereIn('status', ['approved', 'pending'])->get()
            ->groupBy('office_staff_id');
        $rules = $this->officeRules();
        $rows = $staff->map(function (OfficeStaff $person) use ($days, $leaves, $rules) {
            $attendance = $person->attendances->keyBy(fn ($record) => $record->attendance_date->toDateString());
            $leave = ($leaves->get($person->id) ?? collect())->keyBy(fn ($record) => $record->leave_date->toDateString());
            $cells = [];
            $present = $approved = $pending = $minutes = 0;
            foreach ($days as $day) {
                $record = $attendance->get($day['date']);
                $detail = $record ? $this->attendanceRow($record, $rules) : null;
                // Blank placeholder rows are not proof of attendance.
                $isPresent = $record && ($detail['checkInTime'] || $detail['checkOutTime']);
                $status = $isPresent ? 'P' : match ($leave->get($day['date'])?->status) {
                    'approved' => 'L', 'pending' => 'LP', default => '-',
                };
                $present += $status === 'P' ? 1 : 0;
                $approved += $status === 'L' ? 1 : 0;
                $pending += $status === 'LP' ? 1 : 0;
                $minutes += $isPresent ? $detail['workMinutes'] : 0;
                $cells[] = ['date' => $day['date'], 'status' => $status, 'detail' => $isPresent ? $detail : null];
            }

            return ['id' => $person->id, 'code' => $person->code, 'name' => $person->name,
                'designation' => $person->designation, 'cells' => $cells, 'present' => $present,
                'leave' => $approved, 'pending' => $pending, 'workMinutes' => $minutes,
                'workLabel' => $this->formatMinutesLabel($minutes)];
        });

        return ['filters' => $filters, 'monthLabel' => $month->format('F Y'), 'days' => $days, 'rows' => $rows,
            'dailyPresent' => array_map(fn ($index) => $rows->filter(fn ($row) => $row['cells'][$index]['status'] === 'P')->count(), array_keys($days)),
            'totals' => ['staff' => $rows->count(), 'present' => $rows->sum('present'), 'leave' => $rows->sum('leave'),
                'pending' => $rows->sum('pending'), 'workMinutes' => $rows->sum('workMinutes'), 'workLabel' => $this->formatMinutesLabel($rows->sum('workMinutes'))]];
    }

    public function export(Request $request, OfficeAttendanceExporter $exporter)
    {
        return $exporter->download($this->reportData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'office_staff_id' => ['required', 'integer', 'exists:office_staff,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'attendance_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:attendance_date'],
            'work_mode' => ['required', Rule::in(array_keys(OfficeStaffAttendance::MODES))],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i', 'after:check_in_time'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = Carbon::parse($data['attendance_date']);
        $to = Carbon::parse($data['attendance_date_to'] ?? $data['attendance_date']);
        if ($from->diffInDays($to) >= 366) {
            throw ValidationException::withMessages(['attendance_date_to' => 'Select up to 366 days at a time.']);
        }
        $count = DB::transaction(function () use ($data, $from, $to) {
            $staff = OfficeStaff::whereKey($data['office_staff_id'])->lockForUpdate()->firstOrFail();
            if (! $staff->user_id) {
                throw ValidationException::withMessages(['office_staff_id' => 'This staff member needs a linked user account before attendance can be saved.']);
            }
            $duplicate = $staff->attendances()->whereDate('attendance_date', '>=', $from->toDateString())->whereDate('attendance_date', '<=', $to->toDateString())->orderBy('attendance_date')->first();
            if ($duplicate) {
                throw ValidationException::withMessages(['attendance_date' => 'Attendance already exists on '.$duplicate->attendance_date->format('d/m/Y').'. No dates were saved. Edit that record or change the range.']);
            }
            $leave = OfficeLeaveRequest::where('office_staff_id', $staff->id)->whereDate('leave_date', '>=', $from->toDateString())->whereDate('leave_date', '<=', $to->toDateString())->whereIn('status', ['pending', 'approved'])->orderBy('leave_date')->first();
            if ($leave) {
                throw ValidationException::withMessages(['attendance_date' => 'Pending or approved leave exists on '.$leave->leave_date->format('d/m/Y').'. No dates were saved. Review the leave or change the range.']);
            }
            $count = 0;
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $attendance = $staff->attendances()->create([
                    'attendance_date' => $date->toDateString(),
                    'work_mode' => $data['work_mode'],
                    'note' => $data['note'] ?? null,
                    'submitted_by' => $staff->user_id,
                    'check_in_time' => $data['check_in_time'].':00',
                    'check_out_time' => $data['check_out_time'].':00',
                    'is_fixed' => $staff->attendance_mode === 'fixed',
                    'marked_at' => now(),
                ]);
                $attendance->sessions()->create([
                    'check_in_time' => $attendance->check_in_time,
                    'check_out_time' => $attendance->check_out_time,
                ]);
                $count++;
            }

            return $count;
        });

        Log::info('Office attendance entered by admin', [
            'admin_id' => $request->user()->id, 'office_staff_id' => $data['office_staff_id'],
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $count,
        ]);

        return back()->with('success', 'Office attendance saved for '.$count.' day(s).');
    }

    private function reportData(Request $request): array
    {
        $filters = $this->filters($request, false);
        $rules = $this->officeRules();
        $rows = $this->attendanceQuery($filters)
            ->orderBy('attendance_date')
            ->orderBy('office_staff.code')
            ->get()
            ->map(fn (OfficeStaffAttendance $attendance) => $this->attendanceRow($attendance, $rules));

        $selectedStaff = $filters['staffId'] !== '' ? OfficeStaff::findOrFail($filters['staffId']) : null;

        return [
            'filters' => $filters,
            'summaryRows' => $this->summaryRows($filters),
            'leaveRows' => $this->leaveRows($filters),
            'attendanceRows' => $rows,
            'workModes' => OfficeStaffAttendance::MODES,
            'selectedStaff' => $selectedStaff,
            'staffLabel' => $selectedStaff ? $selectedStaff->code.' - '.$selectedStaff->name : 'All Staff',
            'fromLabel' => Carbon::parse($filters['from'])->format('d/m/Y'),
            'toLabel' => Carbon::parse($filters['to'])->format('d/m/Y'),
            'officeRules' => $rules,
            'reportTotals' => $this->reportTotals($rows),
        ];
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
            'leaveRows' => $this->leaveRows($filters),
        ]);
    }

    private function leaveRows(array $filters)
    {
        return OfficeLeaveRequest::with('officeStaff:id,code,name,staff_type')
            ->whereIn('status', ['pending', 'approved'])
            ->when($filters['from'], fn ($q) => $q->whereDate('leave_date', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('leave_date', '<=', $filters['to']))
            ->when($filters['staffId'] !== '', fn ($q) => $q->where('office_staff_id', $filters['staffId']))
            ->when(in_array($filters['workMode'], ['office', 'remote'], true), fn ($q) => $q->whereHas('officeStaff', fn ($staff) => $staff->where('staff_type', $filters['workMode'] === 'office' ? 'on_site' : 'remote')))
            ->when($filters['search'] !== '', fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('reason', 'like', '%'.$filters['search'].'%')->orWhereHas('officeStaff', fn ($staff) => $staff->where('name', 'like', '%'.$filters['search'].'%')->orWhere('code', 'like', '%'.$filters['search'].'%'));
            }))
            ->orderByDesc('leave_date')->get()->map(fn ($leave) => [
                'id' => $leave->id, 'date' => $leave->leave_date->toDateString(),
                'staffName' => $leave->officeStaff->code.' - '.$leave->officeStaff->name,
                'label' => $leave->attendanceLabel(), 'reason' => $leave->reason,
            ]);
    }

    private function filters(Request $request, bool $paginate = true): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'staff_id' => ['nullable', 'integer', 'exists:office_staff,id'],
            'work_mode' => ['nullable', Rule::in(array_keys(OfficeStaffAttendance::MODES))],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
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
            ->get(['id', 'code', 'name', 'designation', 'staff_type', 'attendance_mode', 'fixed_start_time', 'fixed_end_time'])
            ->map(fn (OfficeStaff $staff) => [
                'id' => $staff->id,
                'label' => $staff->code.' - '.$staff->name,
                'designation' => $staff->designation,
                'workMode' => $staff->staff_type === 'remote' ? 'remote' : 'office',
                'attendanceMode' => $staff->attendance_mode,
                'fixedStartTime' => substr($staff->fixed_start_time, 0, 5),
                'fixedEndTime' => substr($staff->fixed_end_time, 0, 5),
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
        if ($attendance->is_fixed) {
            $minutes = $this->workedMinutes($attendance, [...$rules, 'break_included' => true], $sessions);

            return ['workMinutes' => $minutes, 'workHoursLabel' => $this->formatMinutesLabel($minutes),
                'overtimeMinutes' => 0, 'overtimeLabel' => '-', 'lateMinutes' => 0, 'lateLabel' => 'Fixed Attendance', 'isLate' => false];
        }
        if ($attendance->work_mode !== OfficeStaffAttendance::MODE_OFFICE) {
            $minutes = $this->workedMinutes($attendance, [...$rules, 'break_included' => true], $sessions);

            return [
                'workMinutes' => $minutes,
                'workHoursLabel' => $minutes > 0 ? $this->formatMinutesLabel($minutes) : '-',
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
