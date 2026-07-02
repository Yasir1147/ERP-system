<?php

namespace App\Http\Controllers;

use App\Mail\FineTicketSubmitted;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\EmployeeFine;
use App\Models\PayrollAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeFineController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->query('per_page', 5);
        $perPage = in_array($perPage, [5, 10, 15, 25], true) ? $perPage : 5;
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'fine_date');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $fineRows = $this->fineRows($search, $perPage, $sort, $direction);

        return Inertia::render('Fines/Index', [
            'employees' => $this->employeeOptions(),
            'fines' => $fineRows->items(),
            'pagination' => [
                'currentPage' => $fineRows->currentPage(),
                'lastPage' => $fineRows->lastPage(),
                'perPage' => $fineRows->perPage(),
                'total' => $fineRows->total(),
                'from' => $fineRows->firstItem(),
                'to' => $fineRows->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'employeeTypes' => Employee::TYPES,
            'reasons' => EmployeeFine::REASONS,
            'statuses' => EmployeeFine::STATUSES,
            'currentMonth' => Carbon::today()->startOfMonth()->format('Y-m'),
            'nextMonth' => Carbon::today()->addMonthNoOverflow()->startOfMonth()->format('Y-m'),
        ]);
    }

    public function create(): Response
    {
        $selectedType = $this->normalizeType(request()->query('type'));
        $user = request()->user();

        if ($user?->isAttendanceUser()) {
            abort_unless($user->canAccessEmployeeType($selectedType), 403);
            $selectedType ??= $user->attendance_employee_type;
        }

        return Inertia::render('Fines/Create', [
            'employees' => $this->employeeOptions($selectedType),
            'employeeTypes' => Employee::TYPES,
            'reasons' => EmployeeFine::REASONS,
            'selectedType' => $selectedType,
            'employeeTypeLabel' => $selectedType ? Employee::TYPES[$selectedType] : 'All Employees',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', '!=', Employee::STATUS_LEFT)),
            ],
            'fine_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', Rule::in(array_keys(Employee::TYPES))],
        ]);

        $requestedType = $data['type'] ?? null;
        $user = $request->user();

        if ($user?->isAttendanceUser()) {
            abort_unless($user->canAccessEmployeeType($requestedType), 403);
            $requestedType ??= $user->attendance_employee_type;
        }

        if ($requestedType && ! Employee::query()->whereKey($data['employee_id'])->where('type', $requestedType)->exists()) {
            return back()
                ->withErrors(['employee_id' => 'Selected employee does not match this fine ticket type.'])
                ->withInput();
        }

        $fine = EmployeeFine::create([
            'employee_id' => $data['employee_id'],
            'fine_date' => $data['fine_date'],
            'reason' => $data['reason'],
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
            'status' => EmployeeFine::STATUS_PENDING,
        ]);

        $this->sendFineTicketEmail($fine);

        return $request->user()?->role === User::ROLE_ADMIN
            ? to_route('fines.index')->with('success', 'Fine ticket created.')
            : to_route('fines.create', $request->only('type'))->with('success', 'Fine ticket submitted for admin review.');
    }

    public function apply(Request $request, EmployeeFine $employeeFine): RedirectResponse
    {
        abort_unless($employeeFine->status === EmployeeFine::STATUS_PENDING, 422);

        $data = $request->validate([
            'deduction_month' => ['required', 'date_format:Y-m'],
            'applied_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$employeeFine->amount],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['deduction_month'])->startOfMonth()->toDateString();
        $appliedAmount = (float) $data['applied_amount'];

        DB::transaction(function () use ($employeeFine, $data, $month, $request, $appliedAmount) {
            $adjustment = PayrollAdjustment::firstOrCreate(
                [
                    'employee_id' => $employeeFine->employee_id,
                    'month' => $month,
                ],
                [
                    'bonus_extra' => 0,
                    'previous_balance' => 0,
                    'previous_balance_overridden' => false,
                    'deduction' => 0,
                    'paid_by_cash' => 0,
                    'remarks' => null,
                ],
            );

            $fineRemark = 'Fine: '.$employeeFine->reason.' - '.number_format($appliedAmount, 2);

            if ($appliedAmount < (float) $employeeFine->amount) {
                $fineRemark .= ' (reduced from '.number_format((float) $employeeFine->amount, 2).')';
            }

            $remarks = collect([$adjustment->remarks, $fineRemark])
                ->filter()
                ->implode(' | ');

            $adjustment->forceFill([
                'deduction' => (float) $adjustment->deduction + $appliedAmount,
                'remarks' => $remarks,
            ])->save();

            $employeeFine->forceFill([
                'status' => EmployeeFine::STATUS_APPLIED,
                'deduction_month' => $month,
                'applied_amount' => $appliedAmount,
                'payroll_adjustment_id' => $adjustment->id,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'admin_note' => $data['admin_note'] ?? null,
            ])->save();
        });

        return to_route('fines.index')->with('success', 'Fine applied to payroll deduction.');
    }

    public function waive(Request $request, EmployeeFine $employeeFine): RedirectResponse
    {
        abort_unless($employeeFine->status === EmployeeFine::STATUS_PENDING, 422);

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $employeeFine->forceFill([
            'status' => EmployeeFine::STATUS_WAIVED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_note' => $data['admin_note'] ?? null,
        ])->save();

        return to_route('fines.index')->with('success', 'Fine waived.');
    }

    private function employeeOptions(?string $type = null)
    {
        return Employee::query()
            ->where('status', '!=', Employee::STATUS_LEFT)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'profession', 'type', 'status'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'code' => $employee->code,
                'name' => $employee->name,
                'profession' => $employee->profession,
                'type' => $employee->type,
                'status' => $employee->status,
                'label' => collect([$employee->code, $employee->name, $employee->profession])->filter()->implode(' - '),
            ]);
    }

    private function normalizeType(mixed $type): ?string
    {
        return is_string($type) && array_key_exists($type, Employee::TYPES) ? $type : null;
    }

    private function sendFineTicketEmail(EmployeeFine $fine): void
    {
        if (! AppSetting::configureMailer()) {
            return;
        }

        $recipients = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('receive_fine_emails', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            $fine->loadMissing(['employee', 'creator']);

            Mail::to($recipients->all())->send(new FineTicketSubmitted($fine));
        } catch (\Throwable $exception) {
            Log::warning('Fine ticket email failed.', [
                'fine_id' => $fine->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function fineRows(string $search, int $perPage, string $sort, string $direction)
    {
        $sortColumns = [
            'employee' => 'sort_employees.name',
            'fine_date' => 'employee_fines.fine_date',
            'reason' => 'employee_fines.reason',
            'amount' => 'employee_fines.amount',
            'status' => 'employee_fines.status',
            'created_by' => 'sort_creators.name',
        ];
        $sortColumn = $sortColumns[$sort] ?? $sortColumns['fine_date'];

        return EmployeeFine::query()
            ->select('employee_fines.*')
            ->leftJoin('employees as sort_employees', 'sort_employees.id', '=', 'employee_fines.employee_id')
            ->leftJoin('users as sort_creators', 'sort_creators.id', '=', 'employee_fines.created_by')
            ->with(['employee:id,code,name,profession,type,status', 'creator:id,name,role', 'reviewer:id,name,role'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('employee_fines.reason', 'like', '%'.$search.'%')
                        ->orWhere('employee_fines.note', 'like', '%'.$search.'%')
                        ->orWhere('employee_fines.admin_note', 'like', '%'.$search.'%')
                        ->orWhere('employee_fines.status', 'like', '%'.$search.'%')
                        ->orWhereHas('employee', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%')
                                ->orWhere('profession', 'like', '%'.$search.'%')
                                ->orWhere('type', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('creator', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('reviewer', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy($sortColumn, $direction)
            ->orderBy('employee_fines.id', $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmployeeFine $fine) => [
                'id' => $fine->id,
                'employeeId' => $fine->employee_id,
                'employeeCode' => $fine->employee?->code,
                'employeeName' => $fine->employee?->name ?? 'Unknown Employee',
                'employeeProfession' => $fine->employee?->profession,
                'employeeType' => $fine->employee?->type,
                'fineDate' => $fine->fine_date?->toDateString(),
                'fineDateLabel' => $fine->fine_date?->format('d/m/Y'),
                'deductionMonth' => $fine->deduction_month?->format('Y-m'),
                'deductionMonthLabel' => $fine->deduction_month?->format('F Y'),
                'reason' => $fine->reason,
                'amount' => (float) $fine->amount,
                'appliedAmount' => $fine->applied_amount === null ? null : (float) $fine->applied_amount,
                'status' => $fine->status,
                'statusLabel' => EmployeeFine::STATUSES[$fine->status] ?? $fine->status,
                'note' => $fine->note,
                'adminNote' => $fine->admin_note,
                'createdBy' => $fine->creator?->name,
                'createdByRole' => $fine->creator?->role,
                'reviewedBy' => $fine->reviewer?->name,
                'reviewedAtLabel' => $fine->reviewed_at?->format('d/m/Y h:i A'),
            ]);
    }
}
