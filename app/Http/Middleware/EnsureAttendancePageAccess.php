<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Employee;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendancePageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ATTENDANCE], true)) {
            return Inertia::render('auth/AccessDenied', [
                'message' => 'You cannot access this page.',
            ])->toResponse($request)->setStatusCode(403);
        }

        $type = $request->route('type') ?? $request->query('type');
        $type = is_string($type) ? str_replace('-', '_', $type) : null;

        if ($type && array_key_exists($type, Employee::TYPES) && ! $user->canAccessEmployeeType($type)) {
            return Inertia::render('auth/AccessDenied', [
                'message' => 'You cannot access this employee type.',
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
