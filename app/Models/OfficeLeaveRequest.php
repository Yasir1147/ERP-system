<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeLeaveRequest extends Model
{
    protected $guarded = ['id'];

    public static function messageTemplate(): string
    {
        return AppSetting::getValue('office_leave_message_template', "Subject: Sick Leave - [Your Name]\nHi [Manager's Name],\nPlease accept this note that I am unwell on [Leave Date] and unable to attend work. I plan to rest and hope to be back online on [Return Date].\nFor any urgent matters, please contact [Colleague's Name], or I will catch up on everything as soon as I return.\nBest regards,\n[Your Name]");
    }

    public function attendanceLabel(): string
    {
        if ($this->status === 'rejected') {
            return 'Leave rejected';
        }
        $date = $this->leave_date->toDateString();
        $today = now('Asia/Dubai')->toDateString();
        $label = $date < $today ? 'Was on leave' : ($date === $today ? 'On Leave' : 'Scheduled leave');

        return $label.($this->status === 'pending' ? ' (Pending approval)' : '');
    }

    protected function casts(): array
    {
        return ['leave_date' => 'date:Y-m-d', 'reviewed_at' => 'datetime'];
    }

    public function officeStaff(): BelongsTo
    {
        return $this->belongsTo(OfficeStaff::class);
    }
}
