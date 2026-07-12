<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractingDutyAssignment extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_REMOVED = 'removed';

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LEAVE,
        self::STATUS_REMOVED,
    ];

    protected $fillable = [
        'contracting_duty_plan_id',
        'employee_id',
        'project_id',
        'status',
        'has_overtime',
        'overtime_hours',
        'overtime_project_id',
        'note',
        'attendance_record_id',
    ];

    protected $casts = [
        'has_overtime' => 'boolean',
        'overtime_hours' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContractingDutyPlan::class, 'contracting_duty_plan_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function overtimeProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'overtime_project_id');
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
