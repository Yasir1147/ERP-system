<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->latest()
                ->get([
                    'id',
                    'name',
                    'username',
                    'email',
                    'role',
                    'attendance_backdate_enabled',
                    'attendance_backdate_from',
                    'attendance_backdate_to',
                    'attendance_employee_type',
                    'receive_fine_emails',
                    'created_at',
                ])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'attendance_backdate_enabled' => $user->attendance_backdate_enabled,
                    'attendance_backdate_from' => $user->attendance_backdate_from?->toDateString(),
                    'attendance_backdate_to' => $user->attendance_backdate_to?->toDateString(),
                    'attendance_employee_type' => $user->attendance_employee_type,
                    'receive_fine_emails' => $user->receive_fine_emails,
                    'created_at' => $user->created_at,
                ]),
            'roles' => [
                User::ROLE_ADMIN => 'Admin',
                User::ROLE_ATTENDANCE => 'Attendance User',
            ],
            'currentUserId' => request()->user()->id,
            'attendanceTypeOptions' => [
                'all' => 'All Employee Types',
                ...Employee::TYPES,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'alpha_dash', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_ATTENDANCE])],
            'password' => ['required', 'confirmed', Password::defaults()],
            'attendance_backdate_enabled' => ['boolean'],
            'attendance_backdate_from' => ['nullable', 'date', 'required_if:attendance_backdate_enabled,true'],
            'attendance_backdate_to' => ['nullable', 'date', 'required_if:attendance_backdate_enabled,true', 'after_or_equal:attendance_backdate_from', 'before_or_equal:today'],
            'attendance_employee_type' => ['nullable', Rule::in(['all', ...array_keys(Employee::TYPES)])],
            'receive_fine_emails' => ['boolean'],
        ]);

        $data = $this->normalizeAttendanceUserAccess($data);

        User::create($data);

        return to_route('users.index');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'alpha_dash', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_ATTENDANCE])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'attendance_backdate_enabled' => ['boolean'],
            'attendance_backdate_from' => ['nullable', 'date', 'required_if:attendance_backdate_enabled,true'],
            'attendance_backdate_to' => ['nullable', 'date', 'required_if:attendance_backdate_enabled,true', 'after_or_equal:attendance_backdate_from', 'before_or_equal:today'],
            'attendance_employee_type' => ['nullable', Rule::in(['all', ...array_keys(Employee::TYPES)])],
            'receive_fine_emails' => ['boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data = $this->normalizeAttendanceUserAccess($data);

        $user->update($data);

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');

        $user->delete();

        return to_route('users.index');
    }

    private function normalizeAttendanceUserAccess(array $data): array
    {
        $data['attendance_backdate_enabled'] = (bool) ($data['attendance_backdate_enabled'] ?? false);

        if ($data['role'] !== User::ROLE_ATTENDANCE) {
            $data['attendance_backdate_enabled'] = false;
            $data['attendance_backdate_from'] = null;
            $data['attendance_backdate_to'] = null;
            $data['attendance_employee_type'] = null;
            $data['receive_fine_emails'] = (bool) ($data['receive_fine_emails'] ?? true);

            return $data;
        }

        if (! $data['attendance_backdate_enabled']) {
            $data['attendance_backdate_from'] = null;
            $data['attendance_backdate_to'] = null;
        }

        $data['attendance_employee_type'] = ($data['attendance_employee_type'] ?? null) === 'all'
            ? null
            : ($data['attendance_employee_type'] ?? null);
        $data['receive_fine_emails'] = false;

        return $data;
    }
}
