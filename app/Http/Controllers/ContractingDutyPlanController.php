<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\ContractingDutyAssignment;
use App\Models\ContractingDutyPlan;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContractingDutyPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureContractingAccess($request);

        $selectedDate = $this->selectedDate($request);
        $plan = ContractingDutyPlan::query()
            ->with([
                'creator:id,name',
                'publisher:id,name',
                'finalizer:id,name',
                'assignments' => fn ($query) => $query
                    ->with([
                        'employee:id,code,name,profession,type,status',
                        'project:id,name,status,type',
                        'overtimeProject:id,name,status,type',
                    ])
                    ->orderBy('id'),
            ])
            ->whereDate('duty_date', $selectedDate)
            ->first();

        $pendingOlderPlan = ContractingDutyPlan::query()
            ->whereDate('duty_date', '<', $selectedDate)
            ->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED)
            ->orderBy('duty_date')
            ->first(['id', 'duty_date', 'status']);

        $dateRange = $request->user()->attendanceDateRange();
        $employeeLeaves = EmployeeLeave::query()
            ->whereHas('employee', fn ($query) => $query->where('type', 'contracting'))
            ->whereDate('start_date', '<=', $selectedDate)
            ->whereDate('end_date', '>=', $selectedDate)
            ->get(['employee_id', 'start_date', 'end_date', 'reason']);

        return Inertia::render('ContractingDuties/Index', [
            'selectedDate' => $selectedDate,
            'dateMin' => $dateRange['min'],
            'dateMax' => now()->addDays(30)->toDateString(),
            'plan' => $plan ? $this->planPayload($plan) : null,
            'pendingOlderPlan' => $pendingOlderPlan ? [
                'id' => $pendingOlderPlan->id,
                'date' => $pendingOlderPlan->duty_date->toDateString(),
                'status' => $pendingOlderPlan->status,
            ] : null,
            'employees' => Employee::query()
                ->where('type', 'contracting')
                ->where('status', '!=', Employee::STATUS_LEFT)
                ->orderByRaw('CAST(code AS UNSIGNED) asc')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'profession', 'status'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'code' => $employee->code,
                    'name' => $employee->name,
                    'profession' => $employee->profession,
                    'status' => $employee->status,
                    'onLeave' => $employee->status === Employee::STATUS_ON_LEAVE
                        || $employeeLeaves->contains('employee_id', $employee->id),
                    'leaveReason' => $employeeLeaves->firstWhere('employee_id', $employee->id)?->reason,
                ]),
            'projects' => Project::query()
                ->where('type', 'contracting')
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'recentPlans' => ContractingDutyPlan::query()
                ->withCount('assignments')
                ->orderByDesc('duty_date')
                ->limit(10)
                ->get(['id', 'duty_date', 'status'])
                ->map(fn (ContractingDutyPlan $recentPlan) => [
                    'id' => $recentPlan->id,
                    'date' => $recentPlan->duty_date->toDateString(),
                    'status' => $recentPlan->status,
                    'assignmentCount' => $recentPlan->assignments_count,
                ]),
        ]);
    }

    public function storeAssignments(Request $request): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $dateRange = $request->user()->attendanceDateRange();

        $data = $request->validate([
            'duty_date' => [
                'required',
                'date',
                ...($dateRange['min'] ? ['after_or_equal:'.$dateRange['min']] : []),
                'before_or_equal:'.now()->addDays(30)->toDateString(),
            ],
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where('type', 'contracting'),
            ],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('type', 'contracting')
                    ->where('status', '!=', Employee::STATUS_LEFT)),
            ],
        ]);

        $employeeIds = collect($data['employee_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $unavailableIds = Employee::query()
            ->whereIn('id', $employeeIds)
            ->where('status', Employee::STATUS_ON_LEAVE)
            ->pluck('id')
            ->merge(
                EmployeeLeave::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->whereDate('start_date', '<=', $data['duty_date'])
                    ->whereDate('end_date', '>=', $data['duty_date'])
                    ->pluck('employee_id'),
            )
            ->unique();

        if ($unavailableIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Employees on leave cannot be assigned to this duty plan.',
            ]);
        }

        DB::transaction(function () use ($data, $employeeIds, $request) {
            $plan = ContractingDutyPlan::query()->firstOrCreate(
                ['duty_date' => $data['duty_date']],
                ['status' => ContractingDutyPlan::STATUS_DRAFT, 'created_by' => $request->user()->id],
            );

            $this->ensureEditable($plan);

            $existingIds = $plan->assignments()->whereIn('employee_id', $employeeIds)->pluck('employee_id');
            if ($existingIds->isNotEmpty()) {
                $names = Employee::query()
                    ->whereIn('id', $existingIds)
                    ->get(['code', 'name'])
                    ->map(fn (Employee $employee) => trim($employee->code.' - '.$employee->name))
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'employee_ids' => 'Already assigned to this duty plan: '.$names.'.',
                ]);
            }

            foreach ($employeeIds as $employeeId) {
                $plan->assignments()->create([
                    'employee_id' => $employeeId,
                    'project_id' => $data['project_id'],
                    'status' => ContractingDutyAssignment::STATUS_PRESENT,
                ]);
            }
        });

        return back()->with('success', 'Employees added to the duty plan.');
    }

    public function updateAssignment(Request $request, ContractingDutyAssignment $assignment): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $assignment->loadMissing('plan');
        $this->ensureEditable($assignment->plan);

        $isPresent = $request->input('status') === ContractingDutyAssignment::STATUS_PRESENT;
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('type', 'contracting')],
            'status' => ['required', Rule::in(ContractingDutyAssignment::STATUSES)],
            'has_overtime' => ['required', 'boolean'],
            'overtime_hours' => [
                'nullable',
                Rule::requiredIf($isPresent && $request->boolean('has_overtime')),
                'integer',
                'between:1,10',
            ],
            'overtime_project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('type', 'contracting')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $isPresent || ! $data['has_overtime']) {
            $data['has_overtime'] = false;
            $data['overtime_hours'] = null;
            $data['overtime_project_id'] = null;
        } elseif (blank($data['overtime_project_id'] ?? null)) {
            $data['overtime_project_id'] = $data['project_id'];
        }

        $assignment->update($data);

        return back()->with('success', 'Duty assignment updated.');
    }

    public function destroyAssignment(Request $request, ContractingDutyAssignment $assignment): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $assignment->loadMissing('plan');
        $this->ensureEditable($assignment->plan);
        $assignment->delete();

        return back()->with('success', 'Employee removed from the duty plan.');
    }

    public function markPlannedPresent(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $this->ensureEditable($plan);

        $plan->assignments()
            ->where('status', ContractingDutyAssignment::STATUS_PLANNED)
            ->update(['status' => ContractingDutyAssignment::STATUS_PRESENT]);

        return back()->with('success', 'All planned employees marked present.');
    }

    public function publish(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $this->ensureEditable($plan);

        if (! $plan->assignments()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Add at least one employee before publishing the duty plan.']);
        }

        $olderPending = ContractingDutyPlan::query()
            ->whereDate('duty_date', '<', $plan->duty_date)
            ->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED)
            ->orderBy('duty_date')
            ->first();

        if ($olderPending) {
            throw ValidationException::withMessages([
                'plan' => 'Complete the pending duty plan for '.$olderPending->duty_date->format('d/m/Y').' before publishing this plan.',
            ]);
        }

        $plan->update([
            'status' => ContractingDutyPlan::STATUS_PUBLISHED,
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        return back()->with('success', 'Duty plan published for final review.');
    }

    public function finalize(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);

        if ($plan->status === ContractingDutyPlan::STATUS_FINALIZED) {
            throw ValidationException::withMessages(['plan' => 'This duty plan is already submitted.']);
        }

        if ($plan->duty_date->isFuture()) {
            throw ValidationException::withMessages(['plan' => 'Attendance can only be submitted on or after the duty date.']);
        }

        $olderPending = ContractingDutyPlan::query()
            ->whereDate('duty_date', '<', $plan->duty_date)
            ->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED)
            ->orderBy('duty_date')
            ->first();

        if ($olderPending) {
            throw ValidationException::withMessages([
                'plan' => 'Submit the pending duty plan for '.$olderPending->duty_date->format('d/m/Y').' first.',
            ]);
        }

        DB::transaction(function () use ($plan, $request) {
            $lockedPlan = ContractingDutyPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status === ContractingDutyPlan::STATUS_FINALIZED) {
                throw ValidationException::withMessages(['plan' => 'This duty plan is already finalized.']);
            }

            $lockedPlan->assignments()
                ->where('status', ContractingDutyAssignment::STATUS_PLANNED)
                ->update(['status' => ContractingDutyAssignment::STATUS_PRESENT]);

            $assignments = $lockedPlan->assignments()->with('employee:id,code,name')->lockForUpdate()->get();
            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages(['plan' => 'The duty plan has no employees.']);
            }

            $attendanceAssignments = $assignments->where('status', '!=', ContractingDutyAssignment::STATUS_REMOVED);
            $duplicateIds = AttendanceRecord::query()
                ->whereDate('attendance_date', $lockedPlan->duty_date)
                ->whereIn('employee_id', $attendanceAssignments->pluck('employee_id'))
                ->pluck('employee_id');

            if ($duplicateIds->isNotEmpty()) {
                $names = $assignments
                    ->whereIn('employee_id', $duplicateIds)
                    ->map(fn (ContractingDutyAssignment $assignment) => trim($assignment->employee->code.' - '.$assignment->employee->name))
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'plan' => 'Attendance is already marked for: '.$names.'. Correct those records before finalizing this duty plan.',
                ]);
            }

            foreach ($attendanceAssignments as $assignment) {
                $isPresent = $assignment->status === ContractingDutyAssignment::STATUS_PRESENT;
                $record = AttendanceRecord::create([
                    'employee_id' => $assignment->employee_id,
                    'project_id' => $isPresent ? $assignment->project_id : null,
                    'overtime_project_id' => $isPresent && $assignment->has_overtime
                        ? ($assignment->overtime_project_id ?: $assignment->project_id)
                        : null,
                    'submitted_by' => $request->user()->id,
                    'status' => $assignment->status,
                    'leave_reason' => $assignment->status === ContractingDutyAssignment::STATUS_LEAVE
                        ? ($assignment->note ?: 'Leave recorded during duty review')
                        : null,
                    'attendance_date' => $lockedPlan->duty_date,
                    'has_overtime' => $isPresent && $assignment->has_overtime,
                    'overtime_hours' => $isPresent && $assignment->has_overtime ? $assignment->overtime_hours : null,
                    'overtime_time' => null,
                ]);

                $assignment->update(['attendance_record_id' => $record->id]);
            }

            $lockedPlan->update([
                'status' => ContractingDutyPlan::STATUS_FINALIZED,
                'finalized_by' => $request->user()->id,
                'finalized_at' => now(),
            ]);
        });

        return back()->with('success', 'Duty plan finalized and attendance submitted.');
    }

    private function selectedDate(Request $request): string
    {
        $date = $request->string('date')->toString() ?: now()->addDay()->toDateString();

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return now()->addDay()->toDateString();
        }
    }

    private function ensureContractingAccess(Request $request): void
    {
        abort_unless($request->user()?->canAccessEmployeeType('contracting'), 403);
    }

    private function ensureEditable(ContractingDutyPlan $plan): void
    {
        if ($plan->status === ContractingDutyPlan::STATUS_FINALIZED) {
            throw ValidationException::withMessages(['plan' => 'Finalized duty plans cannot be changed.']);
        }
    }

    private function planPayload(ContractingDutyPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'date' => $plan->duty_date->toDateString(),
            'status' => $plan->status,
            'createdBy' => $plan->creator?->name,
            'publishedBy' => $plan->publisher?->name,
            'publishedAt' => $plan->published_at?->format('d/m/Y h:i A'),
            'finalizedBy' => $plan->finalizer?->name,
            'finalizedAt' => $plan->finalized_at?->format('d/m/Y h:i A'),
            'assignments' => $plan->assignments->map(fn (ContractingDutyAssignment $assignment) => [
                'id' => $assignment->id,
                'employeeId' => $assignment->employee_id,
                'employeeCode' => $assignment->employee?->code,
                'employeeName' => $assignment->employee?->name,
                'profession' => $assignment->employee?->profession,
                'projectId' => $assignment->project_id,
                'projectName' => $assignment->project?->name,
                'status' => $assignment->status,
                'hasOvertime' => $assignment->has_overtime,
                'overtimeHours' => $assignment->overtime_hours,
                'overtimeProjectId' => $assignment->overtime_project_id,
                'overtimeProjectName' => $assignment->overtimeProject?->name ?: $assignment->project?->name,
                'note' => $assignment->note,
                'attendanceRecordId' => $assignment->attendance_record_id,
            ])->values(),
        ];
    }
}
