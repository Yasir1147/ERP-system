<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Services\Attendance\AttendanceStatementService;
use App\Services\Attendance\AttendanceStatementExporter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Day-by-day attendance for one employee or one project.
 *
 * The screen, the workbook, and the print view all read the same statement,
 * so a printed copy can never say something the screen did not.
 */
class AttendanceStatementController extends Controller
{
    public function __construct(private readonly AttendanceStatementService $statements)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $statement = $this->statement($filters);

        return Inertia::render('Attendance/Statement', [
            'statement' => $statement,
            'filters' => $filters,
            'employees' => Employee::query()
                ->where('status', '!=', Employee::STATUS_LEFT)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'profession', 'type'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'code' => $employee->code,
                    'name' => $employee->name,
                    'profession' => $employee->profession,
                    'type' => $employee->type,
                ]),
            'projects' => Project::query()
                ->orderBy('name')
                ->get(['id', 'name', 'project_code', 'type'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'code' => $project->project_code,
                    'name' => $project->name,
                    'type' => $project->type,
                ]),
            'employeeTypes' => collect(Employee::TYPES)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
        ]);
    }

    public function export(Request $request, AttendanceStatementExporter $exporter): StreamedResponse
    {
        $statement = $this->statement($this->filters($request));

        abort_if($statement === null, 404);

        return $exporter->download($statement);
    }

    public function print(Request $request): View
    {
        $statement = $this->statement($this->filters($request));

        abort_if($statement === null, 404);

        return view('attendance.statement-print', [
            'statement' => $statement,
            'generatedAt' => now()->format('d/m/Y h:i A'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['employee', 'project', 'type'])],
            'layout' => ['nullable', Rule::in(['list', 'grid'])],
            'employee_type' => ['nullable', Rule::in(array_keys(Employee::TYPES))],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'with_salary' => ['nullable', 'boolean'],
        ]);

        // A month is what these statements are asked for most, so an unset
        // range opens on the current one rather than on nothing at all.
        $from = $data['from'] ?? Carbon::today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? Carbon::today()->endOfMonth()->toDateString();

        $mode = $data['mode'] ?? 'employee';

        return [
            'mode' => $mode,
            // A project is read as a grid of people against days; one person is
            // read as a list of their days. Either can be switched to the other.
            'layout' => $data['layout'] ?? ($mode === 'employee' ? 'list' : 'grid'),
            'employeeType' => $data['employee_type'] ?? 'rope_access',
            'employeeId' => isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            'projectId' => isset($data['project_id']) ? (int) $data['project_id'] : null,
            'from' => $from,
            'to' => $to,
            'withSalary' => (bool) ($data['with_salary'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function statement(array $filters): ?array
    {
        if ($filters['mode'] === 'type') {
            return $this->withLayout(
                $this->statements->forEmployeeType($filters['employeeType'], $filters['from'], $filters['to'], $filters['withSalary']),
                $filters,
            );
        }

        if ($filters['mode'] === 'project') {
            $project = $filters['projectId'] ? Project::find($filters['projectId']) : null;

            return $project
                ? $this->withLayout($this->statements->forProject($project, $filters['from'], $filters['to'], $filters['withSalary']), $filters)
                : null;
        }

        $employee = $filters['employeeId'] ? Employee::with('payrollSetting')->find($filters['employeeId']) : null;

        return $employee
            ? $this->withLayout($this->statements->forEmployee($employee, $filters['from'], $filters['to'], $filters['withSalary']), $filters)
            : null;
    }

    /**
     * @param  array<string, mixed>  $statement
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withLayout(array $statement, array $filters): array
    {
        $statement['layout'] = $filters['layout'];

        return $statement;
    }
}
