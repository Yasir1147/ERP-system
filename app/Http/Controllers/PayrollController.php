<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayrollSetting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedType = $this->normalizeType($request->query('type'));

        $employees = Employee::query()
            ->with('payrollSetting')
            ->when($selectedType, fn ($query) => $query->where('type', $selectedType))
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'type' => $employee->type,
                'status' => $employee->status,
                'label' => $employee->name.' - '.$employee->profession,
                'payrollSetting' => $this->settingPayload($employee),
            ]);

        return Inertia::render('Payroll/Index', [
            'employees' => $employees,
            'filters' => [
                'type' => $selectedType ?? 'all',
            ],
            'typeOptions' => $this->typeOptions(),
            'employeeTypes' => Employee::TYPES,
            'salaryRules' => EmployeePayrollSetting::RULES,
        ]);
    }

    public function report(Request $request): Response
    {
        $selectedMonth = $request->date('month')?->format('Y-m') ?? Carbon::today()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthStart = $month->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();
        $selectedType = $this->normalizeType($request->query('type'));
        $selectedEmployeeId = $this->normalizeEmployeeId($request->query('employee_id'), $selectedType);

        $employees = Employee::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'profession', 'type', 'status'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'type' => $employee->type,
                'status' => $employee->status,
                'label' => $employee->name.' - '.$employee->profession,
            ]);

        $payrollRows = $this->payrollRows($monthStart, $monthEnd, $selectedType, $selectedEmployeeId);

        return Inertia::render('Payroll/Report', [
            'employees' => $employees,
            'payrollRows' => $payrollRows,
            'summary' => [
                'employeeCount' => $payrollRows->count(),
                'presentDays' => $payrollRows->sum('presentDays'),
                'overtimeHours' => $payrollRows->sum('overtimeHours'),
                'basicSalary' => round($payrollRows->sum('basicSalary'), 2),
                'overtimeAmount' => round($payrollRows->sum('overtimeAmount'), 2),
                'totalSalary' => round($payrollRows->sum('totalSalary'), 2),
            ],
            'filters' => [
                'type' => $selectedType ?? 'all',
                'employeeId' => $selectedEmployeeId ? (string) $selectedEmployeeId : 'all',
                'month' => $selectedMonth,
            ],
            'typeOptions' => $this->typeOptions(),
            'employeeTypes' => Employee::TYPES,
            'salaryRules' => EmployeePayrollSetting::RULES,
            'selectedMonthLabel' => $month->format('F Y'),
        ]);
    }

    public function updateSetting(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'daily_salary' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'salary_rule' => ['required', Rule::in(array_keys(EmployeePayrollSetting::RULES))],
            'standard_hours_per_day' => ['required', 'integer', 'min:1', 'max:24'],
            'is_overtime_enabled' => ['required', 'boolean'],
        ]);

        $employee->payrollSetting()->updateOrCreate(
            ['employee_id' => $employee->id],
            $data,
        );

        return to_route('payroll.index', $request->only(['type']));
    }

    private function payrollRows(string $monthStart, string $monthEnd, ?string $type, ?int $employeeId): Collection
    {
        $employees = Employee::query()
            ->with('payrollSetting')
            ->where('status', '!=', Employee::STATUS_LEFT)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($employeeId, fn ($query) => $query->whereKey($employeeId))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $records = AttendanceRecord::query()
            ->leftJoin('projects', 'attendance_records.project_id', '=', 'projects.id')
            ->whereBetween('attendance_records.attendance_date', [$monthStart, $monthEnd])
            ->whereIn('attendance_records.employee_id', $employees->pluck('id'))
            ->get([
                'attendance_records.employee_id',
                'attendance_records.status',
                'attendance_records.overtime_hours',
                'projects.name as project_name',
            ])
            ->groupBy('employee_id');

        return $employees->map(function (Employee $employee) use ($records) {
            $setting = $employee->payrollSetting;
            $employeeRecords = $records->get($employee->id, collect());
            $presentDays = $employeeRecords->where('status', AttendanceRecord::STATUS_PRESENT)->count();
            $absentDays = $employeeRecords->where('status', AttendanceRecord::STATUS_ABSENT)->count();
            $leaveDays = $employeeRecords->where('status', AttendanceRecord::STATUS_LEAVE)->count();
            $overtimeHours = (int) $employeeRecords->sum(fn ($record) => (int) ($record->overtime_hours ?? 0));
            $dailySalary = (float) ($setting?->daily_salary ?? 0);
            $salaryRule = $setting?->salary_rule ?? EmployeePayrollSetting::RULE_PRESENT_DAYS;
            $standardHours = max(1, (int) ($setting?->standard_hours_per_day ?? 8));
            $hourlyRate = $dailySalary / $standardHours;
            $payableDays = $salaryRule === EmployeePayrollSetting::RULE_FIXED_30_DAYS ? 30 : $presentDays;
            $basicSalary = $dailySalary * $payableDays;
            $overtimeAmount = $setting?->is_overtime_enabled === false ? 0 : $overtimeHours * $hourlyRate;

            return [
                'employeeId' => $employee->id,
                'employeeName' => $employee->name,
                'employeeProfession' => $employee->profession,
                'employeeType' => $employee->type,
                'dailySalary' => round($dailySalary, 2),
                'salaryRule' => $salaryRule,
                'standardHoursPerDay' => $standardHours,
                'presentDays' => $presentDays,
                'absentDays' => $absentDays,
                'leaveDays' => $leaveDays,
                'overtimeHours' => $overtimeHours,
                'hourlyRate' => round($hourlyRate, 2),
                'basicSalary' => round($basicSalary, 2),
                'overtimeAmount' => round($overtimeAmount, 2),
                'totalSalary' => round($basicSalary + $overtimeAmount, 2),
                'projectCount' => $employeeRecords->where('status', AttendanceRecord::STATUS_PRESENT)->pluck('project_name')->filter()->unique()->count(),
            ];
        })->values();
    }

    private function settingPayload(Employee $employee): array
    {
        $setting = $employee->payrollSetting;

        return [
            'dailySalary' => (string) ($setting?->daily_salary ?? '0.00'),
            'salaryRule' => $setting?->salary_rule ?? EmployeePayrollSetting::RULE_PRESENT_DAYS,
            'standardHoursPerDay' => (int) ($setting?->standard_hours_per_day ?? 8),
            'isOvertimeEnabled' => $setting?->is_overtime_enabled ?? true,
        ];
    }

    private function normalizeType(mixed $type): ?string
    {
        if (! is_string($type) || $type === '' || $type === 'all') {
            return null;
        }

        abort_unless(array_key_exists($type, Employee::TYPES), 404);

        return $type;
    }

    private function typeOptions(): Collection
    {
        return collect(['all' => 'All Employee Types'])->merge(Employee::TYPES)->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();
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
