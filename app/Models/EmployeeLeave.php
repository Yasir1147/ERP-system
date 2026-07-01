<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    use HasFactory;

    public const PAYROLL_DEDUCTION_PENDING = 'pending';
    public const PAYROLL_DEDUCTION_APPLIED = 'applied';
    public const PAYROLL_DEDUCTION_WAIVED = 'waived';

    public const PAYROLL_DEDUCTION_STATUSES = [
        self::PAYROLL_DEDUCTION_PENDING,
        self::PAYROLL_DEDUCTION_APPLIED,
        self::PAYROLL_DEDUCTION_WAIVED,
    ];

    protected $fillable = [
        'employee_id',
        'created_by',
        'start_date',
        'end_date',
        'reason',
        'payroll_deduction_status',
        'payroll_deduct_days',
        'payroll_deduction_month',
        'payroll_deduction_note',
        'payroll_deduction_reviewed_by',
        'payroll_deduction_reviewed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'payroll_deduction_month' => 'date',
        'payroll_deduction_reviewed_at' => 'datetime',
        'payroll_deduct_days' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrollDeductionReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payroll_deduction_reviewed_by');
    }
}
