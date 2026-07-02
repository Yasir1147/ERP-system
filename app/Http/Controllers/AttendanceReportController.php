<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $today = Carbon::today();
        $startDate = $request->date('start_date')?->toDateString() ?? $today->copy()->startOfMonth()->toDateString();
        $endDate = $request->date('end_date')?->toDateString() ?? $today->toDateString();

        if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $selectedType = $this->normalizeType($request->query('type'));
        $selectedEmployeeId = $this->normalizeEmployeeId($request->query('employee_id'), $selectedType);

        $employees = Employee::query()
            ->when($selectedType, fn ($query) => $query->where('type', $selectedType))
            ->orderBy('type')
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'profession', 'type', 'status'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'code' => $employee->code,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'type' => $employee->type,
                'status' => $employee->status,
                'label' => collect([$employee->code, $employee->name, $employee->profession])->filter()->implode(' - '),
            ]);

        $attendanceRecords = $this->attendanceRecords($startDate, $endDate, $selectedType, $selectedEmployeeId);
        $leaveRangeRecords = $this->leaveRangeRecords($startDate, $endDate, $selectedType, $selectedEmployeeId, $attendanceRecords);

        $records = $attendanceRecords
            ->merge($leaveRangeRecords)
            ->sortBy([
                ['dateRaw', 'asc'],
                ['employeeName', 'asc'],
            ])
            ->values();

        $summary = [
            'present' => $records->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
            'absent' => $records->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'leave' => $records->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
            'overtimeDays' => $records->filter(fn ($record) => (int) ($record['overtimeHours'] ?? 0) > 0)->count(),
            'overtimeHours' => $records->sum(fn ($record) => (int) ($record['overtimeHours'] ?? 0)),
            'totalRecords' => $records->count(),
        ];

        $projectSummary = $records
            ->where('status', AttendanceRecord::STATUS_PRESENT)
            ->flatMap(function ($record) {
                $items = [];

                if (filled($record['projectName'])) {
                    $items[] = [
                        'projectName' => $record['projectName'],
                        'days' => 1,
                        'overtimeHours' => 0,
                    ];
                }

                if ((int) ($record['overtimeHours'] ?? 0) > 0) {
                    $items[] = [
                        'projectName' => $record['overtimeProjectName'] ?: $record['projectName'],
                        'days' => 0,
                        'overtimeHours' => (int) $record['overtimeHours'],
                    ];
                }

                return $items;
            })
            ->filter(fn ($record) => filled($record['projectName']))
            ->groupBy('projectName')
            ->map(fn (Collection $projectRecords, string $projectName) => [
                'projectName' => $projectName,
                'days' => $projectRecords->sum('days'),
                'overtimeHours' => $projectRecords->sum('overtimeHours'),
            ])
            ->sortByDesc('days')
            ->values();

        return Inertia::render('Attendance/Index', [
            'employees' => $employees,
            'records' => $records,
            'summary' => $summary,
            'projectSummary' => $projectSummary,
            'filters' => [
                'type' => $selectedType ?? 'all',
                'employeeId' => $selectedEmployeeId ? (string) $selectedEmployeeId : 'all',
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
            'typeOptions' => collect(['all' => 'All Employee Types'])->merge(Employee::TYPES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'employeeTypes' => Employee::TYPES,
            'projects' => Project::query()
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'type'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'type' => $project->type,
                    'label' => $project->name.' - '.$project->status,
                ]),
        ]);
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $status = $request->input('status');
        $isPresent = $status === AttendanceRecord::STATUS_PRESENT;
        $isLeave = $status === AttendanceRecord::STATUS_LEAVE;

        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', '!=', Employee::STATUS_LEFT)),
            ],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'project_id' => [
                'nullable',
                Rule::requiredIf($isPresent),
                'integer',
                Rule::exists('projects', 'id'),
            ],
            'has_overtime' => ['required', 'boolean'],
            'overtime_hours' => [
                'nullable',
                Rule::requiredIf($isPresent && $request->boolean('has_overtime')),
                'integer',
                'between:1,10',
            ],
            'overtime_project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'leave_reason' => ['nullable', Rule::requiredIf($isLeave), 'string', 'max:1000'],
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);

        if ($isPresent && ! Project::query()->whereKey($data['project_id'])->where('type', $employee->type)->exists()) {
            return back()->withErrors(['project_id' => 'Selected project does not match employee type.']);
        }

        if ($isPresent && filled($data['overtime_project_id'] ?? null) && ! Project::query()->whereKey($data['overtime_project_id'])->where('type', $employee->type)->exists()) {
            return back()->withErrors(['overtime_project_id' => 'Selected overtime project does not match employee type.']);
        }

        $alreadyMarked = AttendanceRecord::query()
            ->whereKeyNot($attendanceRecord->id)
            ->where('employee_id', $data['employee_id'])
            ->whereDate('attendance_date', $data['attendance_date'])
            ->exists();

        if ($alreadyMarked) {
            return back()->withErrors(['attendance_date' => 'Attendance is already marked for this employee on the selected date.']);
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

        $attendanceRecord->update($data);

        return to_route('attendance.index', [
            'type' => $request->query('filter_type', 'all'),
            'employee_id' => $request->query('filter_employee_id', 'all'),
            'start_date' => $request->query('filter_start_date'),
            'end_date' => $request->query('filter_end_date'),
        ])->with('success', 'Attendance record updated.');
    }

    private function attendanceRecords(string $startDate, string $endDate, ?string $type, ?int $employeeId): Collection
    {
        return AttendanceRecord::query()
            ->leftJoin('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->leftJoin('projects', 'attendance_records.project_id', '=', 'projects.id')
            ->leftJoin('projects as overtime_projects', 'attendance_records.overtime_project_id', '=', 'overtime_projects.id')
            ->leftJoin('users', 'attendance_records.submitted_by', '=', 'users.id')
            ->whereBetween('attendance_records.attendance_date', [$startDate, $endDate])
            ->when($type, fn ($query) => $query->where('employees.type', $type))
            ->when($employeeId, fn ($query) => $query->where('attendance_records.employee_id', $employeeId))
            ->orderBy('attendance_records.attendance_date')
            ->orderBy('employees.name')
            ->get([
                'attendance_records.id',
                'attendance_records.employee_id',
                'attendance_records.status',
                'attendance_records.attendance_date',
                'attendance_records.leave_reason',
                'attendance_records.project_id',
                'attendance_records.overtime_project_id',
                'attendance_records.has_overtime',
                'attendance_records.overtime_hours',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.profession as employee_profession',
                'employees.type as employee_type',
                'projects.name as project_name',
                'overtime_projects.name as overtime_project_name',
                'users.name as submitted_by_name',
                'users.role as submitted_by_role',
            ])
            ->map(fn ($record) => [
                'id' => 'attendance-'.$record->id,
                'employeeId' => $record->employee_id,
                'employeeCode' => $record->employee_code,
                'employeeName' => $record->employee_name,
                'employeeProfession' => $record->employee_profession,
                'employeeType' => $record->employee_type,
                'projectId' => $record->project_id,
                'projectName' => $record->project_name,
                'overtimeProjectId' => $record->overtime_project_id,
                'overtimeProjectName' => $record->overtime_project_name ?: $record->project_name,
                'hasOvertime' => (bool) $record->has_overtime,
                'status' => $record->status,
                'dateRaw' => Carbon::parse($record->attendance_date)->toDateString(),
                'date' => Carbon::parse($record->attendance_date)->format('d/m/Y'),
                'reason' => $record->leave_reason,
                'overtimeHours' => $record->overtime_hours,
                'submittedBy' => $record->submitted_by_name,
                'submittedByRole' => $record->submitted_by_role,
            ]);
    }

    private function leaveRangeRecords(string $startDate, string $endDate, ?string $type, ?int $employeeId, Collection $attendanceRecords): Collection
    {
        $markedKeys = $attendanceRecords
            ->mapWithKeys(fn ($record) => [$record['employeeId'].'|'.$record['dateRaw'] => true]);

        return EmployeeLeave::query()
            ->join('employees', 'employee_leaves.employee_id', '=', 'employees.id')
            ->leftJoin('users', 'employee_leaves.created_by', '=', 'users.id')
            ->whereDate('employee_leaves.start_date', '<=', $endDate)
            ->whereDate('employee_leaves.end_date', '>=', $startDate)
            ->when($type, fn ($query) => $query->where('employees.type', $type))
            ->when($employeeId, fn ($query) => $query->where('employee_leaves.employee_id', $employeeId))
            ->get([
                'employee_leaves.id',
                'employee_leaves.employee_id',
                'employee_leaves.start_date',
                'employee_leaves.end_date',
                'employee_leaves.reason',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.profession as employee_profession',
                'employees.type as employee_type',
                'users.name as submitted_by_name',
                'users.role as submitted_by_role',
            ])
            ->flatMap(function ($leave) use ($startDate, $endDate, $markedKeys) {
                $rangeStart = Carbon::parse(max($leave->start_date->toDateString(), $startDate));
                $rangeEnd = Carbon::parse(min($leave->end_date->toDateString(), $endDate));

                return collect(CarbonPeriod::create($rangeStart, $rangeEnd))
                    ->reject(fn (Carbon $date) => $markedKeys->has($leave->employee_id.'|'.$date->toDateString()))
                    ->map(fn (Carbon $date) => [
                        'id' => 'leave-'.$leave->id.'-'.$date->toDateString(),
                        'employeeId' => $leave->employee_id,
                        'employeeCode' => $leave->employee_code,
                        'employeeName' => $leave->employee_name,
                        'employeeProfession' => $leave->employee_profession,
                        'employeeType' => $leave->employee_type,
                        'projectName' => null,
                        'status' => AttendanceRecord::STATUS_LEAVE,
                        'dateRaw' => $date->toDateString(),
                        'date' => $date->format('d/m/Y'),
                        'reason' => $leave->reason ?: 'Leave',
                        'overtimeHours' => null,
                        'submittedBy' => $leave->submitted_by_name,
                        'submittedByRole' => $leave->submitted_by_role,
                    ]);
            })
            ->values();
    }

    private function normalizeType(mixed $type): ?string
    {
        if (! is_string($type) || $type === '' || $type === 'all') {
            return null;
        }

        abort_unless(array_key_exists($type, Employee::TYPES), 404);

        return $type;
    }

    private function normalizeEmployeeId(mixed $employeeId, ?string $type): ?int
    {
        if (! is_numeric($employeeId)) {
            return null;
        }

        $employeeId = (int) $employeeId;

        $rule = Rule::exists('employees', 'id');

        if ($type) {
            $rule->where('type', $type);
        }

        validator(
            ['employee_id' => $employeeId],
            ['employee_id' => ['required', 'integer', $rule]]
        )->validate();

        return $employeeId;
    }
}
