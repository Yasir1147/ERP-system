<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use App\Models\PayrollAdjustment;

class EmployeeProjectSheetService
{
    public function build(array $filters): array
    {
        $employees = Employee::with('payrollSetting')->whereIn('id', $filters['employeeIds'])->orderBy('code')->get();
        $records = AttendanceRecord::whereIn('employee_id', $employees->modelKeys())
            ->where('status', AttendanceRecord::STATUS_PRESENT)
            ->whereDate('attendance_date', '>=', $filters['from'])
            ->whereDate('attendance_date', '<=', $filters['to'])
            ->get(['project_id', 'overtime_project_id']);
        $ids = $records->pluck('project_id')->merge($records->pluck('overtime_project_id'))->filter()->unique()->values()->all();
        $summary = app(ProjectAttendanceSummaryService::class)->build([...$filters, 'projectIds' => $ids], true);
        // A monthly adjustment belongs to its month, rather than an arbitrary date in that month.
        $bonuses = PayrollAdjustment::whereIn('employee_id', $employees->modelKeys())
            ->whereDate('month', '>=', substr($filters['from'], 0, 7).'-01')
            ->whereDate('month', '<=', substr($filters['to'], 0, 7).'-01')
            ->get()->groupBy('employee_id');
        $projects = $summary['rows']->filter(fn ($p) => $p['persons'] > 0)->values();
        $metrics = ['labourDays', 'overtimeHours', 'basicCost', 'overtimeCost'];
        $rows = $employees->map(function ($employee) use ($projects, $metrics, $bonuses) {
            $setting = $employee->payrollSetting;
            $daily = ! $setting ? null : ($setting->salary_rule === EmployeePayrollSetting::RULE_FIXED_30_DAYS && $setting->monthly_salary !== null
                ? (float) $setting->monthly_salary / 30 : (float) $setting->daily_salary);
            $cells = [];
            foreach ($projects as $project) {
                $history = collect($project['history'])->where('employeeId', $employee->id);
                $cells[] = collect($metrics)->mapWithKeys(fn ($metric) => [$metric => round($history->sum($metric), $metric === 'overtimeHours' ? 8 : 2)])->all();
            }
            $totals = collect($metrics)->mapWithKeys(fn ($metric) => [$metric => round(collect($cells)->sum($metric), $metric === 'overtimeHours' ? 8 : 2)])->all();
            $bonus = round(($bonuses->get($employee->id) ?? collect())->sum('bonus_extra'), 2);

            return ['id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'profession' => $employee->profession,
                'dailySalary' => $daily, 'missingPayroll' => ! $setting, 'bonus' => $bonus, 'projects' => $cells,
                ...$totals, 'totalCost' => round($totals['basicCost'] + $totals['overtimeCost'] + $bonus, 2)];
        })->values();
        $totals = collect([...$metrics, 'bonus', 'totalCost'])->mapWithKeys(fn ($metric) => [$metric => round($rows->sum($metric), $metric === 'overtimeHours' ? 8 : 2)])->all();
        $totals['projects'] = $projects->map(fn ($p, $index) => collect($metrics)->mapWithKeys(fn ($metric) => [$metric => round($rows->sum(fn ($r) => $r['projects'][$index][$metric]), $metric === 'overtimeHours' ? 8 : 2)])->all())->all();

        return ['filters' => $filters, 'projects' => $projects->map(fn ($p) => ['id' => $p['projectId'], 'code' => $p['code'], 'name' => $p['name']])->all(),
            'rows' => $rows->all(), 'totals' => $totals, 'missingPayroll' => $rows->where('missingPayroll', true)->count()];
    }
}
