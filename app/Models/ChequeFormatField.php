<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeFormatField extends Model
{
    use HasFactory;

    public const DEFINITIONS = [
        'party_name_1' => 'Party Name Line 1',
        'party_name_2' => 'Party Name Line 2',
        'amount_words_1' => 'Amount in Words Line 1',
        'amount_words_2' => 'Amount in Words Line 2',
        'amount_figures' => 'Amount in Figures',
        'cheque_date' => 'Date',
        'account_payee' => 'A/C Payee Only',
        'label_1' => 'Label 1',
        'label_2' => 'Label 2',
        'signature' => 'Signature',
        'company_logo' => 'Company Logo',
    ];

    public const FONT_FAMILIES = [
        'Arial',
        'Times New Roman',
        'Courier New',
        'Tahoma',
        'Verdana',
    ];

    public const TEXT_ALIGNS = ['left', 'center', 'right'];

    protected $fillable = [
        'cheque_format_id',
        'field_key',
        'display_name',
        'x_position_mm',
        'y_position_mm',
        'width_mm',
        'height_mm',
        'font_family',
        'font_size_pt',
        'font_weight',
        'is_italic',
        'is_underline',
        'text_align',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'x_position_mm' => 'decimal:2',
            'y_position_mm' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'font_size_pt' => 'decimal:2',
            'font_weight' => 'integer',
            'is_italic' => 'boolean',
            'is_underline' => 'boolean',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function chequeFormat(): BelongsTo
    {
        return $this->belongsTo(ChequeFormat::class);
    }
}
