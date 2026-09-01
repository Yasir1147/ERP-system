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
            'matrix' => $this->matrix($rows),
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

        // Absent and Leave carry no project — nobody is marked away *to* a
        // site — so a project statement built only from project-linked rows
        // could never show an absence. The crew is taken to be everyone who
        // worked this project in the range, and their away days are pulled in
        // beside their worked ones. Without this the grid reads as though the
        // whole crew turned up every single day.
        $crewIds = $records->pluck('employee_id')->filter()->unique();

        $awayRecords = $crewIds->isEmpty()
            ? collect()
            : $this->records($from, $to)
                ->whereIn('attendance_records.employee_id', $crewIds)
                ->whereNull('attendance_records.project_id')
                ->whereIn('attendance_records.status', [AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_LEAVE])
                ->get();

        $rows = $records
            ->concat($awayRecords)
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
            'matrix' => $this->matrix($rows),
            'totals' => $this->totals($rows, $withSalary),
            'withSalary' => $withSalary,
            'filters' => ['from' => $from, 'to' => $to],
            'rangeLabel' => $this->rangeLabel($from, $to),
        ];
    }

    /**
     * Every employee of one type, in one sheet.
     *
     * This is the timesheet's question — "who worked this month" — answered in
     * the same grid the site reads, so both can be exported from one place.
     *
     * @return array<string, mixed>
     */
    public function forEmployeeType(string $type, string $from, string $to, bool $withSalary): array
    {
        $employees = Employee::query()
            ->with('payrollSetting')
            ->where('type', $type)
            ->where('status', '!=', Employee::STATUS_LEFT)
            ->orderBy('code')
            ->get();

        $records = $this->records($from, $to)
            ->whereIn('attendance_records.employee_id', $employees->pluck('id'))
            ->get();

        $rows = $records
            ->map(fn (AttendanceRecord $record) => $this->rowFor($record, $withSalary, forEmployee: false))
            ->sortBy([['dateValue', 'asc'], ['employeeName', 'asc']])
            ->values();

        // Everyone on the books is a row, worked or not. A man who did not
        // turn up all month is exactly who a reader is looking for, and
        // leaving him off the sheet hides that.
        $roster = $employees->map(fn (Employee $employee) => [
            'employeeCode' => $employee->code,
            'employeeName' => $employee->name,
            'profession' => $employee->profession,
        ]);

        return [
            'mode' => 'type',
            'subject' => [
                'id' => null,
                'code' => null,
                'name' => Employee::TYPES[$type] ?? $type,
                'profession' => null,
                'typeLabel' => Employee::TYPES[$type] ?? $type,
                'status' => 'all',
                'missingSalary' => false,
            ],
            'rows' => $rows,
            'matrix' => $this->matrix($rows, $roster),
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
     * The same days turned side-on: one row per person, one column per date
     * the project actually ran.
     *
     * Only worked dates become columns. A site that runs two days a week over
     * four months would otherwise carry a hundred empty columns, and the sheet
     * stops being readable long before it stops being correct.
     *
     * A blank cell is deliberately "not listed" rather than absent: nobody
     * wrote that person down that day, which is not the same as marking them
     * away, and reading one as the other costs someone a day's pay.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function matrix(Collection $rows, ?Collection $roster = null): array
    {
        $recorded = $rows
            ->pluck('dateValue')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Sundays between the first and last worked day are kept as columns
        // even though nobody worked them. Dropping them makes a month read as
        // one unbroken run of days and hides the week's shape; the site sheet
        // has always shown the rest day in its place.
        $sundays = collect();

        if ($recorded->isNotEmpty()) {
            $day = Carbon::parse($recorded->first());
            $end = Carbon::parse($recorded->last());

            for (; $day->lte($end); $day->addDay()) {
                if ($day->isSunday()) {
                    $sundays->push($day->toDateString());
                }
            }
        }

        $dates = $recorded->concat($sundays)->unique()->sort()->values();

        $dateColumns = $dates->map(fn (string $date) => [
            'value' => $date,
            'label' => Carbon::parse($date)->format('d-M'),
            'weekday' => Carbon::parse($date)->format('D'),
            'isSunday' => Carbon::parse($date)->isSunday(),
        ]);

        $people = $rows
            ->groupBy(fn (array $row) => ($row['employeeCode'] ?? '').'|'.$row['employeeName'])
            ->map(function (Collection $personRows) use ($dates) {
                $first = $personRows->first();
                $byDate = $personRows->keyBy('dateValue');

                $cells = $dates->map(function (string $date) use ($byDate) {
                    $row = $byDate->get($date);

                    if (! $row) {
                        // A rest day is not an unmarked day. Nobody was meant
                        // to be written down on a Sunday.
                        return Carbon::parse($date)->isSunday()
                            ? ['code' => 'S', 'status' => 'rest', 'note' => 'Sunday']
                            : ['code' => '-', 'status' => 'not_listed', 'note' => null];
                    }

                    $code = match ($row['status']) {
                        AttendanceRecord::STATUS_PRESENT => $row['dayValue'] == 0.5 ? 'H' : 'P',
                        AttendanceRecord::STATUS_ABSENT => 'A',
                        default => 'L',
                    };

                    return [
                        'code' => $code,
                        'status' => $row['status'],
                        'note' => $row['overtimeHours'] ? $row['overtimeHours'].'h OT' : $row['note'],
                    ];
                });

                return [
                    'employeeCode' => $first['employeeCode'],
                    'employeeName' => $first['employeeName'],
                    'profession' => $first['profession'],
                    'cells' => $cells->values(),
                    'presentDays' => round($personRows->sum('dayValue'), 2),
                    'absentDays' => $personRows->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
                    'leaveDays' => $personRows->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
                    // Sundays are excluded: a rest day is not a day this person
                    // went unlisted, and counting it as one reads as neglect.
                    'notListed' => $dates
                        ->reject(fn (string $date) => Carbon::parse($date)->isSunday() || $byDate->has($date))
                        ->count(),
                ];
            })
            ->sortByDesc('presentDays')
            ->values();

        if ($roster) {
            $seen = $people->map(fn (array $person) => ($person['employeeCode'] ?? '').'|'.$person['employeeName'])->all();

            $missing = $roster
                ->reject(fn (array $person) => in_array(($person['employeeCode'] ?? '').'|'.$person['employeeName'], $seen, true))
                ->map(fn (array $person) => [
                    'employeeCode' => $person['employeeCode'],
                    'employeeName' => $person['employeeName'],
                    'profession' => $person['profession'],
                    'cells' => $dates->map(fn (string $date) => Carbon::parse($date)->isSunday()
                        ? ['code' => 'S', 'status' => 'rest', 'note' => 'Sunday']
                        : ['code' => '-', 'status' => 'not_listed', 'note' => null])->values(),
                    'presentDays' => 0.0,
                    'absentDays' => 0,
                    'leaveDays' => 0,
                    'notListed' => $dates->reject(fn (string $date) => Carbon::parse($date)->isSunday())->count(),
                ]);

            $people = $people->concat($missing)->values();
        }

        $footer = $dates->map(function (string $date) use ($rows) {
            $onDate = $rows->where('dateValue', $date);

            return [
                'present' => round($onDate->where('status', AttendanceRecord::STATUS_PRESENT)->sum('dayValue'), 2),
                'absent' => $onDate->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            ];
        });

        return [
            'dates' => $dateColumns,
            'people' => $people,
            'footer' => $footer->values(),
            'footerTotals' => [
                'present' => round($footer->sum('present'), 2),
                'absent' => (int) $footer->sum('absent'),
            ],
        ];
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
