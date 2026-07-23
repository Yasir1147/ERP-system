<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeBookLeaf extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'cheque_book_id',
        'cheque_number',
        'cheque_id',
        'status',
    ];

    protected function casts(): array
    {
        return ['cheque_number' => 'integer'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ChequeBook::class, 'cheque_book_id');
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }
}
