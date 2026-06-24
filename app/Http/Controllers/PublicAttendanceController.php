<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'attendanceDateMin' => $dateRange['min'],
            'attendanceDateMax' => $dateRange['max'],
            'attendanceDateHelp' => $dateRange['message'],
        ]);
    }

    public function store(Request $request, string $type = 'contracting'): RedirectResponse
    {
        $type = $this->normalizeType($type);
        $isPresent = $request->input('status') === AttendanceRecord::STATUS_PRESENT;
        $isLeave = $request->input('status') === AttendanceRecord::STATUS_LEAVE;
        $dateRange = $request->user()->attendanceDateRange();

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
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
                ...$this->attendanceDateRules($dateRange),
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

        $alreadyMarked = AttendanceRecord::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('attendance_date', $data['attendance_date'])
            ->exists();

        if ($alreadyMarked) {
            throw ValidationException::withMessages([
                'employee_id' => 'Attendance for this employee is already marked on the selected date.',
            ]);
        }

        $employeeUnavailable = Employee::query()
            ->whereKey($data['employee_id'])
            ->where('status', Employee::STATUS_ON_LEAVE)
            ->exists();

        $hasLeaveRange = EmployeeLeave::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('start_date', '<=', $data['attendance_date'])
            ->whereDate('end_date', '>=', $data['attendance_date'])
            ->exists();

        if ($employeeUnavailable || $hasLeaveRange) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee is on leave for the selected date.',
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

        AttendanceRecord::create($data);

        if ($isLeave) {
            EmployeeLeave::create([
                'employee_id' => $data['employee_id'],
                'created_by' => $request->user()->id,
                'start_date' => $data['attendance_date'],
                'end_date' => $data['attendance_date'],
                'reason' => $data['leave_reason'],
            ]);
        }

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

    private function attendanceDateRules(array $dateRange): array
    {
        $rules = ['before_or_equal:'.$dateRange['max']];

        if ($dateRange['min']) {
            $rules[] = 'after_or_equal:'.$dateRange['min'];
        }

        return $rules;
    }
}
