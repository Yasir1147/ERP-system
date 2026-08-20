<?php

namespace App\Services\Projects;

use App\Models\AppSetting;
use App\Models\AttendanceRecord;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Labour history for a single project.
 *
 * Shared by the Overview modal, the Excel export, and the print/PDF view so
 * the three can never disagree about what a project's labour cost was.
 */
class ProjectEmployeeHistoryService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Project $project, ?string $from = null, ?string $to = null): array
    {
        // Read per build, never cached on the service: it is injected into a
        // controller the router keeps alive, so a cached value would outlive
        // the request that changed the setting.
        $overhead = AppSetting::projectOverheadSettings();

        $records = AttendanceRecord::query()
            ->with(['employee.payrollSetting', 'submitter'])
            ->where('status', AttendanceRecord::STATUS_PRESENT)
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhere('overtime_project_id', $project->id);
            })
            ->when($from, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($to, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->get();

        $rows = $records->map(fn (AttendanceRecord $record) => $this->rowFor($record, $project, $overhead));

        $totals = [
            'uniqueEmployees' => $rows->pluck('employeeId')->filter()->unique()->count(),
            'entries' => $rows->count(),
            'workedDays' => $rows->pluck('dateValue')->filter()->unique()->count(),
            'overtimeHours' => (int) $rows->sum('overtimeHours'),
            'basicCost' => round($rows->sum('basicCost'), 2),
            'overtimeCost' => round($rows->sum('overtimeCost'), 2),
            'overheadCost' => round($rows->sum('overheadCost'), 2),
            'totalCost' => round($rows->sum('totalCost'), 2),
        ];

        $employeeSummary = $this->employeeSummary($rows, $totals['totalCost']);

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->project_code,
                'status' => $project->status,
                'type' => $project->type,
                'typeLabel' => Project::TYPES[$project->type],
                // Carried into the exports so labour cost can be read against
                // what the project was actually sold for.
                'contractValue' => $project->contract_value !== null ? (float) $project->contract_value : null,
            ],
            'records' => $rows->values(),
            'employeeSummary' => $employeeSummary,
            'totals' => $totals,
            // Employees with no payroll setting are costed at zero, so the
            // total understates reality. Every view must be able to say so.
            'missingPayrollEmployees' => $employeeSummary
                ->where('missingPayrollSetting', true)
                ->map(fn (array $row) => trim($row['employeeCode'].' - '.$row['employeeName'], ' -'))
                ->values(),
            'overhead' => $overhead,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'rangeLabel' => $this->rangeLabel($from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(AttendanceRecord $record, Project $project, array $overhead): array
    {
        $employee = $record->employee;
        $setting = $employee?->payrollSetting;

        $dailySalary = (float) ($setting?->daily_salary ?? 0);
        $standardHours = max(1, (int) ($setting?->standard_hours_per_day ?? 8));
        $attendanceFraction = (float) ($record->attendance_fraction ?? AttendanceRecord::FULL_DAY_FRACTION);

        $basicCost = (int) $record->project_id === (int) $project->id
            ? $dailySalary * $attendanceFraction
            : 0;

        $effectiveOvertimeProjectId = $record->overtime_project_id ?: $record->project_id;
        $overtimeHours = (int) $effectiveOvertimeProjectId === (int) $project->id
            ? (int) ($record->overtime_hours ?? 0)
            : 0;

        $overtimeCost = $setting?->is_overtime_enabled === false
            ? 0
            : $overtimeHours * ($dailySalary / $standardHours);

        // The loaded figure replaces basic salary rather than sitting beside
        // it, matching the Overview page: basic 1,000 at 2x costs 2,000.
        $costedBasicCost = $overhead['enabled'] ? $basicCost * $overhead['multiplier'] : $basicCost;
        $overheadCost = $costedBasicCost - $basicCost;

        return [
            'id' => $record->id,
            'date' => $record->attendance_date?->format('d/m/Y'),
            'dateValue' => $record->attendance_date?->toDateString(),
            'employeeId' => $employee?->id,
            'employeeCode' => $employee?->code ?? '-',
            'employeeName' => $employee?->name ?? 'Unknown Employee',
            'profession' => $employee?->profession ?? '-',
            'status' => $record->status,
            'attendanceFraction' => $attendanceFraction,
            'dailySalary' => round($dailySalary, 2),
            'overtimeHours' => $overtimeHours,
            'basicCost' => round($basicCost, 2),
            'overtimeCost' => round($overtimeCost, 2),
            'overheadCost' => round($overheadCost, 2),
            'totalCost' => round($costedBasicCost + $overtimeCost, 2),
            'submittedBy' => $record->submitter?->name ?? '-',
            'submittedByRole' => $record->submitter?->role,
            'missingPayrollSetting' => ! $setting,
        ];
    }

    /**
     * One row per employee, ordered by cost so the biggest contributors to
     * the project's labour bill read first.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function employeeSummary(Collection $rows, float $projectTotal): Collection
    {
        return $rows
            ->groupBy('employeeId')
            ->map(function (Collection $employeeRows) use ($projectTotal) {
                $first = $employeeRows->first();
                $totalCost = round($employeeRows->sum('totalCost'), 2);

                return [
                    'employeeId' => $first['employeeId'],
                    'employeeCode' => $first['employeeCode'],
                    'employeeName' => $first['employeeName'],
                    'profession' => $first['profession'],
                    'entries' => $employeeRows->count(),
                    'workedDays' => round($employeeRows->sum('attendanceFraction'), 2),
                    'overtimeHours' => (int) $employeeRows->sum('overtimeHours'),
                    'basicCost' => round($employeeRows->sum('basicCost'), 2),
                    'overtimeCost' => round($employeeRows->sum('overtimeCost'), 2),
                    'overheadCost' => round($employeeRows->sum('overheadCost'), 2),
                    'totalCost' => $totalCost,
                    'costShare' => $projectTotal > 0
                        ? round($totalCost / $projectTotal * 100, 1)
                        : 0.0,
                    'missingPayrollSetting' => (bool) $employeeRows->contains('missingPayrollSetting', true),
                    'submittedBy' => $employeeRows
                        ->map(fn (array $row) => trim($row['submittedBy'].($row['submittedByRole'] ? ' ('.$row['submittedByRole'].')' : '')))
                        ->filter(fn (string $submitter) => $submitter !== '-')
                        ->unique()
                        ->values()
                        ->implode(', ') ?: '-',
                ];
            })
            ->sortByDesc('totalCost')
            ->values();
    }

    private function rangeLabel(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            return 'All dates';
        }

        $format = fn (?string $date) => $date ? Carbon::parse($date)->format('d/m/Y') : null;

        if ($from && $to) {
            return $format($from).' — '.$format($to);
        }

        return $from ? 'From '.$format($from) : 'Up to '.$format($to);
    }
}
