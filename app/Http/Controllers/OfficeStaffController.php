<?php

namespace App\Http\Controllers;

use App\Models\OfficeStaff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OfficeStaffController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('OfficeStaff/Index', [
            'staff' => OfficeStaff::query()
                ->with('user:id,username')
                ->orderBy('code')
                ->get()
                ->map(fn (OfficeStaff $staff) => [
                    'id' => $staff->id,
                    'code' => $staff->code,
                    'name' => $staff->name,
                    'designation' => $staff->designation,
                    'photoUrl' => $staff->photo_path ? Storage::disk('public')->url($staff->photo_path) : null,
                    'staffType' => $staff->staff_type,
                    'staffTypeLabel' => OfficeStaff::TYPES[$staff->staff_type] ?? $staff->staff_type,
                    'status' => $staff->status,
                    'statusLabel' => OfficeStaff::STATUSES[$staff->status] ?? $staff->status,
                    'username' => $staff->user?->username,
                ]),
            'staffTypes' => OfficeStaff::TYPES,
            'statuses' => OfficeStaff::STATUSES,
            'nextCode' => $this->nextCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:office_staff,code'],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'staff_type' => ['required', Rule::in(array_keys(OfficeStaff::TYPES))],
            'status' => ['required', Rule::in(array_keys(OfficeStaff::STATUSES))],
        ]);

        $photoPath = $request->file('photo')?->store('office-staff', 'public');

        DB::transaction(function () use ($data, $photoPath) {
            $username = $this->uniqueUsername($data['name'], $data['code']);

            $user = User::create([
                'name' => $data['name'],
                'username' => $username,
                'email' => $username.'@office-staff.local',
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_OFFICE_STAFF,
                'email_verified_at' => now(),
            ]);

            OfficeStaff::create([
                'user_id' => $user->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'designation' => $data['designation'],
                'photo_path' => $photoPath,
                'staff_type' => $data['staff_type'],
                'status' => $data['status'],
            ]);
        });

        return to_route('office-staff.index')->with('success', 'Office staff created.');
    }

    public function update(Request $request, OfficeStaff $officeStaff): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('office_staff', 'code')->ignore($officeStaff->id)],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($officeStaff->user_id)],
            'designation' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'staff_type' => ['required', Rule::in(array_keys(OfficeStaff::TYPES))],
            'status' => ['required', Rule::in(array_keys(OfficeStaff::STATUSES))],
        ]);

        $photoPath = $officeStaff->photo_path;

        if ($request->hasFile('photo')) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            $photoPath = $request->file('photo')->store('office-staff', 'public');
        }

        DB::transaction(function () use ($officeStaff, $data, $photoPath) {
            $officeStaff->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'designation' => $data['designation'],
                'photo_path' => $photoPath,
                'staff_type' => $data['staff_type'],
                'status' => $data['status'],
            ]);
            $officeStaff->user?->update([
                'name' => $data['name'],
                'username' => $data['username'],
            ]);
        });

        return to_route('office-staff.index')->with('success', 'Office staff updated.');
    }

    public function destroy(OfficeStaff $officeStaff): RedirectResponse
    {
        DB::transaction(function () use ($officeStaff) {
            $user = $officeStaff->user;
            if ($officeStaff->photo_path) {
                Storage::disk('public')->delete($officeStaff->photo_path);
            }
            $officeStaff->delete();
            $user?->delete();
        });

        return to_route('office-staff.index')->with('success', 'Office staff deleted.');
    }

    private function nextCode(): string
    {
        $max = OfficeStaff::query()->max(DB::raw('cast(code as unsigned)'));

        return (string) (((int) $max) + 1 ?: 1);
    }

    private function uniqueUsername(string $name, string $code): string
    {
        $base = Str::of($code.'-'.$name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->limit(40, '')
            ->toString();

        $base = $base !== '' ? $base : 'office-staff';
        $username = $base;
        $suffix = 2;

        while (User::query()->where('username', $username)->exists()) {
            $username = $base.'-'.$suffix;
            $suffix++;
        }

        return $username;
    }
}
