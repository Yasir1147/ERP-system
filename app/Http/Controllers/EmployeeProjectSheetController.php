<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\Attendance\EmployeeProjectSheetService;
use App\Services\Excel\EmployeeProjectSheetExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeProjectSheetController extends Controller
{
    public function index(Request $request, EmployeeProjectSheetService $service)
    {
        return Inertia::render('Attendance/EmployeeProjectSheet', [
            'report' => $service->build($this->filters($request)),
            'employees' => Employee::orderBy('code')->get(['id', 'code', 'name', 'profession', 'type', 'status']),
            'employeeTypes' => Employee::TYPES,
        ]);
    }

    public function export(Request $request, EmployeeProjectSheetService $service, EmployeeProjectSheetExporter $exporter)
    {
        return $exporter->download($service->build($this->filters($request)));
    }

    public function print(Request $request, EmployeeProjectSheetService $service)
    {
        return view('attendance.employee-project-sheet', $service->build($this->filters($request)));
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'employee_ids' => ['nullable', 'array', 'max:2000'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data['from'] ?? now('Asia/Dubai')->startOfMonth()->toDateString();
        $to = $data['to'] ?? now('Asia/Dubai')->endOfMonth()->toDateString();
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        return ['employeeIds' => array_map('intval', $data['employee_ids'] ?? []), 'from' => $from, 'to' => $to];
    }
}
