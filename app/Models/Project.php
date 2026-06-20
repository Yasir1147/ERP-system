<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    public const STATUSES = [
        'ongoing',
        'completed',
        'pending',
    ];

    public const TYPES = [
        'rope_access' => 'Rope Access Projects',
        'contracting' => 'Contracting Projects',
    ];

    protected $fillable = [
        'name',
        'status',
        'type',
    ];
}
