<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\EmployeePayrollSetting;
use App\Models\Project;
use App\Support\Overtime;

class ProjectAttendanceSummaryService
{
    public function build(array $filters): array
    {
        $projects = Project::whereIn('id', $filters['projectIds'])->orderBy('name')->get();
        $records = AttendanceRecord::with('employee.payrollSetting')
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
                'labourDays' => 0.0, 'overtimeMinutes' => 0, 'basicCost' => 0.0, 'overtimeCost' => 0.0];
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
                $bucket['overtimeCost'] += $setting?->is_overtime_enabled === false ? 0 : ($ot / 60) * $daily / max(1, (int) ($setting?->standard_hours_per_day ?? 8));
                unset($bucket);
            }
        }
        $rows = collect($buckets)->map(function ($bucket) {
            $row = array_diff_key($bucket, array_flip(['dates', 'people', 'missing', 'overtimeMinutes']));
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
}
