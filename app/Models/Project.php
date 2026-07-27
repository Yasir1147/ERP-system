<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'project_code',
        'name',
        'client_name',
        'location',
        'project_manager',
        'status',
        'type',
        'start_date',
        'expected_end_date',
        'contract_value',
        'cost_budget',
        'progress_percentage',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'contract_value' => 'decimal:2',
        'cost_budget' => 'decimal:2',
        'progress_percentage' => 'integer',
    ];

    public function purchaseBills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(EmployeeExpense::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'assigned_project_id');
    }
}
