<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayrollSetting;
use App\Models\PayrollAdjustment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'code' => $employee->code,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'type' => $employee->type,
                'status' => $employee->status,
                'label' => trim($employee->code.' - '.$employee->name.' - '.$employee->profession),
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
            'absenceDeductionSettings' => AppSetting::absenceDeductionSettings(),
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

        $payrollRows = $this->payrollRows($monthStart, $monthEnd, $month->toDateString(), $selectedType, $selectedEmployeeId);

        return Inertia::render('Payroll/Report', [
            'employees' => $employees,
            'payrollRows' => $payrollRows,
            'summary' => [
                'employeeCount' => $payrollRows->count(),
                'presentDays' => $payrollRows->sum('presentDays'),
                'absentDays' => $payrollRows->sum('absentDays'),
                'overtimeHours' => $payrollRows->sum('overtimeHours'),
                'basicSalary' => round($payrollRows->sum('basicSalary'), 2),
                'absenceDeduction' => round($payrollRows->sum('absenceDeduction'), 2),
                'overtimeAmount' => round($payrollRows->sum('overtimeAmount'), 2),
                'totalSalary' => round($payrollRows->sum('totalSalary'), 2),
                'totalBalance' => round($payrollRows->sum('totalBalance'), 2),
                'paidByCash' => round($payrollRows->sum('paidByCash'), 2),
                'balance' => round($payrollRows->sum('balance'), 2),
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
            'absenceDeductionSettings' => AppSetting::absenceDeductionSettings(),
        ]);
    }

    public function reportPrint(Request $request): View
    {
        $selectedMonth = $request->date('month')?->format('Y-m') ?? Carbon::today()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $selectedType = $this->normalizeType($request->query('type'));
        $selectedEmployeeId = $this->normalizeEmployeeId($request->query('employee_id'), $selectedType);
        $payrollRows = $this->payrollRows(
            $month->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
            $month->toDateString(),
            $selectedType,
            $selectedEmployeeId,
        );

        return view('payroll.report', [
            'rows' => $payrollRows,
            'monthLabel' => $month->format('F Y'),
            'filterLabel' => $selectedEmployeeId
                ? 'Selected Employee'
                : ($selectedType ? (Employee::TYPES[$selectedType] ?? $selectedType) : 'All Employee Types'),
            'generatedAt' => now()->format('d/m/Y h:i A'),
            'totals' => [
                'employees' => $payrollRows->count(),
                'presentDays' => $payrollRows->sum('presentDays'),
                'absentDays' => $payrollRows->sum('absentDays'),
                'overtimeHours' => $payrollRows->sum('overtimeHours'),
                'basicSalary' => round($payrollRows->sum('basicSalary'), 2),
                'absenceDeduction' => round($payrollRows->sum('absenceDeduction'), 2),
                'overtimeAmount' => round($payrollRows->sum('overtimeAmount'), 2),
                'totalSalary' => round($payrollRows->sum('totalSalary'), 2),
                'bonusExtra' => round($payrollRows->sum('bonusExtra'), 2),
                'deduction' => round($payrollRows->sum('deduction'), 2),
                'paidByCash' => round($payrollRows->sum('paidByCash'), 2),
                'balance' => round($payrollRows->sum('balance'), 2),
            ],
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

    public function updateAbsenceRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'absence_deduction_enabled' => ['required', 'boolean'],
            'absence_deduction_apply_to' => ['required', Rule::in([AppSetting::ABSENCE_DEDUCTION_APPLY_FIXED_ONLY])],
            'type' => ['nullable', 'string'],
        ]);

        AppSetting::setValue('absence_deduction_enabled', $data['absence_deduction_enabled'] ? '1' : '0');
        AppSetting::setValue('absence_deduction_apply_to', $data['absence_deduction_apply_to']);

        return to_route('payroll.index', $request->only(['type']));
    }

    public function updateAdjustment(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'bonus_extra' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'previous_balance' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'previous_balance_overridden' => ['sometimes', 'boolean'],
            'deduction' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'paid_by_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        PayrollAdjustment::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'month' => Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()->toDateString(),
            ],
            [
                'bonus_extra' => $data['bonus_extra'],
                'previous_balance' => $data['previous_balance'],
                'previous_balance_overridden' => (bool) ($data['previous_balance_overridden'] ?? false),
                'deduction' => $data['deduction'],
                'paid_by_cash' => $data['paid_by_cash'],
                'remarks' => $data['remarks'],
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Payroll adjustment saved.']);
        }

        return to_route('payroll.report', $request->only(['type', 'employee_id', 'month']));
    }

    public function updateAdjustmentsBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'adjustments' => ['required', 'array', 'min:1'],
            'adjustments.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'adjustments.*.bonus_extra' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'adjustments.*.previous_balance' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'adjustments.*.previous_balance_overridden' => ['sometimes', 'boolean'],
            'adjustments.*.deduction' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'adjustments.*.paid_by_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'adjustments.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()->toDateString();

        DB::transaction(function () use ($data, $month) {
            collect($data['adjustments'])
                ->unique('employee_id')
                ->each(function (array $adjustment) use ($month) {
                    PayrollAdjustment::updateOrCreate(
                        [
                            'employee_id' => $adjustment['employee_id'],
                            'month' => $month,
                        ],
                        [
                            'bonus_extra' => $adjustment['bonus_extra'],
                            'previous_balance' => $adjustment['previous_balance'],
                            'previous_balance_overridden' => (bool) ($adjustment['previous_balance_overridden'] ?? false),
                            'deduction' => $adjustment['deduction'],
                            'paid_by_cash' => $adjustment['paid_by_cash'],
                            'remarks' => $adjustment['remarks'] ?? null,
                        ],
                    );
                });
        });

        return response()->json([
            'message' => count($data['adjustments']).' payroll adjustment(s) saved.',
        ]);
    }

    public function ledger(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'from_month' => ['required', 'date_format:Y-m'],
            'to_month' => ['required', 'date_format:Y-m'],
        ]);

        $fromMonth = Carbon::createFromFormat('Y-m', $data['from_month'])->startOfMonth();
        $toMonth = Carbon::createFromFormat('Y-m', $data['to_month'])->startOfMonth();

        if ($fromMonth->gt($toMonth)) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        $rows = collect(CarbonPeriod::create($fromMonth, '1 month', $toMonth))
            ->map(function (Carbon $month) use ($employee) {
                $row = $this->payrollRows(
                    $month->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                    $month->toDateString(),
                    null,
                    $employee->id,
                )->first();

                return array_merge($row ?? [], [
                    'month' => $month->format('Y-m'),
                    'monthLabel' => $month->format('F Y'),
                ]);
            })
            ->values();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'type' => $employee->type,
            ],
            'rows' => $rows,
        ]);
    }

    public function payslip(Request $request, Employee $employee): View
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $row = $this->payrollRows(
            $month->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
            $month->toDateString(),
            null,
            $employee->id,
        )->first();

        abort_unless($row, 404);

        return view('payroll.payslip', [
            'employee' => $employee,
            'employeeTypeLabel' => Employee::TYPES[$employee->type] ?? $employee->type,
            'monthLabel' => $month->format('F Y'),
            'periodLabel' => $month->format('d/m/Y').' - '.$month->copy()->endOfMonth()->format('d/m/Y'),
            'row' => $row,
            'generatedAt' => now()->format('d/m/Y h:i A'),
        ]);
    }

    public function bulkPayslips(Request $request): View
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'employee_ids' => ['required', 'string'],
        ]);

        $employeeIds = collect(explode(',', $data['employee_ids']))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        abort_if($employeeIds->isEmpty(), 404);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');

        $slips = $employeeIds
            ->map(function (int $employeeId) use ($employees, $month) {
                /** @var Employee|null $employee */
                $employee = $employees->get($employeeId);

                if (! $employee) {
                    return null;
                }

                $row = $this->payrollRows(
                    $month->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                    $month->toDateString(),
                    null,
                    $employee->id,
                )->first();

                if (! $row) {
                    return null;
                }

                return [
                    'employee' => $employee,
                    'employeeTypeLabel' => Employee::TYPES[$employee->type] ?? $employee->type,
                    'row' => $row,
                ];
            })
            ->filter()
            ->values();

        abort_if($slips->isEmpty(), 404);

        return view('payroll.payslips', [
            'slips' => $slips,
            'monthLabel' => $month->format('F Y'),
            'periodLabel' => $month->format('d/m/Y').' - '.$month->copy()->endOfMonth()->format('d/m/Y'),
            'generatedAt' => now()->format('d/m/Y h:i A'),
        ]);
    }

    public function payslipExport(Request $request, Employee $employee): StreamedResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $row = $this->payrollRows(
            $month->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
            $month->toDateString(),
            null,
            $employee->id,
        )->first();

        abort_unless($row, 404);

        return $this->csvDownload('payslip-'.$employee->id.'-'.$month->format('Y-m').'.csv', [
            ['Al Mohafiz - Payslip'],
            ['Month', $month->format('F Y')],
            ['Employee', $row['employeeName']],
            ['Profession', $row['employeeProfession']],
            ['Employee Type', Employee::TYPES[$employee->type] ?? $employee->type],
            [],
            ['Days', 'Absent', 'Per Day', 'Salary', 'Absent Deduction', 'OT Hrs', 'OT Salary', 'New Total', 'Bonus', 'Pr. Balance', 'Total Balance', 'Deduction', 'Paid Cash', 'Balance', 'Remarks'],
            [
                $row['presentDays'],
                $row['absentDays'],
                $row['dailySalary'],
                $row['basicSalary'],
                $row['absenceDeduction'],
                $row['overtimeHours'],
                $row['overtimeAmount'],
                $row['totalSalary'],
                $row['bonusExtra'],
                $row['previousBalance'],
                $row['totalBalance'],
                $row['deduction'],
                $row['paidByCash'],
                $row['balance'],
                $row['remarks'] ?: '',
            ],
        ]);
    }

    public function ledgerPrint(Request $request, Employee $employee): View
    {
        $data = $request->validate([
            'from_month' => ['required', 'date_format:Y-m'],
            'to_month' => ['required', 'date_format:Y-m'],
        ]);

        $fromMonth = Carbon::createFromFormat('Y-m', $data['from_month'])->startOfMonth();
        $toMonth = Carbon::createFromFormat('Y-m', $data['to_month'])->startOfMonth();

        if ($fromMonth->gt($toMonth)) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        $rows = collect(CarbonPeriod::create($fromMonth, '1 month', $toMonth))
            ->map(function (Carbon $month) use ($employee) {
                $row = $this->payrollRows(
                    $month->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                    $month->toDateString(),
                    null,
                    $employee->id,
                )->first();

                return array_merge($row ?? [], [
                    'month' => $month->format('Y-m'),
                    'monthLabel' => $month->format('F Y'),
                ]);
            })
            ->values();

        return view('payroll.ledger', [
            'employee' => $employee,
            'employeeTypeLabel' => Employee::TYPES[$employee->type] ?? $employee->type,
            'fromMonthLabel' => $fromMonth->format('F Y'),
            'toMonthLabel' => $toMonth->format('F Y'),
            'rows' => $rows,
            'totals' => [
                'presentDays' => $rows->sum('presentDays'),
                'absentDays' => $rows->sum('absentDays'),
                'overtimeHours' => $rows->sum('overtimeHours'),
                'basicSalary' => round($rows->sum('basicSalary'), 2),
                'absenceDeduction' => round($rows->sum('absenceDeduction'), 2),
                'overtimeAmount' => round($rows->sum('overtimeAmount'), 2),
                'totalSalary' => round($rows->sum('totalSalary'), 2),
                'bonusExtra' => round($rows->sum('bonusExtra'), 2),
                'deduction' => round($rows->sum('deduction'), 2),
                'paidByCash' => round($rows->sum('paidByCash'), 2),
                'endingBalance' => round((float) ($rows->last()['balance'] ?? 0), 2),
            ],
            'generatedAt' => now()->format('d/m/Y h:i A'),
        ]);
    }

    public function ledgerExport(Request $request, Employee $employee): StreamedResponse
    {
        $data = $request->validate([
            'from_month' => ['required', 'date_format:Y-m'],
            'to_month' => ['required', 'date_format:Y-m'],
        ]);

        $fromMonth = Carbon::createFromFormat('Y-m', $data['from_month'])->startOfMonth();
        $toMonth = Carbon::createFromFormat('Y-m', $data['to_month'])->startOfMonth();

        if ($fromMonth->gt($toMonth)) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        $rows = collect(CarbonPeriod::create($fromMonth, '1 month', $toMonth))
            ->map(function (Carbon $month) use ($employee) {
                $row = $this->payrollRows(
                    $month->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                    $month->toDateString(),
                    null,
                    $employee->id,
                )->first();

                return array_merge($row ?? [], [
                    'monthLabel' => $month->format('F Y'),
                ]);
            })
            ->values();

        $csvRows = [
            ['Al Mohafiz - Employee Ledger'],
            ['Employee', $employee->name],
            ['Profession', $employee->profession],
            ['Employee Type', Employee::TYPES[$employee->type] ?? $employee->type],
            ['Period', $fromMonth->format('F Y').' to '.$toMonth->format('F Y')],
            [],
            ['Month', 'Days', 'Absent', 'Per Day', 'Salary', 'Absent Deduction', 'OT Hrs', 'OT Salary', 'New Total', 'Bonus', 'Pr. Balance', 'Total Balance', 'Deduction', 'Paid Cash', 'Balance', 'Remarks'],
        ];

        foreach ($rows as $row) {
            $csvRows[] = [
                $row['monthLabel'],
                $row['presentDays'],
                $row['absentDays'],
                $row['dailySalary'],
                $row['basicSalary'],
                $row['absenceDeduction'],
                $row['overtimeHours'],
                $row['overtimeAmount'],
                $row['totalSalary'],
                $row['bonusExtra'],
                $row['previousBalance'],
                $row['totalBalance'],
                $row['deduction'],
                $row['paidByCash'],
                $row['balance'],
                $row['remarks'] ?: '',
            ];
        }

        $csvRows[] = [];
        $csvRows[] = [
            'Total',
            $rows->sum('presentDays'),
            $rows->sum('absentDays'),
            '',
            round($rows->sum('basicSalary'), 2),
            round($rows->sum('absenceDeduction'), 2),
            $rows->sum('overtimeHours'),
            round($rows->sum('overtimeAmount'), 2),
            round($rows->sum('totalSalary'), 2),
            round($rows->sum('bonusExtra'), 2),
            '',
            '',
            round($rows->sum('deduction'), 2),
            round($rows->sum('paidByCash'), 2),
            round((float) ($rows->last()['balance'] ?? 0), 2),
            '',
        ];

        return $this->csvDownload('ledger-'.$employee->id.'-'.$fromMonth->format('Y-m').'-to-'.$toMonth->format('Y-m').'.csv', $csvRows);
    }

    private function payrollRows(string $monthStart, string $monthEnd, string $month, ?string $type, ?int $employeeId): Collection
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

        $adjustments = PayrollAdjustment::query()
            ->whereDate('month', $month)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $leaveDeductions = $this->leaveDeductionsForMonth($month, $employees->pluck('id'));

        return $employees->map(function (Employee $employee) use ($records, $adjustments, $leaveDeductions, $month) {
            $autoPreviousBalance = $this->carryForwardBalance($employee, Carbon::parse($month)->subMonthNoOverflow()->startOfMonth());

            return $this->payrollRowForMonth(
                $employee,
                $records->get($employee->id, collect()),
                $adjustments->get($employee->id),
                $autoPreviousBalance,
                $leaveDeductions->get($employee->id, collect()),
            );
        })->values();
    }

    private function payrollRowForMonth(Employee $employee, Collection $employeeRecords, ?PayrollAdjustment $adjustment, float $autoPreviousBalance, ?Collection $leaveDeductions = null): array
    {
        $setting = $employee->payrollSetting;
        $absenceSettings = AppSetting::absenceDeductionSettings();
        $realPresentDays = $employeeRecords->where('status', AttendanceRecord::STATUS_PRESENT)->count();
        $realAbsentDays = $employeeRecords->where('status', AttendanceRecord::STATUS_ABSENT)->count();
        $leaveDeductions ??= collect();
        $leaveDeductedAsAbsentDays = (int) $leaveDeductions->sum('days');
        $rawAbsentDays = $realAbsentDays + $leaveDeductedAsAbsentDays;
        $leaveDays = $employeeRecords->where('status', AttendanceRecord::STATUS_LEAVE)->count();
        $overtimeHours = (int) $employeeRecords->sum(fn ($record) => (int) ($record->overtime_hours ?? 0));
        $dailySalary = (float) ($setting?->daily_salary ?? 0);
        $salaryRule = $setting?->salary_rule ?? EmployeePayrollSetting::RULE_PRESENT_DAYS;
        $standardHours = max(1, (int) ($setting?->standard_hours_per_day ?? 8));
        $hourlyRate = $dailySalary / $standardHours;
        $presentDays = $salaryRule === EmployeePayrollSetting::RULE_FIXED_30_DAYS
            ? min($realPresentDays, 30)
            : $realPresentDays;
        $absentDays = $salaryRule === EmployeePayrollSetting::RULE_FIXED_30_DAYS
            ? min($rawAbsentDays, max(0, 30 - $presentDays))
            : $rawAbsentDays;
        $effectiveRealAbsentDays = min($realAbsentDays, $absentDays);
        $effectiveLeaveDeductedAsAbsentDays = max(0, $absentDays - $effectiveRealAbsentDays);
        $payableDays = $salaryRule === EmployeePayrollSetting::RULE_FIXED_30_DAYS ? 30 : $presentDays;
        $basicSalary = $dailySalary * $payableDays;
        $absenceDeduction = $absenceSettings['enabled'] && $salaryRule === EmployeePayrollSetting::RULE_FIXED_30_DAYS
            ? $dailySalary * $absentDays
            : 0;
        $overtimeAmount = $setting?->is_overtime_enabled === false ? 0 : $overtimeHours * $hourlyRate;
        $totalSalary = $basicSalary + $overtimeAmount;
        $bonusExtra = (float) ($adjustment?->bonus_extra ?? 0);
        $previousBalanceOverridden = (bool) ($adjustment?->previous_balance_overridden ?? false);
        $previousBalance = $previousBalanceOverridden ? (float) $adjustment?->previous_balance : $autoPreviousBalance;
        $deduction = (float) ($adjustment?->deduction ?? 0);
        $paidByCash = (float) ($adjustment?->paid_by_cash ?? 0);
        $totalBalance = $totalSalary + $bonusExtra + $previousBalance;
        $balance = $totalBalance - $absenceDeduction - $deduction - $paidByCash;
        $remainingLeaveDeductionDays = $effectiveLeaveDeductedAsAbsentDays;
        $leaveDeductionRemarks = $leaveDeductions
            ->map(function (array $deduction) use (&$remainingLeaveDeductionDays) {
                if ($remainingLeaveDeductionDays <= 0) {
                    return null;
                }

                $days = min((int) $deduction['days'], $remainingLeaveDeductionDays);
                $remainingLeaveDeductionDays -= $days;

                return $days.' leave day'.($days === 1 ? '' : 's').' deducted as absent - '.$deduction['reason'];
            })
            ->filter()
            ->values();
        $remarks = $leaveDeductionRemarks
            ->prepend($adjustment?->remarks)
            ->filter()
            ->implode(' | ');

        return [
            'employeeId' => $employee->id,
            'employeeName' => $employee->name,
            'employeeProfession' => $employee->profession,
            'employeeType' => $employee->type,
            'dailySalary' => round($dailySalary, 2),
            'salaryRule' => $salaryRule,
            'standardHoursPerDay' => $standardHours,
            'presentDays' => $presentDays,
            'realPresentDays' => $realPresentDays,
            'realAbsentDays' => $realAbsentDays,
            'leaveDeductedAsAbsentDays' => $effectiveLeaveDeductedAsAbsentDays,
            'absentDays' => $absentDays,
            'leaveDays' => $leaveDays,
            'overtimeHours' => $overtimeHours,
            'hourlyRate' => round($hourlyRate, 2),
            'basicSalary' => round($basicSalary, 2),
            'absenceDeduction' => round($absenceDeduction, 2),
            'overtimeAmount' => round($overtimeAmount, 2),
            'totalSalary' => round($totalSalary, 2),
            'bonusExtra' => round($bonusExtra, 2),
            'previousBalance' => round($previousBalance, 2),
            'autoPreviousBalance' => round($autoPreviousBalance, 2),
            'previousBalanceOverridden' => $previousBalanceOverridden,
            'totalBalance' => round($totalBalance, 2),
            'deduction' => round($deduction, 2),
            'paidByCash' => round($paidByCash, 2),
            'balance' => round($balance, 2),
            'remarks' => $remarks,
            'projectCount' => $employeeRecords->where('status', AttendanceRecord::STATUS_PRESENT)->pluck('project_name')->filter()->unique()->count(),
        ];
    }

    private function leaveDeductionsForMonth(string $month, Collection $employeeIds): Collection
    {
        $longLeaves = EmployeeLeave::query()
            ->with('employee:id,name')
            ->whereIn('employee_id', $employeeIds)
            ->where('payroll_deduction_status', EmployeeLeave::PAYROLL_DEDUCTION_APPLIED)
            ->whereDate('payroll_deduction_month', $month)
            ->get()
            ->map(fn (EmployeeLeave $leave) => [
                'employee_id' => $leave->employee_id,
                'days' => (int) $leave->payroll_deduct_days,
                'reason' => $leave->reason ?: 'Leave',
            ]);

        $dailyLeaves = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', AttendanceRecord::STATUS_LEAVE)
            ->where('payroll_deduction_status', AttendanceRecord::PAYROLL_DEDUCTION_APPLIED)
            ->whereDate('payroll_deduction_month', $month)
            ->get(['employee_id', 'payroll_deduct_days', 'leave_reason'])
            ->map(fn (AttendanceRecord $record) => [
                'employee_id' => $record->employee_id,
                'days' => (int) $record->payroll_deduct_days,
                'reason' => $record->leave_reason ?: 'Daily leave',
            ]);

        return $longLeaves
            ->merge($dailyLeaves)
            ->filter(fn (array $deduction) => $deduction['days'] > 0)
            ->groupBy('employee_id');
    }

    private function carryForwardBalance(Employee $employee, Carbon $targetMonth): float
    {
        $firstMonth = $this->firstPayrollMonth($employee);

        if (! $firstMonth || $targetMonth->lt($firstMonth)) {
            return 0;
        }

        $balance = 0.0;

        foreach (CarbonPeriod::create($firstMonth, '1 month', $targetMonth) as $month) {
            $monthStart = $month->copy()->startOfMonth()->toDateString();
            $monthEnd = $month->copy()->endOfMonth()->toDateString();
            $adjustment = PayrollAdjustment::query()
                ->where('employee_id', $employee->id)
                ->whereDate('month', $monthStart)
                ->first();

            $records = AttendanceRecord::query()
                ->leftJoin('projects', 'attendance_records.project_id', '=', 'projects.id')
                ->where('attendance_records.employee_id', $employee->id)
                ->whereBetween('attendance_records.attendance_date', [$monthStart, $monthEnd])
                ->get([
                    'attendance_records.employee_id',
                    'attendance_records.status',
                    'attendance_records.overtime_hours',
                    'projects.name as project_name',
                ]);

            $leaveDeductions = $this->leaveDeductionsForMonth($monthStart, collect([$employee->id]))->get($employee->id, collect());

            $balance = $this->payrollRowForMonth($employee, $records, $adjustment, $balance, $leaveDeductions)['balance'];
        }

        return $balance;
    }

    private function firstPayrollMonth(Employee $employee): ?Carbon
    {
        $firstAttendanceDate = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->min('attendance_date');

        $firstAdjustmentMonth = PayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->min('month');

        $firstLongLeaveDeductionMonth = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->where('payroll_deduction_status', EmployeeLeave::PAYROLL_DEDUCTION_APPLIED)
            ->min('payroll_deduction_month');

        $firstDailyLeaveDeductionMonth = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceRecord::STATUS_LEAVE)
            ->where('payroll_deduction_status', AttendanceRecord::PAYROLL_DEDUCTION_APPLIED)
            ->min('payroll_deduction_month');

        $dates = collect([
            $firstAttendanceDate,
            $firstAdjustmentMonth,
            $firstLongLeaveDeductionMonth,
            $firstDailyLeaveDeductionMonth,
        ])->filter();

        if ($dates->isEmpty()) {
            return null;
        }

        return Carbon::parse($dates->min())->startOfMonth();
    }

    private function csvDownload(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
