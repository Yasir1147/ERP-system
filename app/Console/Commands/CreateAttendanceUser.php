<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CreateAttendanceUser extends Command
{
    protected $signature = 'user:create-attendance {email} {--name=Attendance User} {--password=}';

    protected $description = 'Create or update a non-admin user who can only access attendance entry forms.';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        validator(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::defaults()]],
        )->validate();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) $this->option('name'),
                'password' => Hash::make($password),
                'role' => User::ROLE_ATTENDANCE,
                'email_verified_at' => now(),
            ],
        );

        $this->info("Attendance user ready: {$user->email}");

        return self::SUCCESS;
    }
}
