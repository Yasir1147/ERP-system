<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
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
}
