<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LEAVE,
    ];

    public const PAYROLL_DEDUCTION_PENDING = 'pending';
    public const PAYROLL_DEDUCTION_APPLIED = 'applied';
    public const PAYROLL_DEDUCTION_WAIVED = 'waived';

    public const PAYROLL_DEDUCTION_STATUSES = [
        self::PAYROLL_DEDUCTION_PENDING,
        self::PAYROLL_DEDUCTION_APPLIED,
        self::PAYROLL_DEDUCTION_WAIVED,
    ];

    protected $fillable = [
        'project_id',
        'overtime_project_id',
        'employee_id',
        'submitted_by',
        'status',
        'leave_reason',
        'payroll_deduction_status',
        'payroll_deduct_days',
        'payroll_deduction_month',
        'payroll_deduction_note',
        'payroll_deduction_reviewed_by',
        'payroll_deduction_reviewed_at',
        'attendance_date',
        'has_overtime',
        'overtime_time',
        'overtime_hours',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'has_overtime' => 'boolean',
        'overtime_hours' => 'integer',
        'payroll_deduction_month' => 'date',
        'payroll_deduction_reviewed_at' => 'datetime',
        'payroll_deduct_days' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overtimeProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'overtime_project_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function payrollDeductionReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payroll_deduction_reviewed_by');
    }
}
