<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_ATTENDANCE = 'attendance_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'attendance_backdate_enabled',
        'attendance_backdate_from',
        'attendance_backdate_to',
        'attendance_employee_type',
        'receive_fine_emails',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'attendance_backdate_enabled' => 'boolean',
            'receive_fine_emails' => 'boolean',
            'attendance_backdate_from' => 'date',
            'attendance_backdate_to' => 'date',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAttendanceUser(): bool
    {
        return $this->role === self::ROLE_ATTENDANCE;
    }

    public function attendanceDateRange(): array
    {
        $defaultMin = now()->subDays(2)->toDateString();
        $defaultMax = now()->toDateString();

        if ($this->isAdmin()) {
            return [
                'min' => null,
                'max' => $defaultMax,
                'message' => 'Future dates are not allowed.',
                'backdateEnabled' => true,
            ];
        }

        if (
            $this->attendance_backdate_enabled
            && $this->attendance_backdate_from
            && $this->attendance_backdate_to
        ) {
            return [
                'min' => $this->attendance_backdate_from->toDateString(),
                'max' => min($this->attendance_backdate_to->toDateString(), $defaultMax),
                'message' => 'Only the allowed backdate range is available.',
                'backdateEnabled' => true,
            ];
        }

        return [
            'min' => $defaultMin,
            'max' => $defaultMax,
            'message' => 'Only today and the previous 2 days are allowed.',
            'backdateEnabled' => false,
        ];
    }

    public function canAccessEmployeeType(?string $type): bool
    {
        if ($this->isAdmin() || ! $type || ! $this->attendance_employee_type) {
            return true;
        }

        return $this->attendance_employee_type === $type;
    }

    public function defaultAttendanceType(): string
    {
        return $this->attendance_employee_type ?: 'contracting';
    }
}
