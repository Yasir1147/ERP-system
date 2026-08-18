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
use Illuminate\Support\Collection;
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

        $status = $request->string('status')->toString();
        if (! in_array($status, ['open', 'submitted'], true)) {
            $status = 'all';
        }

        $ownerPlans = ContractingDutyPlan::query()
            ->where('created_by', $request->user()->id);
        $plansQuery = (clone $ownerPlans)
            ->with([
                'creator:id,name',
                'assignments' => fn ($query) => $query
                    ->where('status', '!=', ContractingDutyAssignment::STATUS_REMOVED)
                    ->with('project:id,name'),
            ]);

        if ($status === 'open') {
            $plansQuery->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED);
        } elseif ($status === 'submitted') {
            $plansQuery->where('status', ContractingDutyPlan::STATUS_FINALIZED);
        }

        $plans = $plansQuery
            ->orderByDesc('duty_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ContractingDutyPlan $plan) => [
                'id' => $plan->id,
                'date' => $plan->duty_date->toDateString(),
                'status' => $plan->status,
                'createdBy' => $plan->creator?->name,
                'assignmentCount' => $plan->assignments->count(),
                'projectCount' => $plan->assignments->pluck('project_id')->unique()->count(),
                'projectNames' => $plan->assignments->pluck('project.name')->filter()->unique()->values(),
                'canSubmit' => $plan->status !== ContractingDutyPlan::STATUS_FINALIZED
                    && ! $plan->duty_date->isFuture(),
            ]);

        return Inertia::render('ContractingDuties/Dashboard', [
            'activeStatus' => $status,
            'dateMin' => $request->user()->attendanceDateRange()['min'],
            'dateMax' => now()->addDays(30)->toDateString(),
            'plans' => $plans,
            'summary' => [
                'open' => (clone $ownerPlans)->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED)->count(),
                'submitted' => (clone $ownerPlans)->where('status', ContractingDutyPlan::STATUS_FINALIZED)->count(),
                'employees' => ContractingDutyAssignment::query()
                    ->where('status', '!=', ContractingDutyAssignment::STATUS_REMOVED)
                    ->whereHas('plan', fn ($query) => $query
                        ->where('created_by', $request->user()->id)
                        ->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED))
                    ->distinct('employee_id')
                    ->count('employee_id'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureContractingAccess($request);

        return $this->workspaceResponse($request, $this->selectedDate($request));
    }

    public function edit(Request $request, ContractingDutyPlan $plan): Response
    {
        $this->ensureContractingAccess($request);
        $this->ensurePlanAccess($request, $plan);

        return $this->workspaceResponse($request, $plan->duty_date->toDateString(), $plan);
    }

    private function workspaceResponse(Request $request, string $selectedDate, ?ContractingDutyPlan $selectedPlan = null): Response
    {
        $selectedPlan ??= ContractingDutyPlan::query()
            ->whereDate('duty_date', $selectedDate)
            ->where('created_by', $request->user()->id)
            ->first();

        $plan = $selectedPlan
            ? ContractingDutyPlan::query()
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
                ->findOrFail($selectedPlan->id)
            : null;

        $pendingOlderPlan = ContractingDutyPlan::query()
            ->whereDate('duty_date', '<', $selectedDate)
            ->where('created_by', $request->user()->id)
            ->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED)
            ->orderBy('duty_date')
            ->first(['id', 'duty_date', 'status']);

        $dateRange = $request->user()->attendanceDateRange();
        $employeeLeaves = EmployeeLeave::query()
            ->whereHas('employee', fn ($query) => $query->where('type', 'contracting'))
            ->whereDate('start_date', '<=', $selectedDate)
            ->whereDate('end_date', '>=', $selectedDate)
            ->get(['employee_id', 'start_date', 'end_date', 'reason']);
        $assignedEmployeeIds = ContractingDutyAssignment::query()
            ->whereDate('duty_date', $selectedDate)
            ->where('status', '!=', ContractingDutyAssignment::STATUS_REMOVED)
            ->where(function ($query) {
                $query->whereNotNull('attendance_record_id')
                    ->orWhereHas('plan', fn ($planQuery) => $planQuery
                        ->where('status', '!=', ContractingDutyPlan::STATUS_FINALIZED));
            })
            ->pluck('employee_id')
            ->merge(
                AttendanceRecord::query()
                    ->whereDate('attendance_date', $selectedDate)
                    ->pluck('employee_id'),
            )
            ->unique()
            ->values();

        return Inertia::render('ContractingDuties/Index', [
            'initialStep' => max(1, min(3, $request->integer('step', 1))),
            'extensionMode' => $request->boolean('extend')
                && $plan?->status === ContractingDutyPlan::STATUS_FINALIZED,
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
                ->whereNotIn('id', $assignedEmployeeIds)
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
        ]);
    }

    public function storeAssignments(Request $request): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $dateRange = $request->user()->attendanceDateRange();
        // Adding people to a duty that already exists is not the same as
        // backdating a new one. The plan's date was allowed when it was
        // created, and that same plan can already be submitted, so holding
        // the addition to the user's backdate window only blocked editing
        // last week's duty while still permitting its attendance.
        $existingPlan = ContractingDutyPlan::query()
            ->whereDate('duty_date', (string) $request->input('duty_date'))
            ->where('created_by', $request->user()->id)
            ->exists();

        $dutyDateRules = ['required', 'date', 'before_or_equal:'.now()->addDays(30)->toDateString()];

        if (! $existingPlan && $dateRange['min']) {
            $dutyDateRules[] = 'after_or_equal:'.$dateRange['min'];
        }

        $data = $request->validate([
            'workflow' => ['nullable', Rule::in(['wizard'])],
            'extend_finalized' => ['nullable', 'boolean'],
            'duty_date' => $dutyDateRules,
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

        $plan = DB::transaction(function () use ($data, $employeeIds, $request) {
            $this->removeReleasedAssignments($data['duty_date'], $employeeIds);

            // Matched with whereDate rather than firstOrCreate: duty_date is
            // date-cast, so the stored value can carry a time component. An
            // exact match then misses the existing plan and the insert hits
            // the unique index, which is what made "add more people to this
            // duty" fail with a server error instead of adding them.
            $plan = ContractingDutyPlan::query()
                ->whereDate('duty_date', $data['duty_date'])
                ->where('created_by', $request->user()->id)
                ->first();

            $plan ??= ContractingDutyPlan::query()->create([
                'duty_date' => $data['duty_date'],
                'created_by' => $request->user()->id,
                'status' => ContractingDutyPlan::STATUS_DRAFT,
            ]);

            $this->ensurePlanAccess($request, $plan);
            $isFinalizedExtension = $plan->status === ContractingDutyPlan::STATUS_FINALIZED
                && (bool) ($data['extend_finalized'] ?? false);

            if ($plan->status === ContractingDutyPlan::STATUS_FINALIZED && ! $isFinalizedExtension) {
                $this->ensureEditable($plan);
            }

            $existingIds = ContractingDutyAssignment::query()
                ->whereDate('duty_date', $data['duty_date'])
                ->whereIn('employee_id', $employeeIds)
                ->pluck('employee_id');
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

            if ($isFinalizedExtension) {
                $plan->update([
                    'status' => ContractingDutyPlan::STATUS_DRAFT,
                    'published_by' => null,
                    'published_at' => null,
                    'finalized_by' => null,
                    'finalized_at' => null,
                ]);
            }

            foreach ($employeeIds as $employeeId) {
                $plan->assignments()->create([
                    'duty_date' => $data['duty_date'],
                    'employee_id' => $employeeId,
                    'project_id' => $data['project_id'],
                    'status' => ContractingDutyAssignment::STATUS_PRESENT,
                ]);
            }

            return $plan;
        });

        if (($data['workflow'] ?? null) === 'wizard') {
            return redirect()->route('contracting-duties.edit', ['plan' => $plan, 'step' => 2])
                ->with('success', 'Employees added to the duty plan.');
        }

        return back()->with('success', 'Employees added to the duty plan.');
    }

    public function updateAssignment(Request $request, ContractingDutyAssignment $assignment): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $assignment->loadMissing('plan');
        $this->ensurePlanAccess($request, $assignment->plan);
        $this->ensureEditable($assignment->plan);
        $this->ensureAssignmentNotSubmitted($assignment);

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
        $this->ensurePlanAccess($request, $assignment->plan);
        $this->ensureEditable($assignment->plan);
        $this->ensureAssignmentNotSubmitted($assignment);
        $assignment->delete();

        return back()->with('success', 'Employee removed from the duty plan.');
    }

    public function destroy(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $this->ensurePlanAccess($request, $plan);
        $this->ensureEditable($plan);
        $plan->delete();

        return back()->with('success', 'Duty plan deleted.');
    }

    public function markPlannedPresent(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $this->ensurePlanAccess($request, $plan);
        $this->ensureEditable($plan);

        $plan->assignments()
            ->whereNull('attendance_record_id')
            ->where('status', ContractingDutyAssignment::STATUS_PLANNED)
            ->update(['status' => ContractingDutyAssignment::STATUS_PRESENT]);

        return back()->with('success', 'All planned employees marked present.');
    }

    public function publish(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $this->ensurePlanAccess($request, $plan);
        $this->ensureEditable($plan);

        if (! $plan->assignments()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Add at least one employee before publishing the duty plan.']);
        }

        $olderPending = ContractingDutyPlan::query()
            ->whereDate('duty_date', '<', $plan->duty_date)
            ->where('created_by', $plan->created_by)
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
        $this->ensurePlanAccess($request, $plan);

        if ($plan->status === ContractingDutyPlan::STATUS_FINALIZED) {
            throw ValidationException::withMessages(['plan' => 'This duty plan is already submitted.']);
        }

        if ($plan->duty_date->isFuture()) {
            throw ValidationException::withMessages(['plan' => 'Attendance can only be submitted on or after the duty date.']);
        }

        $olderPending = ContractingDutyPlan::query()
            ->whereDate('duty_date', '<', $plan->duty_date)
            ->where('created_by', $plan->created_by)
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
            $this->ensurePlanAccess($request, $lockedPlan);
            if ($lockedPlan->status === ContractingDutyPlan::STATUS_FINALIZED) {
                throw ValidationException::withMessages(['plan' => 'This duty plan is already finalized.']);
            }

            $lockedPlan->assignments()
                ->whereNull('attendance_record_id')
                ->where('status', ContractingDutyAssignment::STATUS_PLANNED)
                ->update(['status' => ContractingDutyAssignment::STATUS_PRESENT]);

            $assignments = $lockedPlan->assignments()
                ->whereNull('attendance_record_id')
                ->with('employee:id,code,name')
                ->lockForUpdate()
                ->get();
            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages(['plan' => 'There are no new employees to submit.']);
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

    public function repeat(Request $request, ContractingDutyPlan $plan): RedirectResponse
    {
        $this->ensureContractingAccess($request);
        $this->ensurePlanAccess($request, $plan);
        $dateRange = $request->user()->attendanceDateRange();

        $data = $request->validate([
            'target_date' => [
                'required',
                'date',
                ...($dateRange['min'] ? ['after_or_equal:'.$dateRange['min']] : []),
                'before_or_equal:'.now()->addDays(30)->toDateString(),
            ],
        ]);

        if ($plan->duty_date->toDateString() === $data['target_date']) {
            throw ValidationException::withMessages(['plan' => 'Choose a different date for the repeated duty plan.']);
        }

        $plan->loadMissing('assignments.employee:id,code,name,status');
        $sourceAssignments = $plan->assignments
            ->where('status', '!=', ContractingDutyAssignment::STATUS_REMOVED);

        if ($sourceAssignments->isEmpty()) {
            throw ValidationException::withMessages(['plan' => 'The selected duty plan has no employees to copy.']);
        }

        $employeeIds = $sourceAssignments->pluck('employee_id');
        $unavailableIds = Employee::query()
            ->whereIn('id', $employeeIds)
            ->whereIn('status', [Employee::STATUS_LEFT, Employee::STATUS_ON_LEAVE])
            ->pluck('id')
            ->merge(
                EmployeeLeave::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->whereDate('start_date', '<=', $data['target_date'])
                    ->whereDate('end_date', '>=', $data['target_date'])
                    ->pluck('employee_id'),
            )
            ->merge(
                ContractingDutyAssignment::query()
                    ->whereDate('duty_date', $data['target_date'])
                    ->whereIn('employee_id', $employeeIds)
                    ->pluck('employee_id'),
            )
            ->unique();

        $eligibleAssignments = $sourceAssignments
            ->reject(fn (ContractingDutyAssignment $assignment) => $unavailableIds->contains($assignment->employee_id));

        if ($eligibleAssignments->isEmpty()) {
            throw ValidationException::withMessages([
                'plan' => 'No employees can be copied. They are unavailable or already assigned on the selected date.',
            ]);
        }

        $newPlan = DB::transaction(function () use ($data, $eligibleAssignments, $plan) {
            if (ContractingDutyPlan::query()
                ->whereDate('duty_date', $data['target_date'])
                ->where('created_by', $plan->created_by)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'plan' => 'You already have a duty plan for the selected date.',
                ]);
            }

            $newPlan = ContractingDutyPlan::query()->create([
                'duty_date' => $data['target_date'],
                'status' => ContractingDutyPlan::STATUS_DRAFT,
                'created_by' => $plan->created_by,
            ]);

            $newPlan->assignments()->createMany(
                $eligibleAssignments->map(fn (ContractingDutyAssignment $assignment) => [
                    'duty_date' => $data['target_date'],
                    'employee_id' => $assignment->employee_id,
                    'project_id' => $assignment->project_id,
                    'status' => ContractingDutyAssignment::STATUS_PRESENT,
                    'has_overtime' => false,
                    'overtime_hours' => null,
                    'overtime_project_id' => null,
                    'note' => null,
                ])->all(),
            );

            return $newPlan;
        });

        $skipped = $sourceAssignments->count() - $eligibleAssignments->count();
        $message = 'Previous duty copied with '.$eligibleAssignments->count().' employees.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' unavailable or already assigned employees were skipped.';
        }

        return redirect()->route('contracting-duties.edit', ['plan' => $newPlan, 'step' => 2])
            ->with('success', $message);
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

    private function ensurePlanAccess(Request $request, ContractingDutyPlan $plan): void
    {
        abort_unless($request->user()->isAdmin() || (int) $plan->created_by === (int) $request->user()->id, 403);
    }

    private function removeReleasedAssignments(string $date, Collection $employeeIds): void
    {
        $releasedAssignments = ContractingDutyAssignment::query()
            ->whereDate('duty_date', $date)
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('attendance_record_id')
            ->whereHas('plan', fn ($query) => $query
                ->where('status', ContractingDutyPlan::STATUS_FINALIZED))
            ->get(['id', 'contracting_duty_plan_id']);

        if ($releasedAssignments->isEmpty()) {
            return;
        }

        $planIds = $releasedAssignments
            ->pluck('contracting_duty_plan_id')
            ->unique()
            ->values();

        ContractingDutyAssignment::query()
            ->whereIn('id', $releasedAssignments->pluck('id'))
            ->delete();

        ContractingDutyPlan::query()
            ->whereIn('id', $planIds)
            ->get()
            ->each(function (ContractingDutyPlan $plan) {
                if (! $plan->assignments()->exists()) {
                    $plan->delete();
                }
            });
    }

    private function ensureEditable(ContractingDutyPlan $plan): void
    {
        if ($plan->status === ContractingDutyPlan::STATUS_FINALIZED) {
            throw ValidationException::withMessages(['plan' => 'Finalized duty plans cannot be changed.']);
        }
    }

    private function ensureAssignmentNotSubmitted(ContractingDutyAssignment $assignment): void
    {
        if ($assignment->attendance_record_id) {
            throw ValidationException::withMessages([
                'assignment' => 'Submitted attendance cannot be changed from the duty plan.',
            ]);
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
