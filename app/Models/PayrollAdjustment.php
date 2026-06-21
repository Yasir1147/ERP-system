<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'month',
        'bonus_extra',
        'previous_balance',
        'previous_balance_overridden',
        'deduction',
        'paid_by_cash',
        'remarks',
    ];

    protected $casts = [
        'month' => 'date',
        'bonus_extra' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'previous_balance_overridden' => 'boolean',
        'deduction' => 'decimal:2',
        'paid_by_cash' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
