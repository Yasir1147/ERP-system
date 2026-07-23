<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChequeFormat extends Model
{
    use HasFactory;

    public const DATE_FORMATS = [
        'DD/MM/YYYY',
        'MM/DD/YYYY',
        'DD-MM-YYYY',
        'YYYY-MM-DD',
    ];

    protected $fillable = [
        'bank_id',
        'name',
        'cheque_width_mm',
        'cheque_height_mm',
        'date_format',
        'amount_figures_prefix',
        'amount_figures_suffix',
        'amount_words_prefix',
        'amount_words_suffix',
        'party_name_prefix',
        'party_name_suffix',
        'party_name_max_length',
        'amount_words_max_length',
        'account_payee_text',
        'label_1_text',
        'label_2_text',
        'signature_text',
        'background_image_path',
        'logo_image_path',
        'next_cheque_number',
        'version',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cheque_width_mm' => 'decimal:2',
            'cheque_height_mm' => 'decimal:2',
            'party_name_max_length' => 'integer',
            'amount_words_max_length' => 'integer',
            'next_cheque_number' => 'integer',
            'version' => 'integer',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ChequeFormatField::class)->orderBy('sort_order');
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function chequeBooks(): HasMany
    {
        return $this->hasMany(ChequeBook::class);
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
