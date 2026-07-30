<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNotificationLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'employee_document_id',
        'channel',
        'notification_date',
        'status',
        'recipient',
        'days_until_expiry',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'days_until_expiry' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }
}
