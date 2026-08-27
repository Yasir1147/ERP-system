<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A day-by-day attendance statement for one employee or one project.
 *
 * The timesheet answers "who worked this month" across everyone at once; this
 * answers "what did this one person, or this one project, actually do", which
 * is the shape a statement takes when it is handed to a client, a project
 * manager, or the worker being questioned about a day.
 *
 * Shared by the screen, the Excel export, and the print view so the three can
 * never disagree.
 */
class AttendanceStatementService
{
    /**
     * @return array<string, mixed>
     */
    public function forEmployee(Employee $employee, string $from, string $to, bool $withSalary): array
    {
        $records = $this->records($from, $to)
            ->where('attendance_records.employee_id', $employee->id)
            ->get();

        $rows = $records
            ->map(fn (AttendanceRecord $record) => $this->rowFor($record, $withSalary, forEmployee: true))
            ->concat($this->leaveRows($employee->id, null, $from, $to, $records))
            ->sortBy('dateValue')
            ->values();

        return [
            'mode' => 'employee',
            'subject' => [
                'id' => $employee->id,
                'code' => $employee->code,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'typeLabel' => Employee::TYPES[$employee->type] ?? $employee->type,
                'status' => $employee->status,
                'missingSalary' => $employee->payrollSetting === null,
            ],
            'rows' => $rows,
            'totals' => $this->totals($rows, $withSalary),
            'withSalary' => $withSalary,
            'filters' => ['from' => $from, 'to' => $to],
            'rangeLabel' => $this->rangeLabel($from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project, string $from, string $to, bool $withSalary): array
    {
        $records = $this->records($from, $to)
            ->where(function ($query) use ($project) {
                $query->where('attendance_records.project_id', $project->id)
                    ->orWhere('attendance_records.overtime_project_id', $project->id);
            })
            ->get();

        $rows = $records
            ->map(fn (AttendanceRecord $record) => $this->rowFor($record, $withSalary, forEmployee: false))
            ->sortBy([['dateValue', 'asc'], ['employeeName', 'asc']])
            ->values();

        return [
            'mode' => 'project',
            'subject' => [
                'id' => $project->id,
                'code' => $project->project_code,
                'name' => $project->name,
                'profession' => $project->client_name,
                'typeLabel' => Project::TYPES[$project->type] ?? $project->type,
                'status' => $project->status,
                'missingSalary' => false,
            ],
            'rows' => $rows,
            'totals' => $this->totals($rows, $withSalary),
            'withSalary' => $withSalary,
            'filters' => ['from' => $from, 'to' => $to],
            'rangeLabel' => $this->rangeLabel($from, $to),
        ];
    }

    private function records(string $from, string $to)
    {
        return AttendanceRecord::query()
            ->with(['employee.payrollSetting', 'project:id,name', 'overtimeProject:id,name'])
            ->whereDate('attendance_records.attendance_date', '>=', $from)
            ->whereDate('attendance_records.attendance_date', '<=', $to)
            ->orderBy('attendance_records.attendance_date');
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(AttendanceRecord $record, bool $withSalary, bool $forEmployee): array
    {
        $employee = $record->employee;
        $setting = $employee?->payrollSetting;
        $dailySalary = (float) ($setting?->daily_salary ?? 0);
        $standardHours = max(1, (int) ($setting?->standard_hours_per_day ?? 8));
        $fraction = (float) ($record->attendance_fraction ?? AttendanceRecord::FULL_DAY_FRACTION);
        $overtimeHours = (int) ($record->overtime_hours ?? 0);

        $isPresent = $record->status === AttendanceRecord::STATUS_PRESENT;
        $basicCost = $isPresent ? $dailySalary * $fraction : 0.0;
        $overtimeCost = $setting?->is_overtime_enabled === false
            ? 0.0
            : $overtimeHours * ($dailySalary / $standardHours);

        return [
            'id' => (string) $record->id,
            'date' => $record->attendance_date?->format('d/m/Y'),
            'dateValue' => $record->attendance_date?->toDateString(),
            'weekday' => $record->attendance_date?->format('D'),
            'employeeCode' => $employee?->code,
            'employeeName' => $employee?->name ?? 'Unknown Employee',
            'profession' => $employee?->profession,
            'projectName' => $record->project?->name,
            'overtimeProjectName' => $record->overtimeProject?->name ?: $record->project?->name,
            'status' => $record->status,
            'dayValue' => $isPresent ? $fraction : 0.0,
            'overtimeHours' => $overtimeHours,
            'note' => $record->leave_reason,
            'dailySalary' => $withSalary ? round($dailySalary, 2) : null,
            'basicCost' => $withSalary ? round($basicCost, 2) : null,
            'overtimeCost' => $withSalary ? round($overtimeCost, 2) : null,
            'totalCost' => $withSalary ? round($basicCost + $overtimeCost, 2) : null,
            'missingSalary' => $withSalary && ! $setting,
        ];
    }

    /**
     * Leave ranges never create a daily row, so an employee statement would
     * otherwise show a silent gap on the days the person was formally on
     * leave. Days already carrying a record are left alone.
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return Collection<int, array<string, mixed>>
     */
    private function leaveRows(?int $employeeId, ?int $projectId, string $from, string $to, Collection $records): Collection
    {
        if ($employeeId === null) {
            return collect();
        }

        $markedDates = $records
            ->map(fn (AttendanceRecord $record) => $record->attendance_date?->toDateString())
            ->filter()
            ->all();

        return EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get()
            ->flatMap(function (EmployeeLeave $leave) use ($from, $to, $markedDates) {
                $start = Carbon::parse(max($leave->start_date->toDateString(), $from));
                $end = Carbon::parse(min($leave->end_date->toDateString(), $to));
                $days = collect();

                for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                    if (in_array($day->toDateString(), $markedDates, true)) {
                        continue;
                    }

                    $days->push([
                        'id' => 'leave-'.$leave->id.'-'.$day->toDateString(),
                        'date' => $day->format('d/m/Y'),
                        'dateValue' => $day->toDateString(),
                        'weekday' => $day->format('D'),
                        'employeeCode' => null,
                        'employeeName' => null,
                        'profession' => null,
                        'projectName' => null,
                        'overtimeProjectName' => null,
                        'status' => AttendanceRecord::STATUS_LEAVE,
                        'dayValue' => 0.0,
                        'overtimeHours' => 0,
                        'note' => $leave->reason ?: 'Leave',
                        'dailySalary' => null,
                        'basicCost' => null,
                        'overtimeCost' => null,
                        'totalCost' => null,
                        'missingSalary' => false,
                    ]);
                }

                return $days;
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(Collection $rows, bool $withSalary): array
    {
        return [
            'entries' => $rows->count(),
            'presentDays' => round($rows->sum('dayValue'), 2),
            'present' => $rows->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
            'absent' => $rows->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'leave' => $rows->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
            'overtimeHours' => (int) $rows->sum('overtimeHours'),
            'uniqueEmployees' => $rows->pluck('employeeCode')->filter()->unique()->count(),
            'projects' => $rows->pluck('projectName')->filter()->unique()->count(),
            'basicCost' => $withSalary ? round($rows->sum('basicCost'), 2) : null,
            'overtimeCost' => $withSalary ? round($rows->sum('overtimeCost'), 2) : null,
            'totalCost' => $withSalary ? round($rows->sum('totalCost'), 2) : null,
        ];
    }

    private function rangeLabel(string $from, string $to): string
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        // A whole calendar month is what most of these reports are, and
        // "August 2026" reads better on a statement than two dates.
        if ($start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($start->copy()->endOfMonth())) {
            return $start->format('F Y');
        }

        return $start->format('d/m/Y').' to '.$end->format('d/m/Y');
    }
}
