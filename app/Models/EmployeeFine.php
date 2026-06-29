<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFine extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_WAIVED = 'waived';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPLIED => 'Applied to Payroll',
        self::STATUS_WAIVED => 'Waived',
    ];

    public const REASONS = [
        'Late arrival',
        'Absence without notice',
        'Safety violation',
        'Uniform/PPE violation',
        'Work damage',
        'Other',
    ];

    protected $fillable = [
        'employee_id',
        'created_by',
        'reviewed_by',
        'payroll_adjustment_id',
        'fine_date',
        'deduction_month',
        'reason',
        'amount',
        'applied_amount',
        'status',
        'note',
        'admin_note',
        'reviewed_at',
    ];

    protected $casts = [
        'fine_date' => 'date',
        'deduction_month' => 'date',
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payrollAdjustment(): BelongsTo
    {
        return $this->belongsTo(PayrollAdjustment::class);
    }
}
