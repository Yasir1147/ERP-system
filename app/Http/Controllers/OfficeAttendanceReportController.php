<?php

namespace App\Http\Controllers;

use App\Models\OfficeStaff;
use App\Models\OfficeStaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class OfficeAttendanceReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('OfficeAttendance/Report', [
            'staff' => $this->staffOptions(),
            'workModes' => OfficeStaffAttendance::MODES,
            'filters' => $filters,
            'summaryRows' => $this->summaryRows($filters),
        ]);
    }

    public function print(Request $request): View
    {
        $filters = $this->filters($request, false);
        $rows = $this->attendanceQuery($filters)
            ->orderBy('attendance_date')
            ->orderBy('office_staff.code')
            ->get()
            ->map(fn (OfficeStaffAttendance $attendance) => $this->attendanceRow($attendance));

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
        ]);
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

        $rows = $this->attendanceQuery($filters)
            ->orderByDesc('attendance_date')
            ->orderByDesc('office_staff_attendances.id')
            ->get()
            ->map(fn (OfficeStaffAttendance $attendance) => $this->attendanceRow($attendance));

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

    private function attendanceRow(OfficeStaffAttendance $attendance): array
    {
        $sessions = $attendance->sessions;
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
            'sessionCount' => $sessions->count(),
            'sessionSummary' => $sessionSummary,
            'note' => $attendance->note,
            'submittedBy' => $attendance->submitter?->name,
        ];
    }
}
