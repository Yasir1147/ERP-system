<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Turns "Other" plus a typed project name into a real project id.
 *
 * Sites turn up that nobody registered yet, and a field user cannot be left
 * unable to record the day's work. The typed name is not kept as loose text:
 * every cost figure hangs off project_id, so attendance without one drops
 * silently out of labour cost, the project statement, and employee history.
 * A real project is raised instead, flagged provisional for an admin to
 * review, complete, or merge.
 */
trait ResolvesNamedProjects
{
    public const OTHER_PROJECT = 'other';

    /**
     * Replaces the named project fields on the request with real ids, so the
     * validation that follows only ever sees an integer.
     *
     * @param  array<string, string>  $fields  id field => name field
     */
    protected function resolveNamedProjects(Request $request, string $employeeType, array $fields): void
    {
        foreach ($fields as $idField => $nameField) {
            if ((string) $request->input($idField) !== self::OTHER_PROJECT) {
                continue;
            }

            $name = trim((string) $request->input($nameField));

            Validator::make([$nameField => $name], [
                $nameField => ['required', 'string', 'min:3', 'max:120'],
            ], [], [$nameField => 'project name'])->validate();

            $project = Project::raiseProvisional($name, $employeeType, $request->user()?->id);

            $request->merge([$idField => $project->id]);
        }
    }
}
