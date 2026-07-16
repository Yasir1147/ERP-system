<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\ChequeFormat;
use App\Models\ChequeFormatField;
use App\Models\ChequeParty;
use App\Support\AmountToWords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChequeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $formatId = $request->integer('format_id') ?: null;
        $partyId = $request->integer('party_id') ?: null;
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 10;

        $cheques = Cheque::query()
            ->with(['format:id,name', 'party:id,name', 'creator:id,name'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('cheque_number', 'like', '%'.$search.'%')
                ->orWhere('voucher_number', 'like', '%'.$search.'%')
                ->orWhere('payee_name', 'like', '%'.$search.'%')))
            ->when($formatId, fn ($query) => $query->where('cheque_format_id', $formatId))
            ->when($partyId, fn ($query) => $query->where('cheque_party_id', $partyId))
            ->orderByDesc('cheque_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Cheque $cheque) => [
                'id' => $cheque->id,
                'chequeNumber' => $cheque->cheque_number,
                'date' => $cheque->cheque_date?->format('d/m/Y'),
                'partyName' => $cheque->party?->name,
                'payeeName' => $cheque->payee_name,
                'formatName' => $cheque->format?->name,
                'amount' => (float) $cheque->amount,
                'voucherNumber' => $cheque->voucher_number,
                'status' => $cheque->status,
                'statusLabel' => Cheque::STATUSES[$cheque->status] ?? $cheque->status,
                'createdBy' => $cheque->creator?->name,
            ]);

        return Inertia::render('Cheques/Index', [
            'cheques' => $cheques->items(),
            'pagination' => [
                'currentPage' => $cheques->currentPage(), 'lastPage' => $cheques->lastPage(),
                'perPage' => $cheques->perPage(), 'total' => $cheques->total(),
                'from' => $cheques->firstItem(), 'to' => $cheques->lastItem(),
            ],
            'filters' => [
                'search' => $search, 'formatId' => $formatId ? (string) $formatId : '',
                'partyId' => $partyId ? (string) $partyId : '', 'perPage' => $perPage,
            ],
            'formats' => $this->simpleFormats(),
            'parties' => $this->partyOptions(),
        ]);
    }

    public function create(): Response
    {
        return $this->formResponse(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $cheque = DB::transaction(function () use ($data, $request) {
            $format = ChequeFormat::query()->with('fields')->lockForUpdate()->findOrFail($data['cheque_format_id']);
            $party = ChequeParty::query()->lockForUpdate()->findOrFail($data['cheque_party_id']);

            if ($format->next_cheque_number === null) {
                throw ValidationException::withMessages([
                    'cheque_format_id' => 'Set the next cheque number in Cheque Formats before preparing a cheque.',
                ]);
            }

            $data['cheque_number'] = (string) $format->next_cheque_number;

            if (Cheque::query()->where('cheque_format_id', $format->id)->where('cheque_number', $data['cheque_number'])->exists()) {
                throw ValidationException::withMessages([
                    'cheque_format_id' => 'The configured cheque number has already been used. Update the sequence in Cheque Formats.',
                ]);
            }

            $cheque = Cheque::query()->create($this->attributes($data, $request, $format, $party));
            $format->update(['next_cheque_number' => $format->next_cheque_number + 1]);

            return $cheque;
        });

        return to_route('cheques.edit', $cheque)->with('success', 'Cheque prepared successfully.');
    }

    public function edit(Cheque $cheque): Response
    {
        return $this->formResponse($cheque);
    }

    public function update(Request $request, Cheque $cheque): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['cheque_format_id'] = $cheque->cheque_format_id;

        DB::transaction(function () use ($data, $request, $cheque) {
            $format = ChequeFormat::query()->with('fields')->lockForUpdate()->findOrFail($data['cheque_format_id']);
            $party = ChequeParty::query()->lockForUpdate()->findOrFail($data['cheque_party_id']);
            $data['cheque_number'] = $cheque->cheque_number;
            $cheque->update($this->attributes($data, $request, $format, $party, $cheque));
        });

        return to_route('cheques.edit', $cheque)->with('success', 'Cheque updated successfully.');
    }

    public function destroy(Cheque $cheque): RedirectResponse
    {
        $cheque->delete();

        return to_route('cheques.index')->with('success', 'Cheque deleted successfully.');
    }

    public function print(Cheque $cheque): Response
    {
        return Inertia::render('Cheques/Print', [
            'cheque' => $this->printPayload($cheque),
        ]);
    }

    public function voucher(Cheque $cheque): Response
    {
        return Inertia::render('Cheques/Voucher', [
            'cheque' => $this->printPayload($cheque),
            'voucher' => [
                'voucherNumber' => $cheque->voucher_number,
                'issuedDate' => ($cheque->issued_date ?? $cheque->created_at)?->format('d/m/Y'),
                'chequeDate' => $cheque->cheque_date?->format('d/m/Y'),
                'amount' => number_format((float) $cheque->amount, 2),
                'amountWords' => $cheque->amount_in_words,
                'beneficiary' => $cheque->payee_name,
                'purpose' => $cheque->purpose,
                'receivedBy' => $cheque->received_by,
                'receiverId' => $cheque->receiver_id,
                'receiverMobile' => $cheque->receiver_mobile,
                'preparedBy' => $cheque->prepared_by,
                'checkedBy' => $cheque->checked_by,
                'approvedBy' => $cheque->approved_by,
            ],
        ]);
    }

    public function markPrinted(Cheque $cheque): RedirectResponse
    {
        $cheque->update(['status' => Cheque::STATUS_PRINTED, 'printed_at' => now()]);

        return back()->with('success', 'Cheque marked as printed.');
    }

    private function formResponse(?Cheque $cheque): Response
    {
        return Inertia::render('Cheques/Form', [
            'cheque' => $cheque ? [
                'id' => $cheque->id,
                'cheque_format_id' => (string) $cheque->cheque_format_id,
                'cheque_party_id' => (string) $cheque->cheque_party_id,
                'cheque_number' => $cheque->cheque_number,
                'cheque_date' => $cheque->cheque_date?->toDateString(),
                'issued_date' => $cheque->issued_date?->toDateString(),
                'amount' => (float) $cheque->amount,
                'fils_on_second_line' => $cheque->fils_on_second_line,
                'payee_name' => $cheque->payee_name,
                'account_payee' => filled($cheque->account_payee_text),
                'signature_text' => $cheque->signature_text,
                'label_1_text' => $cheque->label_1_text,
                'label_2_text' => $cheque->label_2_text,
                'voucher_number' => $cheque->voucher_number,
                'remarks' => $cheque->remarks,
                'purpose' => $cheque->purpose,
                'received_by' => $cheque->received_by,
                'receiver_id' => $cheque->receiver_id,
                'receiver_mobile' => $cheque->receiver_mobile,
                'prepared_by' => $cheque->prepared_by,
                'checked_by' => $cheque->checked_by,
                'approved_by' => $cheque->approved_by,
            ] : null,
            'formats' => $this->formatOptions(),
            'parties' => $this->partyOptions(true),
            'defaultPreparedBy' => auth()->user()?->name,
            'defaultIssuedDate' => now()->toDateString(),
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'cheque_format_id' => ['required', 'integer', Rule::exists('cheque_formats', 'id')],
            'cheque_party_id' => ['required', 'integer', Rule::exists('cheque_parties', 'id')->where('is_active', true)],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'cheque_date' => ['required', 'date'],
            'issued_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'fils_on_second_line' => ['sometimes', 'boolean'],
            'payee_name' => ['required', 'string', 'max:255'],
            'account_payee' => ['required', 'boolean'],
            'signature_text' => ['nullable', 'string', 'max:255'],
            'label_1_text' => ['nullable', 'string', 'max:255'],
            'label_2_text' => ['nullable', 'string', 'max:255'],
            'voucher_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'receiver_id' => ['nullable', 'string', 'max:255'],
            'receiver_mobile' => ['nullable', 'string', 'max:255'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'checked_by' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function attributes(array $data, Request $request, ChequeFormat $format, ChequeParty $party, ?Cheque $cheque = null): array
    {
        $snapshot = $this->formatSnapshot($format);
        $amountWords = AmountToWords::convert($data['amount']);

        return [
            'cheque_format_id' => $format->id,
            'cheque_party_id' => $party->id,
            'cheque_number' => $data['cheque_number'] ?? null,
            'cheque_date' => $data['cheque_date'],
            'issued_date' => $data['issued_date'],
            'amount' => $data['amount'],
            'payee_name' => trim($data['payee_name']),
            'amount_in_words' => $amountWords,
            'fils_on_second_line' => (bool) ($data['fils_on_second_line'] ?? false),
            'account_payee_text' => $data['account_payee'] ? $format->account_payee_text : null,
            'signature_text' => $data['signature_text'] ?? null,
            'label_1_text' => $data['label_1_text'] ?? null,
            'label_2_text' => $data['label_2_text'] ?? null,
            'voucher_number' => $data['voucher_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'received_by' => $data['received_by'] ?? null,
            'receiver_id' => $data['receiver_id'] ?? null,
            'receiver_mobile' => $data['receiver_mobile'] ?? null,
            'prepared_by' => $data['prepared_by'] ?? null,
            'checked_by' => $data['checked_by'] ?? null,
            'approved_by' => $data['approved_by'] ?? null,
            'status' => $cheque?->status ?? Cheque::STATUS_PREPARED,
            'format_snapshot' => $snapshot,
            'created_by' => $cheque?->created_by ?? $request->user()->id,
            'updated_by' => $request->user()->id,
        ];
    }

    private function formatOptions()
    {
        return ChequeFormat::query()->with(['bank:id,name', 'fields'])->orderBy('name')->get()->map(fn (ChequeFormat $format) => [
            'id' => $format->id,
            'name' => $format->name,
            'bankName' => $format->bank?->name,
            'backgroundImageUrl' => $format->background_image_path ? Storage::url($format->background_image_path) : null,
            'nextChequeNumber' => $format->next_cheque_number,
            ...$this->formatSnapshot($format),
        ]);
    }

    private function formatSnapshot(ChequeFormat $format): array
    {
        return [
            'chequeWidthMm' => (float) $format->cheque_width_mm,
            'chequeHeightMm' => (float) $format->cheque_height_mm,
            'dateFormat' => $format->date_format,
            'amountFiguresPrefix' => $format->amount_figures_prefix,
            'amountFiguresSuffix' => $format->amount_figures_suffix,
            'amountWordsPrefix' => $format->amount_words_prefix,
            'amountWordsSuffix' => $format->amount_words_suffix,
            'partyNamePrefix' => $format->party_name_prefix,
            'partyNameSuffix' => $format->party_name_suffix,
            'partyNameMaxLength' => $format->party_name_max_length,
            'amountWordsMaxLength' => $format->amount_words_max_length,
            'accountPayeeText' => $format->account_payee_text,
            'signatureText' => $format->signature_text,
            'label1Text' => $format->label_1_text,
            'label2Text' => $format->label_2_text,
            'logoImageUrl' => $format->logo_image_path ? Storage::url($format->logo_image_path) : null,
            'fields' => $format->fields->map(fn (ChequeFormatField $field) => [
                'field_key' => $field->field_key,
                'x_position_mm' => (float) $field->x_position_mm,
                'y_position_mm' => (float) $field->y_position_mm,
                'width_mm' => $field->width_mm === null ? null : (float) $field->width_mm,
                'height_mm' => $field->height_mm === null ? null : (float) $field->height_mm,
                'font_family' => $field->font_family,
                'font_size_pt' => (float) $field->font_size_pt,
                'font_weight' => $field->font_weight,
                'is_italic' => $field->is_italic,
                'is_underline' => $field->is_underline,
                'text_align' => $field->text_align,
                'is_visible' => $field->is_visible,
            ])->values()->all(),
        ];
    }

    private function printPayload(Cheque $cheque): array
    {
        $snapshot = $cheque->format_snapshot;
        $fields = collect($snapshot['fields'] ?? []);
        $format = $cheque->format()->with('fields')->first();

        if (! $fields->contains('field_key', 'company_logo')) {
            $logoField = $format?->fields->firstWhere('field_key', 'company_logo');

            if ($logoField) {
                $fields->push([
                    'field_key' => $logoField->field_key,
                    'x_position_mm' => (float) $logoField->x_position_mm,
                    'y_position_mm' => (float) $logoField->y_position_mm,
                    'width_mm' => $logoField->width_mm === null ? null : (float) $logoField->width_mm,
                    'height_mm' => $logoField->height_mm === null ? null : (float) $logoField->height_mm,
                    'font_family' => $logoField->font_family,
                    'font_size_pt' => (float) $logoField->font_size_pt,
                    'font_weight' => $logoField->font_weight,
                    'is_italic' => $logoField->is_italic,
                    'is_underline' => $logoField->is_underline,
                    'text_align' => $logoField->text_align,
                    'is_visible' => $logoField->is_visible,
                ]);
            }
        }

        return [
            'id' => $cheque->id,
            'widthMm' => (float) $snapshot['chequeWidthMm'],
            'heightMm' => (float) $snapshot['chequeHeightMm'],
            'fields' => $fields->values()->all(),
            'fieldValues' => $this->fieldValues($cheque),
            'logoImageUrl' => $snapshot['logoImageUrl']
                ?? ($format?->logo_image_path ? Storage::url($format->logo_image_path) : null),
            'chequeNumber' => $cheque->cheque_number,
        ];
    }

    private function fieldValues(Cheque $cheque): array
    {
        $snapshot = $cheque->format_snapshot;
        [$partyOne, $partyTwo] = $this->splitText(trim(($snapshot['partyNamePrefix'] ?? '').' '.$cheque->payee_name.' '.($snapshot['partyNameSuffix'] ?? '')), (int) $snapshot['partyNameMaxLength']);
        $amountWordsPrefix = $snapshot['amountWordsPrefix'] ?? '';
        $amountWordsSuffix = $snapshot['amountWordsSuffix'] ?? '';
        $amountWordsText = trim($amountWordsPrefix.' '.$cheque->amount_in_words.' '.$amountWordsSuffix);

        if ($cheque->fils_on_second_line && str_contains($cheque->amount_in_words, ' And Fils ')) {
            [$wholeWords, $filsWords] = explode(' And Fils ', $cheque->amount_in_words, 2);
            $wordsOne = trim($amountWordsPrefix.' '.$wholeWords);
            $wordsTwo = trim('And Fils '.$filsWords.' '.$amountWordsSuffix);
        } else {
            [$wordsOne, $wordsTwo] = $this->splitText($amountWordsText, (int) $snapshot['amountWordsMaxLength']);
        }

        return [
            'party_name_1' => $partyOne,
            'party_name_2' => $partyTwo,
            'amount_words_1' => $wordsOne,
            'amount_words_2' => $wordsTwo,
            'amount_figures' => trim(($snapshot['amountFiguresPrefix'] ?? '').' '.number_format((float) $cheque->amount, 2).' '.($snapshot['amountFiguresSuffix'] ?? '')),
            'cheque_date' => $this->formattedDate($cheque->cheque_date, $snapshot['dateFormat']),
            'account_payee' => $cheque->account_payee_text ?? '',
            'label_1' => $cheque->label_1_text ?? '',
            'label_2' => $cheque->label_2_text ?? '',
            'signature' => $cheque->signature_text ?? '',
        ];
    }

    private function splitText(string $text, int $maximum): array
    {
        if (mb_strlen($text) <= $maximum) return [$text, ''];
        $candidate = mb_substr($text, 0, $maximum + 1);
        $split = mb_strrpos($candidate, ' ');
        $split = $split === false || $split === 0 ? $maximum : $split;

        return [trim(mb_substr($text, 0, $split)), trim(mb_substr($text, $split))];
    }

    private function formattedDate(Carbon $date, string $format): string
    {
        return $date->format(match ($format) {
            'MM/DD/YYYY' => 'm/d/Y', 'DD-MM-YYYY' => 'd-m-Y', 'YYYY-MM-DD' => 'Y-m-d', default => 'd/m/Y',
        });
    }

    private function simpleFormats()
    {
        return ChequeFormat::query()->orderBy('name')->get(['id', 'name'])->map(fn (ChequeFormat $format) => ['id' => $format->id, 'name' => $format->name]);
    }

    private function partyOptions(bool $full = false)
    {
        return ChequeParty::query()->where('is_active', true)->orderBy('name')->get()->map(fn (ChequeParty $party) => $full ? [
            'id' => $party->id, 'name' => $party->name, 'contactPerson' => $party->contact_person,
            'email' => $party->email, 'mobile' => $party->mobile, 'phone' => $party->phone,
            'fax' => $party->fax, 'address' => $party->address, 'remarks' => $party->remarks,
        ] : ['id' => $party->id, 'name' => $party->name]);
    }
}
