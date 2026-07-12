<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractingDutyPlan extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FINALIZED = 'finalized';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_FINALIZED,
    ];

    protected $fillable = [
        'duty_date',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'duty_date' => 'date',
        'published_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ContractingDutyAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
