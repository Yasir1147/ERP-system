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
        'is_provisional',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'contract_value' => 'decimal:2',
        'cost_budget' => 'decimal:2',
        'progress_percentage' => 'integer',
        'is_provisional' => 'boolean',
    ];

    /**
     * The project a field user named because the site was not on the list.
     *
     * It is created as a real project rather than kept as loose text: every
     * cost figure in the system hangs off project_id, so attendance with no
     * project silently drops out of the labour cost, the project statement,
     * and the employee history. Matching is case- and space-insensitive so
     * the same site typed twice does not become two projects.
     */
    public static function raiseProvisional(string $name, string $type, ?int $userId = null): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        $existing = static::query()
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'name' => $name,
            'type' => $type,
            'status' => 'ongoing',
            'is_provisional' => true,
            'progress_percentage' => 0,
            'created_by' => $userId,
        ]);
    }

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
