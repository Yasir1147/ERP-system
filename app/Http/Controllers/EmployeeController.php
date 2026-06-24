<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(?string $type = null): Response
    {
        $type ??= 'rope_access';
        abort_unless(array_key_exists($type, Employee::TYPES), 404);

        return Inertia::render('Employees/Index', [
            'employees' => Employee::query()
                ->where('type', $type)
                ->latest()
                ->get(['id', 'code', 'name', 'profession', 'type', 'status', 'created_at']),
            'employeeType' => $type,
            'employeeTypeLabel' => Employee::TYPES[$type],
            'employeeStatuses' => Employee::STATUSES,
            'nextEmployeeCode' => $this->nextEmployeeCode($type),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        Employee::create($data);

        return to_route('employees.type.index', $data['type']);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validatedData($request);

        $employee->update($data);

        return to_route('employees.type.index', $data['type']);
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $type = $employee->type;

        $employee->delete();

        return to_route('employees.type.index', $type);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^\d+$/',
                Rule::unique('employees', 'code')
                    ->where(fn ($query) => $query->where('type', $request->input('type')))
                    ->ignore($request->route('employee')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'profession' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Employee::TYPES))],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
        ]);
    }

    private function nextEmployeeCode(string $type): string
    {
        $maxCode = Employee::query()
            ->where('type', $type)
            ->pluck('code')
            ->filter(fn (?string $code) => $code !== null && ctype_digit($code))
            ->map(fn (string $code) => (int) $code)
            ->max();

        return (string) (($maxCode ?? 309) + 1);
    }
}
