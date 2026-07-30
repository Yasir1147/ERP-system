<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id',
        'document_category_id',
        'document_number',
        'issue_date',
        'expiry_date',
        'file_path',
        'notes',
        'reminder_days',
        'notification_enabled',
        'email_enabled',
        'whatsapp_enabled',
        'notification_email',
        'whatsapp_number',
        'notifications_stopped_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'reminder_days' => 'integer',
        'notification_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'notifications_stopped_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(DocumentNotificationLog::class);
    }

    public function effectiveReminderDays(): int
    {
        return $this->reminder_days ?? $this->category?->default_reminder_days ?? 10;
    }
}
