<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function overview(Request $request): Response
    {
        $selectedType = $this->normalizeType($request->query('type'));
        $selectedProjectId = $this->normalizeProjectId($request->query('project_id'), $selectedType);

        $projects = Project::query()
            ->when($selectedType, fn ($query) => $query->where('type', $selectedType))
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'type', 'created_at']);

        $overviewRows = $projects
            ->when($selectedProjectId, fn (Collection $items) => $items->where('id', $selectedProjectId))
            ->map(fn (Project $project) => $this->projectOverviewRow($project))
            ->values();

        return Inertia::render('Projects/Overview', [
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
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
                'totalCost' => round($overviewRows->sum('totalCost'), 2),
            ],
            'filters' => [
                'type' => $selectedType ?? 'all',
                'projectId' => $selectedProjectId ? (string) $selectedProjectId : 'all',
            ],
            'typeOptions' => $this->typeOptions(),
            'projectTypes' => Project::TYPES,
            'statuses' => Project::STATUSES,
        ]);
    }

    public function index(?string $type = null): Response
    {
        $type ??= 'contracting';
        abort_unless(array_key_exists($type, Project::TYPES), 404);

        return Inertia::render('Projects/Index', [
            'projects' => Project::query()
                ->where('type', $type)
                ->latest()
                ->get(['id', 'name', 'status', 'type', 'created_at']),
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

        $records = AttendanceRecord::query()
            ->with(['employee.payrollSetting', 'submitter'])
            ->where('status', AttendanceRecord::STATUS_PRESENT)
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhere('overtime_project_id', $project->id);
            })
            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($data['to'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->get();

        $rows = $records->map(function (AttendanceRecord $record) use ($project) {
            $employee = $record->employee;
            $setting = $employee?->payrollSetting;
            $dailySalary = (float) ($setting?->daily_salary ?? 0);
            $standardHours = max(1, (int) ($setting?->standard_hours_per_day ?? 8));
            $basicCost = (int) $record->project_id === (int) $project->id ? $dailySalary : 0;
            $effectiveOvertimeProjectId = $record->overtime_project_id ?: $record->project_id;
            $overtimeHours = (int) $effectiveOvertimeProjectId === (int) $project->id ? (int) ($record->overtime_hours ?? 0) : 0;
            $overtimeCost = $setting?->is_overtime_enabled === false ? 0 : $overtimeHours * ($dailySalary / $standardHours);
            $totalCost = $basicCost + $overtimeCost;

            return [
                'id' => $record->id,
                'date' => $record->attendance_date?->format('d/m/Y'),
                'dateValue' => $record->attendance_date?->toDateString(),
                'employeeId' => $employee?->id,
                'employeeName' => $employee?->name ?? 'Unknown Employee',
                'profession' => $employee?->profession ?? '-',
                'status' => $record->status,
                'dailySalary' => round($dailySalary, 2),
                'overtimeHours' => $overtimeHours,
                'basicCost' => round($basicCost, 2),
                'overtimeCost' => round($overtimeCost, 2),
                'totalCost' => round($totalCost, 2),
                'submittedBy' => $record->submitter?->name ?? '-',
                'submittedByRole' => $record->submitter?->role,
                'missingPayrollSetting' => ! $setting,
            ];
        });

        $employeeSummary = $rows
            ->groupBy('employeeId')
            ->map(function (Collection $employeeRows) {
                $first = $employeeRows->first();

                return [
                    'employeeId' => $first['employeeId'],
                    'employeeName' => $first['employeeName'],
                    'profession' => $first['profession'],
                    'entries' => $employeeRows->count(),
                    'workedDays' => $employeeRows->pluck('dateValue')->unique()->count(),
                    'overtimeHours' => (int) $employeeRows->sum('overtimeHours'),
                    'basicCost' => round($employeeRows->sum('basicCost'), 2),
                    'overtimeCost' => round($employeeRows->sum('overtimeCost'), 2),
                    'totalCost' => round($employeeRows->sum('totalCost'), 2),
                    'submittedBy' => $employeeRows
                        ->map(fn (array $row) => trim($row['submittedBy'].($row['submittedByRole'] ? ' ('.$row['submittedByRole'].')' : '')))
                        ->filter(fn (string $submitter) => $submitter !== '-')
                        ->unique()
                        ->values()
                        ->implode(', ') ?: '-',
                ];
            })
            ->values();

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'type' => $project->type,
                'typeLabel' => Project::TYPES[$project->type],
            ],
            'records' => $rows->values(),
            'employeeSummary' => $employeeSummary,
            'totals' => [
                'uniqueEmployees' => $rows->pluck('employeeId')->filter()->unique()->count(),
                'entries' => $rows->count(),
                'workedDays' => $rows->pluck('dateValue')->filter()->unique()->count(),
                'overtimeHours' => (int) $rows->sum('overtimeHours'),
                'basicCost' => round($rows->sum('basicCost'), 2),
                'overtimeCost' => round($rows->sum('overtimeCost'), 2),
                'totalCost' => round($rows->sum('totalCost'), 2),
            ],
            'filters' => [
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
            ],
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
        $data = $this->validatedData($request);

        $project->update($data);

        return to_route('projects.type.index', $data['type']);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $type = $project->type;

        $project->delete();

        return to_route('projects.type.index', $type);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'type' => ['required', Rule::in(array_keys(Project::TYPES))],
        ]);
    }

    private function projectOverviewRow(Project $project): array
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
        $startDate = $firstWorkDate ?? $project->created_at;
        $elapsedEndDate = $project->status === 'completed' && $lastWorkDate ? $lastWorkDate : Carbon::today();

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
                $basicCost += $dailySalary;
            }

            $overtimeCost += $setting?->is_overtime_enabled === false ? 0 : $overtimeHours * ($dailySalary / $standardHours);
        }

        $labourIds = $records->pluck('employee_id')->unique();
        $workedDates = $records->pluck('attendance_date')->map(fn ($date) => Carbon::parse($date)->toDateString())->unique();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'type' => $project->type,
            'typeLabel' => Project::TYPES[$project->type],
            'firstWorkDate' => $firstWorkDate?->format('d/m/Y'),
            'lastWorkDate' => $lastWorkDate?->format('d/m/Y'),
            'daysSinceStart' => $startDate ? Carbon::parse($startDate)->startOfDay()->diffInDays(Carbon::parse($elapsedEndDate)->startOfDay()) + 1 : 0,
            'workedDays' => $workedDates->count(),
            'labourCount' => $labourIds->count(),
            'labourEntries' => $records->count(),
            'overtimeHours' => (int) $records->sum(fn (AttendanceRecord $record) => (int) ((int) ($record->overtime_project_id ?: $record->project_id) === (int) $project->id ? ($record->overtime_hours ?? 0) : 0)),
            'basicCost' => round($basicCost, 2),
            'overtimeCost' => round($overtimeCost, 2),
            'totalCost' => round($basicCost + $overtimeCost, 2),
            'missingPayrollSettings' => $missingPayrollSettings->filter()->unique()->values(),
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
