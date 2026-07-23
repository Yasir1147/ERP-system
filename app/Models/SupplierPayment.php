<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use HasFactory;

    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque'];

    protected $fillable = [
        'supplier_id', 'purchase_bill_id', 'payment_date', 'amount', 'payment_method',
        'reference', 'receipt_path', 'notes', 'created_by',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }
}
