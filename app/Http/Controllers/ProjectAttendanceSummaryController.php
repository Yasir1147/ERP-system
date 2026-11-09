<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Attendance\ProjectAttendanceSummaryService;
use App\Services\Excel\ProjectAttendanceSummaryExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProjectAttendanceSummaryController extends Controller
{
    public function index(Request $request, ProjectAttendanceSummaryService $service)
    {
        return Inertia::render('Attendance/ProjectSummary', [
            'report' => $service->build($this->filters($request)),
            'projects' => Project::orderBy('name')->get(['id', 'project_code', 'name', 'type']),
        ]);
    }

    public function export(Request $request, ProjectAttendanceSummaryService $service, ProjectAttendanceSummaryExporter $exporter)
    {
        return $exporter->download($service->build($this->filters($request)));
    }

    public function print(Request $request, ProjectAttendanceSummaryService $service)
    {
        return view('attendance.project-summary', $service->build($this->filters($request)));
    }

    public function history(Request $request, Project $project, ProjectAttendanceSummaryService $service)
    {
        return response()->json($service->history($project, $this->filters($request)));
    }

    public function historyExport(Request $request, Project $project, ProjectAttendanceSummaryService $service, ProjectAttendanceSummaryExporter $exporter)
    {
        return $exporter->downloadHistory($service->history($project, $this->filters($request)));
    }

    public function historyPrint(Request $request, Project $project, ProjectAttendanceSummaryService $service)
    {
        return view('attendance.project-summary-history', $service->history($project, $this->filters($request)));
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['required', 'integer', 'distinct', 'exists:projects,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data['from'] ?? now('Asia/Dubai')->startOfMonth()->toDateString();
        $to = $data['to'] ?? now('Asia/Dubai')->endOfMonth()->toDateString();
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        return ['projectIds' => array_map('intval', $data['project_ids'] ?? []), 'from' => $from, 'to' => $to];
    }
}
