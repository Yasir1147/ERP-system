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

    protected $fillable = [
        'project_id',
        'overtime_project_id',
        'employee_id',
        'submitted_by',
        'status',
        'leave_reason',
        'attendance_date',
        'has_overtime',
        'overtime_time',
        'overtime_hours',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'has_overtime' => 'boolean',
        'overtime_hours' => 'integer',
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
}
