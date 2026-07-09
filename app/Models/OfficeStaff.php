<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeStaff extends Model
{
    public const TYPE_REMOTE = 'remote';

    public const TYPE_ON_SITE = 'on_site';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPES = [
        self::TYPE_REMOTE => 'Remote',
        self::TYPE_ON_SITE => 'Office Work',
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    protected $table = 'office_staff';

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'designation',
        'photo_path',
        'staff_type',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(OfficeStaffAttendance::class);
    }
}
