<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AttendanceRecord;
use App\Models\EmployeeExpense;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Services\Projects\ProjectEmployeeHistoryExporter;
use App\Services\Projects\ProjectEmployeeHistoryService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectEmployeeHistoryService $history)
    {
    }

    public function overview(Request $request): Response
    {
        // Read once here and passed down, never memoised on the controller:
        // the router keeps one controller instance per route, so a cached
        // value would outlive the request that saved the new setting.
        $overhead = AppSetting::projectOverheadSettings();
        $selectedType = $this->normalizeType($request->query('type'));
        $selectedProjectId = $this->normalizeProjectId($request->query('project_id'), $selectedType);

        $projects = Project::query()
            ->when($selectedType, fn ($query) => $query->where('type', $selectedType))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $costMaps = $this->projectCostMaps($projects->pluck('id'));

        $overviewRows = $projects
            ->when($selectedProjectId, fn (Collection $items) => $items->where('id', $selectedProjectId))
            ->map(fn (Project $project) => $this->projectOverviewRow($project, $costMaps, $overhead))
            ->values();

        return Inertia::render('Projects/Overview', [
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'projectCode' => $project->project_code,
                'name' => $project->name,
                'status' => $project->status,
                'type' => $project->type,
                'label' => $project->name.' - '.Project::TYPES[$project->type],
            ])->values(),
            'overviewRows' => $overviewRows,
            'summary' => [
                'projectCount' => $overviewRows->count(),
                'activeProjects' => $overviewRows->where('status', 'ongoing')->count(),
                'labourCount' => $overviewRows->sum('labourCount'),
                'workedDays' => $overviewRows->sum('workedDays'),
                'overtimeHours' => $overviewRows->sum('overtimeHours'),
                'labourCost' => round($overviewRows->sum('labourCost'), 2),
                'overheadCost' => round($overviewRows->sum('overheadCost'), 2),
                'purchaseCost' => round($overviewRows->sum('purchaseCost'), 2),
                'expenseCost' => round($overviewRows->sum('expenseCost'), 2),
                'totalCost' => round($overviewRows->sum('totalCost'), 2),
                'contractValue' => round($overviewRows->sum('contractValue'), 2),
                'costBudget' => round($overviewRows->sum('costBudget'), 2),
            ],
            'selectedProjectDetails' => $selectedProjectId ? $this->selectedProjectDetails($selectedProjectId) : null,
            'filters' => [
                'type' => $selectedType ?? 'all',
                'projectId' => $selectedProjectId ? (string) $selectedProjectId : 'all',
            ],
            'typeOptions' => $this->typeOptions(),
            'projectTypes' => Project::TYPES,
            'statuses' => Project::STATUSES,
            'overheadSettings' => $overhead,
        ]);
    }

    public function updateOverheadSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
        ]);

        AppSetting::setValue('project_overhead_enabled', $data['enabled'] ? '1' : '0');
        AppSetting::setValue('project_overhead_multiplier', (string) $data['multiplier']);

        return back()->with('success', 'Overhead setting saved.');
    }

    public function index(?string $type = null): Response
    {
        $type ??= 'contracting';
        abort_unless(array_key_exists($type, Project::TYPES), 404);

        $projects = Project::query()
            ->where('type', $type)
            ->latest()
            ->get();
        $costMaps = $this->projectCostMaps($projects->pluck('id'));
        $overhead = AppSetting::projectOverheadSettings();

        return Inertia::render('Projects/Index', [
            'projects' => $projects->map(fn (Project $project) => $this->projectOverviewRow($project, $costMaps, $overhead))->values(),
            'statuses' => Project::STATUSES,
            'projectType' => $type,
            'projectTypeLabel' => Project::TYPES[$type],
        ]);
    }

    public function employeeHistory(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->history->build($project, $data['from'] ?? null, $data['to'] ?? null)
        );
    }

    /**
     * Formatted .xlsx of the project's labour history.
     */
    public function employeeHistoryExport(Request $request, Project $project, ProjectEmployeeHistoryExporter $exporter): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $history = $this->history->build($project, $data['from'] ?? null, $data['to'] ?? null);

        return $exporter->download($history);
    }

    /**
     * Print view for the same history. Saved as PDF from the browser's print
     * dialog, matching how payslips and timesheets are produced.
     */
    public function employeeHistoryPrint(Request $request, Project $project): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('projects.employee-history-print', [
            'history' => $this->history->build($project, $data['from'] ?? null, $data['to'] ?? null),
            'generatedAt' => now()->format('d/m/Y h:i A'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        Project::create($data);

        return to_route('projects.type.index', $data['type']);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request, $project);

        $project->update($data);

        return to_route('projects.type.index', $data['type']);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $type = $project->type;

        $hasAttendance = AttendanceRecord::query()
            ->where('project_id', $project->id)
            ->orWhere('overtime_project_id', $project->id)
            ->exists();

        if (
            $hasAttendance
            || $project->purchaseBills()->exists()
            || $project->expenses()->exists()
            || $project->equipment()->exists()
            || \App\Models\ContractingDutyAssignment::query()
                ->where('project_id', $project->id)
                ->orWhere('overtime_project_id', $project->id)
                ->exists()
        ) {
            return back()->withErrors([
                'project' => 'This project has attendance, purchase, expense, equipment, or duty records. Mark it completed instead of deleting it.',
            ]);
        }

        $project->delete();

        return to_route('projects.type.index', $type);
    }

    private function validatedData(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'project_code' => ['nullable', 'string', 'max:50', Rule::unique('projects', 'project_code')->ignore($project)],
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'project_manager' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'type' => ['required', Rule::in(array_keys(Project::TYPES))],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'cost_budget' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($data['status'] === 'completed') {
            $data['progress_percentage'] = 100;
        }

        return $data;
    }

    private function projectOverviewRow(Project $project, array $costMaps, array $overhead): array
    {
        $records = AttendanceRecord::query()
            ->with('employee.payrollSetting')
            ->where('status', AttendanceRecord::STATUS_PRESENT)
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhere('overtime_project_id', $project->id);
            })
            ->orderBy('attendance_date')
            ->get();

        $firstWorkDate = $records->first()?->attendance_date;
        $lastWorkDate = $records->last()?->attendance_date;
        $startDate = $project->start_date ?? $firstWorkDate ?? $project->created_at;
        $elapsedEndDate = $project->status === 'completed'
            ? ($project->expected_end_date ?? $lastWorkDate ?? Carbon::today())
            : Carbon::today();

        $basicCost = 0.0;
        $overtimeCost = 0.0;
        $missingPayrollSettings = collect();

        foreach ($records as $record) {
            $employee = $record->employee;
            $setting = $employee?->payrollSetting;
            $dailySalary = (float) ($setting?->daily_salary ?? 0);
            $standardHours = max(1, (int) ($setting?->standard_hours_per_day ?? 8));
            $effectiveOvertimeProjectId = $record->overtime_project_id ?: $record->project_id;
            $overtimeHours = (int) $effectiveOvertimeProjectId === (int) $project->id ? (int) ($record->overtime_hours ?? 0) : 0;

            if (! $setting) {
                $missingPayrollSettings->push($employee?->name);
            }

            if ((int) $record->project_id === (int) $project->id) {
                $basicCost += $dailySalary * (float) ($record->attendance_fraction ?? AttendanceRecord::FULL_DAY_FRACTION);
            }

            $overtimeCost += $setting?->is_overtime_enabled === false ? 0 : $overtimeHours * ($dailySalary / $standardHours);
        }

        $labourIds = $records->pluck('employee_id')->unique();
        $workedDates = $records->pluck('attendance_date')->map(fn ($date) => Carbon::parse($date)->toDateString())->unique();
        // The loaded figure replaces basic salary; it does not sit beside it.
        // Basic 1,000 at 2x is a cost of 2,000, not 3,000.
        $costedBasicCost = $overhead['enabled'] ? $basicCost * $overhead['multiplier'] : $basicCost;
        $overheadCost = round($costedBasicCost - $basicCost, 2);
        $labourCost = round($costedBasicCost + $overtimeCost, 2);
        $purchaseCost = (float) ($costMaps['purchases'][$project->id] ?? 0);
        $purchaseVat = (float) ($costMaps['purchaseVat'][$project->id] ?? 0);
        $supplierPaid = (float) ($costMaps['supplierPaid'][$project->id] ?? 0);
        $expenseCost = (float) ($costMaps['expenses'][$project->id] ?? 0);
        $actualCost = round($labourCost + $purchaseCost + $expenseCost, 2);
        $contractValue = $project->contract_value !== null ? (float) $project->contract_value : null;
        $costBudget = $project->cost_budget !== null ? (float) $project->cost_budget : null;
        $budgetRemaining = $costBudget !== null ? round($costBudget - $actualCost, 2) : null;
        $expectedProfit = $contractValue !== null ? round($contractValue - $actualCost, 2) : null;
        $budgetUsedPercent = $costBudget && $costBudget > 0 ? round(($actualCost / $costBudget) * 100, 1) : null;
        $profitMarginPercent = $contractValue && $contractValue > 0 ? round(($expectedProfit / $contractValue) * 100, 1) : null;
        [$healthStatus, $healthLabel] = $this->projectHealth($project, $budgetUsedPercent, $contractValue, $actualCost);

        return [
            'id' => $project->id,
            'projectCode' => $project->project_code,
            'name' => $project->name,
            'clientName' => $project->client_name,
            'location' => $project->location,
            'projectManager' => $project->project_manager,
            'status' => $project->status,
            'type' => $project->type,
            'typeLabel' => Project::TYPES[$project->type],
            'startDate' => $project->start_date?->format('d/m/Y'),
            'startDateValue' => $project->start_date?->toDateString(),
            'expectedEndDate' => $project->expected_end_date?->format('d/m/Y'),
            'expectedEndDateValue' => $project->expected_end_date?->toDateString(),
            'contractValue' => $contractValue,
            'costBudget' => $costBudget,
            'progressPercentage' => (int) $project->progress_percentage,
            'description' => $project->description,
            'healthStatus' => $healthStatus,
            'healthLabel' => $healthLabel,
            'firstWorkDate' => $firstWorkDate?->format('d/m/Y'),
            'lastWorkDate' => $lastWorkDate?->format('d/m/Y'),
            'daysSinceStart' => $startDate ? Carbon::parse($startDate)->startOfDay()->diffInDays(Carbon::parse($elapsedEndDate)->startOfDay()) + 1 : 0,
            'workedDays' => $workedDates->count(),
            'labourCount' => $labourIds->count(),
            'labourEntries' => $records->count(),
            'overtimeHours' => (int) $records->sum(fn (AttendanceRecord $record) => (int) ((int) ($record->overtime_project_id ?: $record->project_id) === (int) $project->id ? ($record->overtime_hours ?? 0) : 0)),
            'basicCost' => round($basicCost, 2),
            'overtimeCost' => round($overtimeCost, 2),
            'labourCost' => $labourCost,
            'overheadCost' => $overheadCost,
            'purchaseCost' => round($purchaseCost, 2),
            'purchaseVat' => round($purchaseVat, 2),
            'supplierPaid' => round($supplierPaid, 2),
            'supplierOutstanding' => round(max(0, $purchaseCost - $supplierPaid), 2),
            'expenseCost' => round($expenseCost, 2),
            'totalCost' => $actualCost,
            'budgetRemaining' => $budgetRemaining,
            'expectedProfit' => $expectedProfit,
            'budgetUsedPercent' => $budgetUsedPercent,
            'profitMarginPercent' => $profitMarginPercent,
            'purchaseBillCount' => (int) ($costMaps['purchaseCount'][$project->id] ?? 0),
            'approvedExpenseCount' => (int) ($costMaps['expenseCount'][$project->id] ?? 0),
            'equipmentCount' => (int) ($costMaps['equipmentCount'][$project->id] ?? 0),
            'missingPayrollSettings' => $missingPayrollSettings->filter()->unique()->values(),
        ];
    }

    private function projectCostMaps(Collection $projectIds): array
    {
        if ($projectIds->isEmpty()) {
            return [
                'purchases' => [], 'purchaseVat' => [], 'purchaseCount' => [],
                'supplierPaid' => [], 'expenses' => [], 'expenseCount' => [], 'equipmentCount' => [],
            ];
        }

        $purchases = PurchaseBill::query()
            ->whereIn('project_id', $projectIds)
            ->selectRaw('project_id, count(*) as record_count, coalesce(sum(total_amount), 0) as total_cost, coalesce(sum(vat_amount), 0) as vat_total')
            ->groupBy('project_id')->get()->keyBy('project_id');

        $supplierPaid = SupplierPayment::query()
            ->join('purchase_bills', 'purchase_bills.id', '=', 'supplier_payments.purchase_bill_id')
            ->whereIn('purchase_bills.project_id', $projectIds)
            ->selectRaw('purchase_bills.project_id, coalesce(sum(supplier_payments.amount), 0) as paid_total')
            ->groupBy('purchase_bills.project_id')->pluck('paid_total', 'project_id')->all();

        $expenses = EmployeeExpense::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', EmployeeExpense::STATUS_APPROVED)
            ->selectRaw('project_id, count(*) as record_count, coalesce(sum(amount), 0) as total_cost')
            ->groupBy('project_id')->get()->keyBy('project_id');

        $equipmentCounts = Equipment::query()
            ->whereIn('assigned_project_id', $projectIds)
            ->selectRaw('assigned_project_id, count(*) as record_count')
            ->groupBy('assigned_project_id')
            ->pluck('record_count', 'assigned_project_id')
            ->all();

        return [
            'purchases' => $purchases->map(fn ($row) => (float) $row->total_cost)->all(),
            'purchaseVat' => $purchases->map(fn ($row) => (float) $row->vat_total)->all(),
            'purchaseCount' => $purchases->map(fn ($row) => (int) $row->record_count)->all(),
            'supplierPaid' => array_map('floatval', $supplierPaid),
            'expenses' => $expenses->map(fn ($row) => (float) $row->total_cost)->all(),
            'expenseCount' => $expenses->map(fn ($row) => (int) $row->record_count)->all(),
            'equipmentCount' => array_map('intval', $equipmentCounts),
        ];
    }

    private function projectHealth(Project $project, ?float $budgetUsedPercent, ?float $contractValue = null, float $actualCost = 0): array
    {
        // Checked before anything else, completion included: a project that
        // spent more than it was sold for is a loss whether or not it is
        // finished, and reading "Completed" over a loss hides the one fact
        // that matters. Most projects carry no cost budget, so the budget
        // rules below never fire for them.
        if ($contractValue !== null && $contractValue > 0 && $actualCost > $contractValue) {
            return ['loss', 'Loss'];
        }

        if ($project->status === 'completed') {
            return ['completed', 'Completed'];
        }

        if ($project->expected_end_date && $project->expected_end_date->isPast() && $project->progress_percentage < 100) {
            return ['delayed', 'Delayed'];
        }

        if ($budgetUsedPercent !== null && $budgetUsedPercent > 100) {
            return ['over_budget', 'Over Budget'];
        }

        if ($budgetUsedPercent !== null && $budgetUsedPercent >= 85 && $project->progress_percentage < 85) {
            return ['at_risk', 'At Risk'];
        }

        return ['on_track', 'On Track'];
    }

    private function selectedProjectDetails(int $projectId): array
    {
        $bills = PurchaseBill::query()
            ->with('supplier:id,name')
            ->withSum('payments as paid_amount', 'amount')
            ->where('project_id', $projectId)
            ->latest('bill_date')
            ->limit(10)
            ->get()
            ->map(fn (PurchaseBill $bill) => [
                'id' => $bill->id,
                'billNumber' => $bill->bill_number,
                'supplierName' => $bill->supplier?->name,
                'date' => $bill->bill_date->format('d/m/Y'),
                'total' => (float) $bill->total_amount,
                'paid' => (float) ($bill->paid_amount ?? 0),
                'balance' => round(max(0, (float) $bill->total_amount - (float) ($bill->paid_amount ?? 0)), 2),
                'status' => $bill->status,
            ]);

        $expenses = EmployeeExpense::query()
            ->with('submitter:id,name')
            ->where('project_id', $projectId)
            ->where('status', EmployeeExpense::STATUS_APPROVED)
            ->latest('expense_date')
            ->limit(10)
            ->get()
            ->map(fn (EmployeeExpense $expense) => [
                'id' => $expense->id,
                'date' => $expense->expense_date->format('d/m/Y'),
                'purpose' => $expense->purpose,
                'amount' => (float) $expense->amount,
                'submittedBy' => $expense->submitter?->name,
            ]);

        $equipment = Equipment::query()
            ->where('assigned_project_id', $projectId)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'asset_code', 'status'])
            ->map(fn (Equipment $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'assetCode' => $item->asset_code,
                'status' => $item->status,
            ]);

        return [
            'purchaseBills' => $bills,
            'approvedExpenses' => $expenses,
            'equipment' => $equipment,
        ];
    }

    private function normalizeType(mixed $type): ?string
    {
        if (! is_string($type) || $type === '' || $type === 'all') {
            return null;
        }

        abort_unless(array_key_exists($type, Project::TYPES), 404);

        return $type;
    }

    private function normalizeProjectId(mixed $projectId, ?string $type): ?int
    {
        if (! is_numeric($projectId)) {
            return null;
        }

        $projectId = (int) $projectId;
        $rule = Rule::exists('projects', 'id');

        if ($type) {
            $rule->where('type', $type);
        }

        validator(
            ['project_id' => $projectId],
            ['project_id' => ['required', 'integer', $rule]]
        )->validate();

        return $projectId;
    }

    private function typeOptions(): Collection
    {
        return collect(['all' => 'All Project Categories'])->merge(Project::TYPES)->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();
    }
}
