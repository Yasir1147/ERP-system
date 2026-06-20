<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLeaveController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('EmployeeLeaves/Index', [
            'employees' => Employee::query()
                ->where('status', '!=', Employee::STATUS_LEFT)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'profession', 'type', 'status']),
            'employeeTypes' => Employee::TYPES,
            'leaves' => EmployeeLeave::query()
                ->with([
                    'employee:id,name,profession,type,status',
                    'creator:id,name,role',
                ])
                ->latest('start_date')
                ->get()
                ->map(fn (EmployeeLeave $leave) => [
                    'id' => $leave->id,
                    'employeeId' => $leave->employee_id,
                    'employeeName' => $leave->employee?->name,
                    'employeeProfession' => $leave->employee?->profession,
                    'employeeType' => $leave->employee?->type,
                    'employeeStatus' => $leave->employee?->status,
                    'startDate' => $leave->start_date->toDateString(),
                    'endDate' => $leave->end_date->toDateString(),
                    'startDateLabel' => $leave->start_date->format('d/m/Y'),
                    'endDateLabel' => $leave->end_date->format('d/m/Y'),
                    'reason' => $leave->reason,
                    'createdBy' => $leave->creator?->name,
                    'createdByRole' => $leave->creator?->role,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureNoOverlap($data['employee_id'], $data['start_date'], $data['end_date']);

        $data['created_by'] = $request->user()->id;

        EmployeeLeave::create($data);

        return to_route('employee-leaves.index');
    }

    public function update(Request $request, EmployeeLeave $employeeLeave): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureNoOverlap($data['employee_id'], $data['start_date'], $data['end_date'], $employeeLeave->id);

        $employeeLeave->update($data);

        return to_route('employee-leaves.index');
    }

    public function destroy(EmployeeLeave $employeeLeave): RedirectResponse
    {
        $employeeLeave->delete();

        return to_route('employee-leaves.index');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('status', '!=', Employee::STATUS_LEFT)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function ensureNoOverlap(int $employeeId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $hasOverlap = EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_date' => 'This employee already has leave in the selected date range.',
            ]);
        }
    }
}
