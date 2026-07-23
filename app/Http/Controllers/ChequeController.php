<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\ChequeBookLeaf;
use App\Models\ChequeFormat;
use App\Models\ChequeFormatField;
use App\Models\ChequeParty;
use App\Support\AmountToWords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChequeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 10;

        $books = ChequeBook::query()
            ->with(['format:id,bank_id,name', 'format.bank:id,name'])
            ->withCount([
                'leaves as total_leaves_count',
                'leaves as available_leaves_count' => fn ($query) => $query->where('status', ChequeBookLeaf::STATUS_AVAILABLE),
                'leaves as issued_leaves_count' => fn ($query) => $query->where('status', ChequeBookLeaf::STATUS_ISSUED),
                'leaves as void_leaves_count' => fn ($query) => $query->where('status', ChequeBookLeaf::STATUS_VOID),
            ])
            ->orderByRaw("case when status = 'active' then 0 when status = 'exhausted' then 1 else 2 end")
            ->orderByDesc('id')
            ->get();

        $requestedBook = (int) $request->query('book_id', 0);
        $selectedBook = $books->firstWhere('id', $requestedBook)
            ?? $books->firstWhere('status', ChequeBook::STATUS_ACTIVE)
            ?? $books->first();

        if ($selectedBook) {
            $rows = ChequeBookLeaf::query()
                ->where('cheque_book_id', $selectedBook->id)
                ->with(['cheque.party:id,name', 'cheque.format:id,name', 'cheque.creator:id,name'])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('cheque_number', 'like', '%'.$search.'%')
                    ->when(ctype_digit($search), fn ($query) => $query->orWhere('cheque_number', (int) $search))
                    ->orWhereHas('cheque', fn ($query) => $query
                        ->where('payee_name', 'like', '%'.$search.'%')
                        ->orWhere('remarks', 'like', '%'.$search.'%')
                        ->orWhere('voucher_number', 'like', '%'.$search.'%'))))
                ->when($status !== '', function ($query) use ($status) {
                    if (in_array($status, [ChequeBookLeaf::STATUS_AVAILABLE, ChequeBookLeaf::STATUS_VOID], true)) {
                        $query->where('status', $status);
                    } else {
                        $query->whereHas('cheque', fn ($query) => $query->where('status', $status));
                    }
                })
                ->orderBy('cheque_number')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (ChequeBookLeaf $leaf) => $leaf->cheque
                    ? $this->chequeRow($leaf->cheque, $leaf->status, $selectedBook)
                    : $this->availableLeafRow($leaf, $selectedBook));
        } else {
            $rows = ChequeBookLeaf::query()
                ->whereRaw('1 = 0')
                ->paginate($perPage)
                ->withQueryString();
        }

        return Inertia::render('Cheques/Index', [
            'cheques' => $rows->items(),
            'pagination' => [
                'currentPage' => $rows->currentPage(), 'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(), 'total' => $rows->total(),
                'from' => $rows->firstItem(), 'to' => $rows->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'bookId' => $selectedBook ? (string) $selectedBook->id : '',
                'status' => $status,
                'perPage' => $perPage,
            ],
            'books' => $books->map(fn (ChequeBook $book) => [
                'id' => $book->id,
                'reference' => $book->reference,
                'bankName' => $book->format?->bank?->name,
                'formatName' => $book->format?->name,
                'startNumber' => $book->formatNumber($book->start_number),
                'endNumber' => $book->formatNumber($book->end_number),
                'nextNumber' => $book->formatNumber($book->next_number),
                'status' => $book->status,
                'statusLabel' => ChequeBook::STATUSES[$book->status] ?? ucfirst($book->status),
                'totalCount' => $book->total_leaves_count,
                'availableCount' => $book->available_leaves_count,
                'issuedCount' => $book->issued_leaves_count,
                'voidCount' => $book->void_leaves_count,
                'remarks' => $book->remarks,
            ])->values(),
            'bookFormats' => ChequeFormat::query()->with('bank:id,name')->orderBy('name')->get()->map(fn (ChequeFormat $format) => [
                'id' => $format->id,
                'name' => $format->name,
                'bankName' => $format->bank?->name,
            ]),
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
            $book = ChequeBook::query()->lockForUpdate()->findOrFail($data['cheque_book_id']);

            $existingCheque = Cheque::query()
                ->where('submission_token', $data['submission_token'])
                ->first();

            if ($existingCheque) {
                return $existingCheque;
            }

            if ($book->status !== ChequeBook::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['cheque_book_id' => 'Select an active cheque book.']);
            }

            $format = ChequeFormat::query()->with('fields')->lockForUpdate()->findOrFail($book->cheque_format_id);
            $party = ChequeParty::query()->lockForUpdate()->findOrFail($data['cheque_party_id']);
            $leaf = ChequeBookLeaf::query()
                ->where('cheque_book_id', $book->id)
                ->where('status', ChequeBookLeaf::STATUS_AVAILABLE)
                ->orderBy('cheque_number')
                ->lockForUpdate()
                ->first();

            if (! $leaf) {
                $book->update(['status' => ChequeBook::STATUS_EXHAUSTED, 'next_number' => null]);
                throw ValidationException::withMessages(['cheque_book_id' => 'This cheque book has no available cheque leaves. Create a new cheque book.']);
            }

            $data['cheque_format_id'] = $format->id;
            $data['cheque_number'] = $book->formatNumber($leaf->cheque_number);
            $data['cheque_book_id'] = $book->id;
            $data['cheque_book_leaf_id'] = $leaf->id;

            $cheque = Cheque::query()->create($this->attributes($data, $request, $format, $party));
            $leaf->update(['cheque_id' => $cheque->id, 'status' => ChequeBookLeaf::STATUS_ISSUED]);

            $nextLeaf = ChequeBookLeaf::query()
                ->where('cheque_book_id', $book->id)
                ->where('status', ChequeBookLeaf::STATUS_AVAILABLE)
                ->orderBy('cheque_number')
                ->first();
            $book->update([
                'next_number' => $nextLeaf?->cheque_number,
                'status' => $nextLeaf ? ChequeBook::STATUS_ACTIVE : ChequeBook::STATUS_EXHAUSTED,
                'updated_by' => $request->user()->id,
            ]);

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
        if ($cheque->status === Cheque::STATUS_VOID) {
            throw ValidationException::withMessages([
                'cheque' => 'A void cheque cannot be edited.',
            ]);
        }

        $data = $this->validatedData($request);
        $data['cheque_format_id'] = $cheque->cheque_format_id;
        $data['cheque_book_id'] = $cheque->cheque_book_id;
        $data['submission_token'] = $cheque->submission_token;

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
        if ($cheque->cheque_book_leaf_id) {
            DB::transaction(function () use ($cheque) {
                $leaf = ChequeBookLeaf::query()->lockForUpdate()->findOrFail($cheque->cheque_book_leaf_id);
                $cheque->update(['status' => Cheque::STATUS_VOID, 'updated_by' => auth()->id()]);
                $leaf->update(['status' => ChequeBookLeaf::STATUS_VOID]);
            });

            return back()->with('success', 'Cheque marked void. Its number will not be reused.');
        }

        $cheque->delete();

        return to_route('cheques.index')->with('success', 'Cheque deleted successfully.');
    }

    public function print(Cheque $cheque): Response
    {
        return Inertia::render('Cheques/Print', [
            'cheque' => $this->printPayload($cheque),
        ]);
    }

    public function voucher(Request $request, Cheque $cheque): Response
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
            'includeCheque' => $request->boolean('include_cheque', true),
        ]);
    }

    public function markPrinted(Cheque $cheque): RedirectResponse
    {
        abort_if($cheque->status === Cheque::STATUS_VOID, 422, 'A void cheque cannot be printed.');
        $cheque->update(['status' => Cheque::STATUS_PRINTED, 'printed_at' => now()]);

        return back()->with('success', 'Cheque marked as printed.');
    }

    private function formResponse(?Cheque $cheque): Response
    {
        return Inertia::render('Cheques/Form', [
            'cheque' => $cheque ? [
                'id' => $cheque->id,
                'cheque_format_id' => (string) $cheque->cheque_format_id,
                'cheque_book_id' => $cheque->cheque_book_id ? (string) $cheque->cheque_book_id : '',
                'cheque_party_id' => (string) $cheque->cheque_party_id,
                'cheque_number' => $this->displayChequeNumber($cheque),
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
            'books' => $this->bookOptions($cheque),
            'parties' => $this->partyOptions(true),
            'defaultPreparedBy' => auth()->user()?->name,
            'defaultIssuedDate' => now()->toDateString(),
        ]);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'cheque_format_id' => ['required', 'integer', Rule::exists('cheque_formats', 'id')],
            'cheque_book_id' => [$request->isMethod('post') ? 'required' : 'nullable', 'integer', Rule::exists('cheque_books', 'id')],
            'submission_token' => ['nullable', 'uuid'],
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

        if ($request->isMethod('post') && blank($data['submission_token'] ?? null)) {
            $data['submission_token'] = (string) Str::uuid();
        }

        return $data;
    }

    private function attributes(array $data, Request $request, ChequeFormat $format, ChequeParty $party, ?Cheque $cheque = null): array
    {
        $snapshot = $this->formatSnapshot($format);
        $amountWords = AmountToWords::convert($data['amount']);

        return [
            'cheque_format_id' => $format->id,
            'cheque_book_id' => $data['cheque_book_id'] ?? $cheque?->cheque_book_id,
            'cheque_book_leaf_id' => $data['cheque_book_leaf_id'] ?? $cheque?->cheque_book_leaf_id,
            'submission_token' => $data['submission_token'] ?? $cheque?->submission_token,
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
            ...$this->formatSnapshot($format),
        ]);
    }

    private function bookOptions(?Cheque $cheque = null)
    {
        return ChequeBook::query()
            ->with(['format.bank:id,name'])
            ->where(fn ($query) => $query
                ->where('status', ChequeBook::STATUS_ACTIVE)
                ->when($cheque?->cheque_book_id, fn ($query, $bookId) => $query->orWhere('id', $bookId)))
            ->orderBy('reference')
            ->get()
            ->map(fn (ChequeBook $book) => [
                'id' => $book->id,
                'reference' => $book->reference,
                'formatId' => $book->cheque_format_id,
                'formatName' => $book->format?->name,
                'bankName' => $book->format?->bank?->name,
                'nextChequeNumber' => $book->formatNumber($book->next_number),
                'status' => $book->status,
            ]);
    }

    private function chequeRow(Cheque $cheque, string $leafStatus, ?ChequeBook $book = null): array
    {
        return [
            'id' => $cheque->id,
            'chequeNumber' => $book?->formatNumber($cheque->cheque_number) ?? $this->displayChequeNumber($cheque),
            'issueDate' => $cheque->issued_date?->format('d/m/Y'),
            'chequeDate' => $cheque->cheque_date?->format('d/m/Y'),
            'partyName' => $cheque->party?->name,
            'payeeName' => $cheque->payee_name,
            'formatName' => $cheque->format?->name,
            'amount' => (float) $cheque->amount,
            'voucherNumber' => $cheque->voucher_number,
            'remarks' => $cheque->remarks,
            'purpose' => $cheque->purpose,
            'status' => $cheque->status,
            'statusLabel' => Cheque::STATUSES[$cheque->status] ?? $cheque->status,
            'leafStatus' => $leafStatus,
            'createdBy' => $cheque->creator?->name,
        ];
    }

    private function availableLeafRow(ChequeBookLeaf $leaf, ChequeBook $book): array
    {
        return [
            'id' => null,
            'chequeNumber' => $book->formatNumber($leaf->cheque_number),
            'issueDate' => null,
            'chequeDate' => null,
            'partyName' => null,
            'payeeName' => null,
            'formatName' => null,
            'amount' => null,
            'voucherNumber' => null,
            'remarks' => null,
            'purpose' => null,
            'status' => null,
            'statusLabel' => null,
            'leafStatus' => $leaf->status,
            'createdBy' => null,
        ];
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

    private function displayChequeNumber(Cheque $cheque): ?string
    {
        if ($cheque->cheque_number === null) {
            return null;
        }

        return $cheque->book?->formatNumber($cheque->cheque_number) ?? $cheque->cheque_number;
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
            'chequeNumber' => $this->displayChequeNumber($cheque),
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
