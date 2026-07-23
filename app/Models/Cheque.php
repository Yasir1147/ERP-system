<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    use HasFactory;

    public const STATUS_PREPARED = 'prepared';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_VOID = 'void';

    public const STATUSES = [
        self::STATUS_PREPARED => 'Prepared',
        self::STATUS_PRINTED => 'Printed',
        self::STATUS_VOID => 'Void',
    ];

    protected $fillable = [
        'cheque_format_id',
        'cheque_book_id',
        'cheque_book_leaf_id',
        'submission_token',
        'cheque_party_id',
        'cheque_number',
        'cheque_date',
        'issued_date',
        'amount',
        'payee_name',
        'amount_in_words',
        'fils_on_second_line',
        'account_payee_text',
        'signature_text',
        'label_1_text',
        'label_2_text',
        'voucher_number',
        'remarks',
        'purpose',
        'received_by',
        'receiver_id',
        'receiver_mobile',
        'prepared_by',
        'checked_by',
        'approved_by',
        'status',
        'format_snapshot',
        'printed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cheque_date' => 'date',
            'issued_date' => 'date',
            'amount' => 'decimal:2',
            'fils_on_second_line' => 'boolean',
            'format_snapshot' => 'array',
            'printed_at' => 'datetime',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(ChequeFormat::class, 'cheque_format_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(ChequeParty::class, 'cheque_party_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ChequeBook::class, 'cheque_book_id');
    }

    public function leaf(): BelongsTo
    {
        return $this->belongsTo(ChequeBookLeaf::class, 'cheque_book_leaf_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
