<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollSetting extends Model
{
    use HasFactory;

    public const RULE_PRESENT_DAYS = 'present_days';
    public const RULE_FIXED_30_DAYS = 'fixed_30_days';

    public const RULES = [
        self::RULE_PRESENT_DAYS => 'Present Days',
        self::RULE_FIXED_30_DAYS => 'Fixed 30 Days',
    ];

    protected $fillable = [
        'employee_id',
        'daily_salary',
        'salary_rule',
        'standard_hours_per_day',
        'is_overtime_enabled',
    ];

    protected $casts = [
        'daily_salary' => 'decimal:2',
        'standard_hours_per_day' => 'integer',
        'is_overtime_enabled' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
