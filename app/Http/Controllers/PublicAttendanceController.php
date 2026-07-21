<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicAttendanceController extends Controller
{
    public function create(string $type = 'contracting'): Response
    {
        $type = $this->normalizeType($type);
        $dateRange = request()->user()->attendanceDateRange();
        $leaveLookupMin = $dateRange['min'] ?? $dateRange['max'];
        $today = now()->toDateString();
        $historyEnabled = $type === 'rope_access';
        $historyDate = $today;

        if ($historyEnabled) {
            $historyDate = request()->validate([
                'history_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            ])['history_date'] ?? $today;
        }

        $todayRecords = $this->submittedRecordsForDate($type, request()->user()->id, $today);
        $historyRecords = $historyDate === $today
            ? $todayRecords
            : $this->submittedRecordsForDate($type, request()->user()->id, $historyDate);

        return Inertia::render('Public/MarkAttendance', [
            'projects' => Project::query()
                ->where('type', $type)
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'type']),
            'employees' => Employee::query()
                ->where('type', $type)
                ->where('status', '!=', Employee::STATUS_LEFT)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'profession', 'type', 'status']),
            'employeeLeaves' => EmployeeLeave::query()
                ->whereHas('employee', fn ($query) => $query->where('type', $type))
                ->whereDate('start_date', '<=', $dateRange['max'])
                ->whereDate('end_date', '>=', $leaveLookupMin)
                ->get(['employee_id', 'start_date', 'end_date', 'reason'])
                ->map(fn (EmployeeLeave $leave) => [
                    'employeeId' => $leave->employee_id,
                    'startDate' => $leave->start_date->toDateString(),
                    'endDate' => $leave->end_date->toDateString(),
                    'reason' => $leave->reason,
                ]),
            'employeeType' => $type,
            'employeeTypeLabel' => Employee::TYPES[$type],
            'submitUrl' => $this->submitUrl($type),
            'dutyPlanUrl' => $type === 'contracting' ? '/contracting-duty-plans' : null,
            'expenseCreateUrl' => $type === 'rope_access' ? '/expenses/create?type=rope_access' : null,
            'attendanceDateMin' => $dateRange['min'],
            'attendanceDateMax' => $dateRange['max'],
            'attendanceDateHelp' => $dateRange['message'],
            'todayRecords' => $todayRecords,
            'historyEnabled' => $historyEnabled,
            'historyDate' => $historyDate,
            'historyRecords' => $historyEnabled ? $historyRecords : [],
        ]);
    }

    public function store(Request $request, string $type = 'contracting'): RedirectResponse
    {
        $type = $this->normalizeType($type);
        $isPresent = $request->input('status') === AttendanceRecord::STATUS_PRESENT;
        $isLeave = $request->input('status') === AttendanceRecord::STATUS_LEAVE;
        $dateRange = $request->user()->attendanceDateRange();

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('type', $type)
                    ->where('status', '!=', Employee::STATUS_LEFT)),
            ],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'project_id' => [
                'nullable',
                Rule::requiredIf($isPresent),
                'integer',
                Rule::exists('projects', 'id')->where('type', $type),
            ],
            'attendance_date' => [
                'required',
                'date',
                ...$this->attendanceDateRules($dateRange, $isLeave),
            ],
            'attendance_end_date' => [
                'nullable',
                Rule::requiredIf($isLeave),
                'date',
                'after_or_equal:attendance_date',
                ...$this->attendanceDateRules($dateRange, $isLeave),
            ],
            'has_overtime' => ['required', 'boolean'],
            'overtime_hours' => [
                'nullable',
                Rule::requiredIf($isPresent && $request->boolean('has_overtime')),
                'integer',
                'between:1,10',
            ],
            'overtime_project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('type', $type),
            ],
            'leave_reason' => ['nullable', Rule::requiredIf($isLeave), 'string', 'max:1000'],
        ]);

        $employeeIds = collect($data['employee_ids'])->map(fn ($employeeId) => (int) $employeeId)->unique()->values();

        $leaveStartDate = $data['attendance_date'];
        $leaveEndDate = $isLeave ? ($data['attendance_end_date'] ?? $data['attendance_date']) : $data['attendance_date'];

        $alreadyMarked = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$leaveStartDate, $leaveEndDate])
            ->pluck('employee_id');

        if ($alreadyMarked->isNotEmpty()) {
            $names = Employee::query()
                ->whereIn('id', $alreadyMarked)
                ->orderByRaw('CAST(code AS UNSIGNED) asc')
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn (Employee $employee) => trim($employee->code.' - '.$employee->name))
                ->implode(', ');

            throw ValidationException::withMessages([
                'employee_ids' => 'Attendance is already marked in the selected date range for: '.$names.'.',
            ]);
        }

        $employeeUnavailableIds = Employee::query()
            ->whereIn('id', $employeeIds)
            ->where('status', Employee::STATUS_ON_LEAVE)
            ->pluck('id');

        $leaveRangeEmployeeIds = EmployeeLeave::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('start_date', '<=', $leaveEndDate)
            ->whereDate('end_date', '>=', $leaveStartDate)
            ->pluck('employee_id');

        $unavailableIds = $employeeUnavailableIds->merge($leaveRangeEmployeeIds)->unique()->values();

        if ($unavailableIds->isNotEmpty()) {
            $names = Employee::query()
                ->whereIn('id', $unavailableIds)
                ->orderByRaw('CAST(code AS UNSIGNED) asc')
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn (Employee $employee) => trim($employee->code.' - '.$employee->name))
                ->implode(', ');

            throw ValidationException::withMessages([
                'employee_ids' => 'These employees are on leave for the selected date range: '.$names.'.',
            ]);
        }

        if (! $isPresent) {
            $data['project_id'] = null;
            $data['overtime_project_id'] = null;
            $data['has_overtime'] = false;
            $data['overtime_hours'] = null;
        }

        if (! $isLeave) {
            $data['leave_reason'] = null;
        }

        if (! $data['has_overtime']) {
            $data['overtime_hours'] = null;
            $data['overtime_project_id'] = null;
        } elseif (blank($data['overtime_project_id'] ?? null)) {
            $data['overtime_project_id'] = $data['project_id'];
        }

        $data['submitted_by'] = $request->user()->id;
        $data['overtime_time'] = null;

        unset($data['employee_ids']);

        DB::transaction(function () use ($data, $employeeIds, $isLeave, $leaveStartDate, $leaveEndDate, $request) {
            foreach ($employeeIds as $employeeId) {
                if ($isLeave) {
                    EmployeeLeave::create([
                        'employee_id' => $employeeId,
                        'created_by' => $request->user()->id,
                        'start_date' => $leaveStartDate,
                        'end_date' => $leaveEndDate,
                        'reason' => $data['leave_reason'],
                    ]);

                    continue;
                }

                unset($data['attendance_end_date']);

                AttendanceRecord::create([
                    ...$data,
                    'employee_id' => $employeeId,
                ]);
            }
        });

        return redirect($this->submitUrl($type));
    }

    private function normalizeType(string $type): string
    {
        $type = str_replace('-', '_', $type);

        abort_unless(array_key_exists($type, Employee::TYPES), 404);

        return $type;
    }

    private function submitUrl(string $type): string
    {
        return $type === 'rope_access'
            ? '/mark-attendance/rope-access'
            : '/mark-attendance/contracting';
    }

    private function attendanceDateRules(array $dateRange, bool $isLeave): array
    {
        $rules = $isLeave ? [] : ['before_or_equal:'.$dateRange['max']];

        if ($dateRange['min']) {
            $rules[] = 'after_or_equal:'.$dateRange['min'];
        }

        return $rules;
    }

    private function submittedRecordsForDate(string $type, int $userId, string $date): array
    {
        $attendanceRecords = AttendanceRecord::query()
            ->leftJoin('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->leftJoin('projects', 'attendance_records.project_id', '=', 'projects.id')
            ->leftJoin('projects as overtime_projects', 'attendance_records.overtime_project_id', '=', 'overtime_projects.id')
            ->where('attendance_records.submitted_by', $userId)
            ->where('employees.type', $type)
            ->whereDate('attendance_records.attendance_date', $date)
            ->orderByDesc('attendance_records.id')
            ->get([
                'attendance_records.id',
                'attendance_records.status',
                'attendance_records.attendance_date',
                'attendance_records.leave_reason',
                'attendance_records.overtime_hours',
                'attendance_records.created_at as submitted_at',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.profession as employee_profession',
                'projects.name as project_name',
                'overtime_projects.name as overtime_project_name',
            ])
            ->map(fn ($record) => [
                'id' => 'attendance-'.$record->id,
                'employeeCode' => $record->employee_code,
                'employeeName' => $record->employee_name,
                'employeeProfession' => $record->employee_profession,
                'status' => $record->status,
                'projectName' => $record->project_name,
                'overtimeProjectName' => $record->overtime_project_name ?: $record->project_name,
                'reason' => $record->leave_reason,
                'overtimeHours' => $record->overtime_hours,
                'submittedAt' => $record->submitted_at,
                'submittedTime' => Carbon::parse($record->submitted_at)->format('h:i A'),
            ]);

        $leaveRecords = EmployeeLeave::query()
            ->join('employees', 'employee_leaves.employee_id', '=', 'employees.id')
            ->where('employee_leaves.created_by', $userId)
            ->where('employees.type', $type)
            ->whereDate('employee_leaves.start_date', '<=', $date)
            ->whereDate('employee_leaves.end_date', '>=', $date)
            ->orderByDesc('employee_leaves.id')
            ->get([
                'employee_leaves.id',
                'employee_leaves.reason',
                'employee_leaves.created_at as submitted_at',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.profession as employee_profession',
            ])
            ->map(fn ($leave) => [
                'id' => 'leave-'.$leave->id,
                'employeeCode' => $leave->employee_code,
                'employeeName' => $leave->employee_name,
                'employeeProfession' => $leave->employee_profession,
                'status' => AttendanceRecord::STATUS_LEAVE,
                'projectName' => null,
                'overtimeProjectName' => null,
                'reason' => $leave->reason,
                'overtimeHours' => null,
                'submittedAt' => $leave->submitted_at,
                'submittedTime' => Carbon::parse($leave->submitted_at)->format('h:i A'),
            ]);

        return $attendanceRecords
            ->concat($leaveRecords)
            ->sortByDesc('submittedAt')
            ->values()
            ->all();
    }
}
