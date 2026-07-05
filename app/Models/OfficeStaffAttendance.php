<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeStaffAttendance extends Model
{
    public const MODE_REMOTE = 'remote';

    public const MODE_OFFICE = 'office';

    public const MODES = [
        self::MODE_REMOTE => 'Remote Work',
        self::MODE_OFFICE => 'Office Work',
    ];

    protected $fillable = [
        'office_staff_id',
        'submitted_by',
        'attendance_date',
        'work_mode',
        'check_in_time',
        'check_out_time',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function officeStaff(): BelongsTo
    {
        return $this->belongsTo(OfficeStaff::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(OfficeStaffAttendanceSession::class);
    }
}
