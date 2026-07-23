<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    use HasFactory;

    public const STATUSES = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'under_repair' => 'Under Repair',
        'retired' => 'Retired',
    ];

    protected $table = 'equipment';

    protected $fillable = [
        'supplier_id', 'purchase_bill_id', 'purchase_bill_item_id', 'assigned_project_id',
        'assigned_employee_id', 'name', 'category', 'asset_code', 'brand', 'model',
        'serial_number', 'purchase_date', 'purchase_cost', 'warranty_expiry', 'status',
        'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function bill(): BelongsTo { return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id'); }
    public function billItem(): BelongsTo { return $this->belongsTo(PurchaseBillItem::class, 'purchase_bill_item_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class, 'assigned_project_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class, 'assigned_employee_id'); }
}
