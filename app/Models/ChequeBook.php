<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChequeBook extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_EXHAUSTED => 'Exhausted',
        self::STATUS_CLOSED => 'Closed',
    ];

    protected $fillable = [
        'cheque_format_id',
        'reference',
        'start_number',
        'end_number',
        'number_length',
        'next_number',
        'received_date',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_number' => 'integer',
            'end_number' => 'integer',
            'number_length' => 'integer',
            'next_number' => 'integer',
            'received_date' => 'date',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(ChequeFormat::class, 'cheque_format_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(ChequeBookLeaf::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function formatNumber(int|string|null $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $width = max(
            1,
            (int) $this->number_length,
            strlen((string) $this->start_number),
            strlen((string) $this->end_number),
        );

        return str_pad((string) $number, $width, '0', STR_PAD_LEFT);
    }
}
