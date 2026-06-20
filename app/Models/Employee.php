<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    public const TYPES = [
        'rope_access' => 'Rope Access Employee',
        'contracting' => 'Contracting Employee',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_LEFT = 'left';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ON_LEAVE => 'On Leave',
        self::STATUS_LEFT => 'Left',
    ];

    protected $fillable = [
        'name',
        'profession',
        'type',
        'status',
    ];

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function payrollSetting(): HasOne
    {
        return $this->hasOne(EmployeePayrollSetting::class);
    }

    public function payrollAdjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
