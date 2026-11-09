<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Support\Overtime;

class ProjectAttendanceSummaryService
{
    public function build(array $filters, bool $includeHistory = false): array
    {
        $projects = Project::whereIn('id', $filters['projectIds'])->orderBy('name')->get();
        $records = AttendanceRecord::with($includeHistory ? ['employee.payrollSetting', 'submitter', 'project', 'overtimeProject'] : ['employee.payrollSetting'])
            ->where('status', AttendanceRecord::STATUS_PRESENT)
            ->whereDate('attendance_date', '>=', $filters['from'])
            ->whereDate('attendance_date', '<=', $filters['to'])
            ->where(fn ($q) => $q->whereIn('project_id', $projects->modelKeys())->orWhereIn('overtime_project_id', $projects->modelKeys()))
            ->get();
        $buckets = [];
        $allPeople = $missingPeople = [];
        foreach ($projects as $project) {
            $buckets[$project->id] = ['projectId' => $project->id, 'code' => $project->project_code, 'name' => $project->name,
                'typeLabel' => Project::TYPES[$project->type] ?? $project->type, 'dates' => [], 'people' => [], 'missing' => [],
                'labourDays' => 0.0, 'overtimeMinutes' => 0, 'basicCost' => 0.0, 'overtimeCost' => 0.0, 'history' => []];
        }
        foreach ($records as $record) {
            $setting = $record->employee?->payrollSetting;
            $daily = $setting?->salary_rule === EmployeePayrollSetting::RULE_FIXED_30_DAYS && $setting->monthly_salary !== null
                ? (float) $setting->monthly_salary / 30
                : (float) ($setting?->daily_salary ?? 0);
            $fraction = (float) ($record->attendance_fraction ?? AttendanceRecord::FULL_DAY_FRACTION);
            $minutes = (int) round(Overtime::hours($record->overtime_hours ?? 0) * 60);
            $otProject = $record->overtime_project_id ?: $record->project_id;
            foreach (array_unique(array_filter([$record->project_id, $otProject])) as $id) {
                if (! isset($buckets[$id])) {
                    continue;
                }
                $basic = (int) $record->project_id === (int) $id;
                $ot = (int) $otProject === (int) $id ? $minutes : 0;
                if ((! $basic || $fraction <= 0) && $ot === 0) {
                    continue;
                }
                $bucket = &$buckets[$id];
                $bucket['dates'][$record->attendance_date->toDateString()] = true;
                $bucket['people'][$record->employee_id] = true;
                $allPeople[$record->employee_id] = true;
                if (! $setting) {
                    $bucket['missing'][$record->employee_id] = true;
                    $missingPeople[$record->employee_id] = true;
                }
                if ($basic) {
                    $bucket['labourDays'] += $fraction;
                    $bucket['basicCost'] += $daily * $fraction;
                }
                $bucket['overtimeMinutes'] += $ot;
                $otCost = $setting?->is_overtime_enabled === false ? 0 : ($ot / 60) * $daily / max(1, (int) ($setting?->standard_hours_per_day ?? 8));
                $bucket['overtimeCost'] += $otCost;
                if ($includeHistory) {
                    $basicCost = $basic ? $daily * $fraction : 0;
                    $bucket['history'][] = [
                        'id' => $record->id, 'date' => $record->attendance_date->toDateString(),
                        'employeeId' => $record->employee_id, 'employeeCode' => $record->employee?->code,
                        'employeeName' => $record->employee?->name ?? 'Unknown Employee', 'profession' => $record->employee?->profession,
                        'labourDays' => $basic ? $fraction : 0, 'overtimeHours' => $ot / 60,
                        'dailySalary' => $daily, 'basicCost' => $basicCost, 'overtimeCost' => $otCost,
                        'totalCost' => $basicCost + $otCost, 'missingPayroll' => ! $setting,
                        'mainProject' => $record->project?->name, 'overtimeProject' => $record->overtimeProject?->name ?? $record->project?->name,
                        'submittedBy' => $record->submitter?->name ?? '-', 'note' => $record->leave_reason,
                    ];
                }
                unset($bucket);
            }
        }
        $rows = collect($buckets)->map(function ($bucket) use ($includeHistory) {
            $row = array_diff_key($bucket, array_flip(['dates', 'people', 'missing', 'overtimeMinutes', 'history']));
            if ($includeHistory) {
                $row['history'] = $bucket['history'];
            }
            $row['workedDays'] = count($bucket['dates']);
            $row['persons'] = count($bucket['people']);
            $row['missingPayroll'] = count($bucket['missing']);
            $row['labourDays'] = round($bucket['labourDays'], 2);
            $row['overtimeHours'] = $bucket['overtimeMinutes'] / 60;
            $row['basicCost'] = round($bucket['basicCost'], 2);
            $row['overtimeCost'] = round($bucket['overtimeCost'], 2);
            $row['totalCost'] = round($row['basicCost'] + $row['overtimeCost'], 2);

            return $row;
        })->values();

        return ['filters' => $filters, 'rows' => $rows, 'totals' => [
            'projects' => $rows->count(), 'workedDays' => $rows->sum('workedDays'), 'persons' => count($allPeople),
            'labourDays' => round($rows->sum('labourDays'), 2), 'overtimeHours' => Overtime::hours($rows->sum('overtimeHours')),
            'basicCost' => round($rows->sum('basicCost'), 2), 'overtimeCost' => round($rows->sum('overtimeCost'), 2),
            'totalCost' => round($rows->sum('totalCost'), 2), 'missingPayroll' => count($missingPeople),
        ]];
    }

    public function history(Project $project, array $filters): array
    {
        $filters['projectIds'] = [$project->id];
        $row = $this->build($filters, true)['rows']->first();
        $records = collect($row['history'])->sortBy([['date', 'asc'], ['employeeName', 'asc']])->values();
        unset($row['history']);
        $employees = $records->groupBy('employeeId')->map(function ($rows) use ($row) {
            $first = $rows->first();
            $cost = round($rows->sum('totalCost'), 2);

            return [
                'employeeId' => $first['employeeId'], 'employeeCode' => $first['employeeCode'],
                'employeeName' => $first['employeeName'], 'profession' => $first['profession'],
                'entries' => $rows->count(), 'workedDays' => $rows->pluck('date')->unique()->count(),
                'labourDays' => round($rows->sum('labourDays'), 2), 'overtimeHours' => Overtime::hours($rows->sum('overtimeHours')),
                'basicCost' => round($rows->sum('basicCost'), 2), 'overtimeCost' => round($rows->sum('overtimeCost'), 2),
                'totalCost' => $cost, 'costShare' => $row['totalCost'] > 0 ? round($cost / $row['totalCost'] * 100, 1) : 0,
                'missingPayroll' => $rows->contains('missingPayroll', true),
                'submittedBy' => $rows->pluck('submittedBy')->unique()->implode(', '),
            ];
        })->sortByDesc('totalCost')->values();

        return ['project' => ['id' => $project->id, 'name' => $project->name, 'code' => $project->project_code, 'typeLabel' => $row['typeLabel']],
            'filters' => $filters, 'totals' => $row, 'employees' => $employees, 'records' => $records];
    }
}
