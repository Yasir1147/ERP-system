<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeExpense;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 10;
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $from = $request->query('from', Carbon::today()->startOfMonth()->toDateString());
        $to = $request->query('to', Carbon::today()->toDateString());
        $sort = (string) $request->query('sort', 'expense_date');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $expenses = $this->expenseRows($search, $status, $from, $to, $perPage, $sort, $direction);
        $summary = $this->summary($search, $status, $from, $to);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses->items(),
            'pagination' => [
                'currentPage' => $expenses->currentPage(),
                'lastPage' => $expenses->lastPage(),
                'perPage' => $expenses->perPage(),
                'total' => $expenses->total(),
                'from' => $expenses->firstItem(),
                'to' => $expenses->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'perPage' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => $summary,
            'employeeTypes' => Employee::TYPES,
            'purposes' => EmployeeExpense::PURPOSES,
            'statuses' => EmployeeExpense::STATUSES,
        ]);
    }

    public function create(): Response
    {
        $type = $this->normalizeType(request()->query('type', 'rope_access'));
        abort_unless($type === 'rope_access', 404);

        $user = request()->user();

        if ($user?->isAttendanceUser()) {
            abort_unless($user->canAccessEmployeeType($type), 403);
        }

        return Inertia::render('Expenses/Create', [
            'projects' => $this->projectOptions($type),
            'employeeType' => $type,
            'employeeTypeLabel' => Employee::TYPES[$type],
            'purposes' => EmployeeExpense::PURPOSES,
            'submitUrl' => '/expenses',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->normalizeType($request->input('type', 'rope_access'));
        abort_unless($type === 'rope_access', 404);

        $user = $request->user();

        if ($user?->isAttendanceUser()) {
            abort_unless($user->canAccessEmployeeType($type), 403);
        }

        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Employee::TYPES))],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('type', $type),
            ],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'receipt' => ['nullable', 'image', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $receiptPath = $request->file('receipt')?->store('expense-receipts', 'public');

        EmployeeExpense::create([
            'submitted_by' => $user?->id,
            'project_id' => $data['project_id'] ?? null,
            'employee_type' => $type,
            'expense_date' => $data['expense_date'],
            'purpose' => $data['purpose'],
            'amount' => $data['amount'],
            'receipt_path' => $receiptPath,
            'status' => EmployeeExpense::STATUS_PENDING,
            'note' => $data['note'] ?? null,
        ]);

        return $user?->role === User::ROLE_ADMIN
            ? to_route('expenses.index')->with('success', 'Expense bill created.')
            : to_route('expenses.create', ['type' => $type])->with('success', 'Expense bill submitted for admin review.');
    }

    public function approve(Request $request, EmployeeExpense $employeeExpense): RedirectResponse
    {
        abort_unless($employeeExpense->status === EmployeeExpense::STATUS_PENDING, 422);

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $employeeExpense->forceFill([
            'status' => EmployeeExpense::STATUS_APPROVED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_note' => $data['admin_note'] ?? null,
        ])->save();

        return to_route('expenses.index')->with('success', 'Expense approved.');
    }

    public function reject(Request $request, EmployeeExpense $employeeExpense): RedirectResponse
    {
        abort_unless($employeeExpense->status === EmployeeExpense::STATUS_PENDING, 422);

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $employeeExpense->forceFill([
            'status' => EmployeeExpense::STATUS_REJECTED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_note' => $data['admin_note'] ?? null,
        ])->save();

        return to_route('expenses.index')->with('success', 'Expense rejected.');
    }

    public function destroy(EmployeeExpense $employeeExpense): RedirectResponse
    {
        if ($employeeExpense->receipt_path) {
            Storage::disk('public')->delete($employeeExpense->receipt_path);
        }

        $employeeExpense->delete();
    
        return to_route('expenses.index')->with('success', 'Expense bill deleted.');
    }

    private function projectOptions(string $type)
    {
        return Project::query()
            ->where('type', $type)
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'type'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'type' => $project->type,
            ]);
    }

    private function normalizeType(mixed $type): string
    {
        $type = is_string($type) ? str_replace('-', '_', $type) : 'rope_access';

        abort_unless(array_key_exists($type, Employee::TYPES), 404);

        return $type;
    }

    private function expenseRows(string $search, string $status, string $from, string $to, int $perPage, string $sort, string $direction)
    {
        $sortColumns = [
            'expense_date' => 'employee_expenses.expense_date',
            'purpose' => 'employee_expenses.purpose',
            'amount' => 'employee_expenses.amount',
            'status' => 'employee_expenses.status',
            'project' => 'sort_projects.name',
            'submitted_by' => 'sort_submitters.name',
        ];
        $sortColumn = $sortColumns[$sort] ?? $sortColumns['expense_date'];

        return $this->baseQuery($search, $status, $from, $to)
            ->select('employee_expenses.*')
            ->leftJoin('projects as sort_projects', 'sort_projects.id', '=', 'employee_expenses.project_id')
            ->leftJoin('users as sort_submitters', 'sort_submitters.id', '=', 'employee_expenses.submitted_by')
            ->with(['project:id,name,status,type', 'submitter:id,name,role', 'reviewer:id,name,role'])
            ->orderBy($sortColumn, $direction)
            ->orderBy('employee_expenses.id', $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmployeeExpense $expense) => [
                'id' => $expense->id,
                'employeeType' => $expense->employee_type,
                'employeeTypeLabel' => Employee::TYPES[$expense->employee_type] ?? $expense->employee_type,
                'projectName' => $expense->project?->name,
                'projectStatus' => $expense->project?->status,
                'expenseDate' => $expense->expense_date?->toDateString(),
                'expenseDateLabel' => $expense->expense_date?->format('d/m/Y'),
                'purpose' => $expense->purpose,
                'amount' => (float) $expense->amount,
                'receiptUrl' => $expense->receipt_path ? Storage::url($expense->receipt_path) : null,
                'status' => $expense->status,
                'statusLabel' => EmployeeExpense::STATUSES[$expense->status] ?? $expense->status,
                'note' => $expense->note,
                'adminNote' => $expense->admin_note,
                'submittedBy' => $expense->submitter?->name,
                'submittedByRole' => $expense->submitter?->role,
                'reviewedBy' => $expense->reviewer?->name,
                'reviewedAtLabel' => $expense->reviewed_at?->format('d/m/Y h:i A'),
            ]);
    }

    private function summary(string $search, string $status, string $from, string $to): array
    {
        $rows = $this->baseQuery($search, $status, $from, $to)
            ->selectRaw('employee_expenses.status, count(*) as record_count, coalesce(sum(employee_expenses.amount), 0) as total_amount')
            ->groupBy('employee_expenses.status')
            ->get()
            ->keyBy('status');

        return [
            'totalCount' => (int) $rows->sum('record_count'),
            'totalAmount' => (float) $rows->sum('total_amount'),
            'pendingAmount' => (float) ($rows[EmployeeExpense::STATUS_PENDING]->total_amount ?? 0),
            'approvedAmount' => (float) ($rows[EmployeeExpense::STATUS_APPROVED]->total_amount ?? 0),
            'rejectedAmount' => (float) ($rows[EmployeeExpense::STATUS_REJECTED]->total_amount ?? 0),
        ];
    }

    private function baseQuery(string $search, string $status, string $from, string $to)
    {
        return EmployeeExpense::query()
            ->where('employee_expenses.employee_type', 'rope_access')
            ->when($status !== '' && array_key_exists($status, EmployeeExpense::STATUSES), fn ($query) => $query->where('employee_expenses.status', $status))
            ->when($from, fn ($query) => $query->whereDate('employee_expenses.expense_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('employee_expenses.expense_date', '<=', $to))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('employee_expenses.purpose', 'like', '%'.$search.'%')
                        ->orWhere('employee_expenses.note', 'like', '%'.$search.'%')
                        ->orWhere('employee_expenses.admin_note', 'like', '%'.$search.'%')
                        ->orWhere('employee_expenses.status', 'like', '%'.$search.'%')
                        ->orWhereHas('project', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('submitter', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('reviewer', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            });
    }
}
