<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseBillItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_bill_id', 'item_type', 'description', 'quantity', 'unit', 'unit_price', 'line_total'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
