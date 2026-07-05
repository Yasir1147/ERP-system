<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeStaffAttendanceSession extends Model
{
    protected $fillable = [
        'office_staff_attendance_id',
        'check_in_time',
        'check_out_time',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(OfficeStaffAttendance::class, 'office_staff_attendance_id');
    }
}
