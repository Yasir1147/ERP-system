<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCardTemplate;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceCardController extends Controller
{
    public function index(Request $request, AttendanceCardService $cards)
    {
        return Inertia::render('AttendanceCards/Index', [
            'report' => $cards->build($this->filters($request)),
            'templates' => AttendanceCardTemplate::orderBy('name')->get()->map->payload(),
            'employees' => Employee::where('status', '!=', Employee::STATUS_LEFT)->orderBy('code')->get(['id', 'code', 'name', 'profession', 'type']),
            'employeeTypes' => Employee::TYPES,
        ]);
    }

    public function print(Request $request, AttendanceCardService $cards)
    {
        $report = $cards->build($this->filters($request));
        if ($report['filters']['perPage'] === 2 && $report['template']['layout']['height'] > 136.5) {
            throw ValidationException::withMessages(['per_page' => 'This template is too tall for two cards per page. Select one card per A4 page or reduce its height to 136.5 mm.']);
        }

        return Inertia::render('AttendanceCards/Print', ['report' => $report]);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'], 'employee_type' => ['nullable', Rule::in(array_keys(Employee::TYPES))],
            'template_id' => ['nullable', 'integer', 'exists:attendance_card_templates,id'],
            'employee_ids' => ['nullable', 'array', 'max:500'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'all_employees' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', Rule::in([1, 2])],
        ]);
        $type = $data['employee_type'] ?? 'contracting';
        $ids = array_map('intval', $data['employee_ids'] ?? []);
        if (Employee::whereIn('id', $ids)->where('type', '!=', $type)->exists()) {
            throw ValidationException::withMessages(['employee_ids' => 'Select employees from the chosen employee type.']);
        }

        return ['month' => $data['month'] ?? now('Asia/Dubai')->format('Y-m'), 'employeeType' => $type,
            'templateId' => isset($data['template_id']) ? (int) $data['template_id'] : null, 'employeeIds' => $ids,
            'allEmployees' => (bool) ($data['all_employees'] ?? false), 'perPage' => (int) ($data['per_page'] ?? 2)];
    }
}
