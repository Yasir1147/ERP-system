<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $fillable = [
        'name',
        'default_reminder_days',
        'is_active',
    ];

    protected $casts = [
        'default_reminder_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
