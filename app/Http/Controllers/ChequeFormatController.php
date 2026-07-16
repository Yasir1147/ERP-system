<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Cheque;
use App\Models\ChequeFormat;
use App\Models\ChequeFormatField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChequeFormatController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $bankId = $request->integer('bank_id') ?: null;
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 10;

        $formats = ChequeFormat::query()
            ->with(['bank:id,name', 'creator:id,name'])
            ->withCount('fields')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($bankId, fn ($query) => $query->where('bank_id', $bankId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ChequeFormat $format) => [
                'id' => $format->id,
                'name' => $format->name,
                'bankName' => $format->bank?->name,
                'createdBy' => $format->creator?->name,
                'fieldCount' => $format->fields_count,
                'updatedAt' => $format->updated_at?->format('d/m/Y h:i A'),
            ]);

        return Inertia::render('ChequeFormats/Index', [
            'formats' => $formats->items(),
            'pagination' => [
                'currentPage' => $formats->currentPage(),
                'lastPage' => $formats->lastPage(),
                'perPage' => $formats->perPage(),
                'total' => $formats->total(),
                'from' => $formats->firstItem(),
                'to' => $formats->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'bankId' => $bankId ? (string) $bankId : '',
                'perPage' => $perPage,
            ],
            'banks' => $this->bankOptions(),
        ]);
    }

    public function create(): Response
    {
        return $this->formResponse(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $format = DB::transaction(function () use ($data, $request) {
            $format = ChequeFormat::query()->create([
                ...$this->formatAttributes($data),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $format->fields()->createMany($this->fieldAttributes($data['fields']));

            return $format;
        });

        return to_route('cheque-formats.edit', $format)->with('success', 'Cheque format created successfully.');
    }

    public function edit(ChequeFormat $chequeFormat): Response
    {
        $chequeFormat->load(['fields', 'bank:id,name']);

        return $this->formResponse($chequeFormat);
    }

    public function update(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        $data = $this->validatedData($request, $chequeFormat);

        DB::transaction(function () use ($data, $request, $chequeFormat) {
            $lockedFormat = ChequeFormat::query()->lockForUpdate()->findOrFail($chequeFormat->id);

            if ($lockedFormat->version !== (int) $data['version']) {
                throw ValidationException::withMessages([
                    'version' => 'This cheque format was changed by another user. Reload the page before saving again.',
                ]);
            }

            $lockedFormat->update([
                ...$this->formatAttributes($data),
                'version' => $lockedFormat->version + 1,
                'updated_by' => $request->user()->id,
            ]);

            $lockedFormat->fields()->delete();
            $lockedFormat->fields()->createMany($this->fieldAttributes($data['fields']));
        });

        return to_route('cheque-formats.edit', $chequeFormat)->with('success', 'Cheque format updated successfully.');
    }

    public function duplicate(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        $copy = DB::transaction(function () use ($request, $chequeFormat) {
            $source = ChequeFormat::query()->with('fields')->lockForUpdate()->findOrFail($chequeFormat->id);
            $copy = $source->replicate();
            $copy->name = $this->copyName($source);
            $copy->version = 1;
            $copy->next_cheque_number = null;
            $copy->created_by = $request->user()->id;
            $copy->updated_by = $request->user()->id;
            $copy->save();

            if ($source->background_image_path && Storage::disk('public')->exists($source->background_image_path)) {
                $extension = pathinfo($source->background_image_path, PATHINFO_EXTENSION);
                $copyPath = 'cheque-format-backgrounds/'.Str::uuid().'.'.$extension;
                Storage::disk('public')->copy($source->background_image_path, $copyPath);
                $copy->update(['background_image_path' => $copyPath]);
            }

            if ($source->logo_image_path && Storage::disk('public')->exists($source->logo_image_path)) {
                $extension = pathinfo($source->logo_image_path, PATHINFO_EXTENSION);
                $copyPath = 'cheque-format-logos/'.Str::uuid().'.'.$extension;
                Storage::disk('public')->copy($source->logo_image_path, $copyPath);
                $copy->update(['logo_image_path' => $copyPath]);
            }

            $copy->fields()->createMany($source->fields->map(fn (ChequeFormatField $field) => [
                ...$field->only([
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
                ]),
            ])->all());

            return $copy;
        });

        return to_route('cheque-formats.edit', $copy)->with('success', 'Cheque format duplicated successfully.');
    }

    public function destroy(ChequeFormat $chequeFormat): RedirectResponse
    {
        if ($chequeFormat->cheques()->exists()) {
            throw ValidationException::withMessages(['format' => 'A cheque format used by prepared cheques cannot be deleted.']);
        }

        if ($chequeFormat->background_image_path) {
            Storage::disk('public')->delete($chequeFormat->background_image_path);
        }

        if ($chequeFormat->logo_image_path) {
            Storage::disk('public')->delete($chequeFormat->logo_image_path);
        }

        $chequeFormat->delete();

        return to_route('cheque-formats.index')->with('success', 'Cheque format deleted successfully.');
    }

    public function storeBackground(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        $data = $request->validateWithBag('background', [
            'background_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $path = $data['background_image']->store('cheque-format-backgrounds', 'public');

        if ($chequeFormat->background_image_path) {
            Storage::disk('public')->delete($chequeFormat->background_image_path);
        }

        $chequeFormat->update([
            'background_image_path' => $path,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Cheque preview template uploaded successfully.');
    }

    public function destroyBackground(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        if ($chequeFormat->background_image_path) {
            Storage::disk('public')->delete($chequeFormat->background_image_path);
        }

        $chequeFormat->update([
            'background_image_path' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Cheque preview template removed.');
    }

    public function storeLogo(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        $data = $request->validateWithBag('logo', [
            'logo_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['logo_image']->store('cheque-format-logos', 'public');

        // Keep replaced files so already-prepared cheque snapshots remain printable.
        $chequeFormat->update([
            'logo_image_path' => $path,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Printable cheque logo uploaded successfully.');
    }

    public function destroyLogo(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        // Keep the file on disk because prepared cheque snapshots may still reference it.
        $chequeFormat->update([
            'logo_image_path' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Printable cheque logo removed from this format.');
    }

    public function storeChequeSequence(Request $request, ChequeFormat $chequeFormat): RedirectResponse
    {
        $data = $request->validateWithBag('sequence', [
            'next_cheque_number' => ['required', 'integer', 'min:1', 'max:999999999999999'],
        ]);

        DB::transaction(function () use ($data, $request, $chequeFormat): void {
            $format = ChequeFormat::query()->lockForUpdate()->findOrFail($chequeFormat->id);
            $highestIssuedNumber = Cheque::query()
                ->where('cheque_format_id', $format->id)
                ->whereRaw("cheque_number REGEXP '^[0-9]+$'")
                ->selectRaw('MAX(CAST(cheque_number AS UNSIGNED)) as highest_number')
                ->value('highest_number');

            if ($highestIssuedNumber !== null && (int) $data['next_cheque_number'] <= (int) $highestIssuedNumber) {
                $exception = ValidationException::withMessages([
                    'next_cheque_number' => "The next cheque number must be greater than the highest issued number ({$highestIssuedNumber}).",
                ]);
                $exception->errorBag = 'sequence';

                throw $exception;
            }

            $format->update([
                'next_cheque_number' => (int) $data['next_cheque_number'],
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Next cheque number updated successfully.');
    }

    private function formResponse(?ChequeFormat $format): Response
    {
        return Inertia::render('ChequeFormats/Form', [
            'chequeFormat' => $format ? $this->formatPayload($format) : null,
            'banks' => $this->bankOptions(),
            'dateFormats' => ChequeFormat::DATE_FORMATS,
            'fontFamilies' => ChequeFormatField::FONT_FAMILIES,
            'fieldDefinitions' => collect(ChequeFormatField::DEFINITIONS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    private function validatedData(Request $request, ?ChequeFormat $format = null): array
    {
        $uniqueName = Rule::unique('cheque_formats', 'name')
            ->where(fn ($query) => $query->where('bank_id', $request->input('bank_id')));

        if ($format) {
            $uniqueName->ignore($format->id);
        }

        $data = $request->validate([
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'cheque_width_mm' => ['required', 'numeric', 'between:50,500'],
            'cheque_height_mm' => ['required', 'numeric', 'between:30,300'],
            'date_format' => ['required', Rule::in(ChequeFormat::DATE_FORMATS)],
            'amount_figures_prefix' => ['nullable', 'string', 'max:255'],
            'amount_figures_suffix' => ['nullable', 'string', 'max:255'],
            'amount_words_prefix' => ['nullable', 'string', 'max:255'],
            'amount_words_suffix' => ['nullable', 'string', 'max:255'],
            'party_name_prefix' => ['nullable', 'string', 'max:255'],
            'party_name_suffix' => ['nullable', 'string', 'max:255'],
            'party_name_max_length' => ['required', 'integer', 'between:1,500'],
            'amount_words_max_length' => ['required', 'integer', 'between:1,500'],
            'account_payee_text' => ['nullable', 'string', 'max:255'],
            'label_1_text' => ['nullable', 'string', 'max:255'],
            'label_2_text' => ['nullable', 'string', 'max:255'],
            'signature_text' => ['nullable', 'string', 'max:255'],
            'version' => [$format ? 'required' : 'nullable', 'integer', 'min:1'],
            'fields' => ['required', 'array', 'size:'.count(ChequeFormatField::DEFINITIONS)],
            'fields.*.field_key' => ['required', 'string', 'distinct', Rule::in(array_keys(ChequeFormatField::DEFINITIONS))],
            'fields.*.x_position_mm' => ['required', 'numeric', 'min:0'],
            'fields.*.y_position_mm' => ['required', 'numeric', 'min:0'],
            'fields.*.width_mm' => ['nullable', 'numeric', 'min:0.1'],
            'fields.*.height_mm' => ['nullable', 'numeric', 'min:0.1'],
            'fields.*.font_family' => ['required', Rule::in(ChequeFormatField::FONT_FAMILIES)],
            'fields.*.font_size_pt' => ['required', 'numeric', 'between:6,72'],
            'fields.*.font_weight' => ['required', 'integer', Rule::in([400, 700])],
            'fields.*.is_italic' => ['required', 'boolean'],
            'fields.*.is_underline' => ['required', 'boolean'],
            'fields.*.text_align' => ['required', Rule::in(ChequeFormatField::TEXT_ALIGNS)],
            'fields.*.is_visible' => ['required', 'boolean'],
        ]);

        $receivedKeys = collect($data['fields'])->pluck('field_key')->sort()->values()->all();
        $requiredKeys = collect(array_keys(ChequeFormatField::DEFINITIONS))->sort()->values()->all();

        if ($receivedKeys !== $requiredKeys) {
            throw ValidationException::withMessages(['fields' => 'All required cheque fields must be provided exactly once.']);
        }

        $chequeWidth = (float) $data['cheque_width_mm'];
        $chequeHeight = (float) $data['cheque_height_mm'];

        foreach ($data['fields'] as $index => $field) {
            $x = (float) $field['x_position_mm'];
            $y = (float) $field['y_position_mm'];
            $width = (float) ($field['width_mm'] ?? 0);
            $height = (float) ($field['height_mm'] ?? 0);

            if ($x > $chequeWidth || $x + $width > $chequeWidth) {
                throw ValidationException::withMessages(["fields.$index.x_position_mm" => 'The field must remain within the cheque width.']);
            }

            if ($y > $chequeHeight || $y + $height > $chequeHeight) {
                throw ValidationException::withMessages(["fields.$index.y_position_mm" => 'The field must remain within the cheque height.']);
            }
        }

        return $data;
    }

    private function formatAttributes(array $data): array
    {
        return collect($data)->only([
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
        ])->all();
    }

    private function fieldAttributes(array $fields): array
    {
        return collect($fields)->values()->map(fn (array $field, int $index) => [
            ...$field,
            'display_name' => ChequeFormatField::DEFINITIONS[$field['field_key']],
            'sort_order' => $index,
        ])->all();
    }

    private function formatPayload(ChequeFormat $format): array
    {
        return [
            'id' => $format->id,
            'bank_id' => (string) $format->bank_id,
            'name' => $format->name,
            'cheque_width_mm' => $format->cheque_width_mm,
            'cheque_height_mm' => $format->cheque_height_mm,
            'date_format' => $format->date_format,
            'amount_figures_prefix' => $format->amount_figures_prefix,
            'amount_figures_suffix' => $format->amount_figures_suffix,
            'amount_words_prefix' => $format->amount_words_prefix,
            'amount_words_suffix' => $format->amount_words_suffix,
            'party_name_prefix' => $format->party_name_prefix,
            'party_name_suffix' => $format->party_name_suffix,
            'party_name_max_length' => $format->party_name_max_length,
            'amount_words_max_length' => $format->amount_words_max_length,
            'account_payee_text' => $format->account_payee_text,
            'label_1_text' => $format->label_1_text,
            'label_2_text' => $format->label_2_text,
            'signature_text' => $format->signature_text,
            'version' => $format->version,
            'background_image_url' => $format->background_image_path ? Storage::url($format->background_image_path) : null,
            'logo_image_url' => $format->logo_image_path ? Storage::url($format->logo_image_path) : null,
            'next_cheque_number' => $format->next_cheque_number,
            'fields' => $format->fields->map(fn (ChequeFormatField $field) => [
                'field_key' => $field->field_key,
                'x_position_mm' => $field->x_position_mm,
                'y_position_mm' => $field->y_position_mm,
                'width_mm' => $field->width_mm,
                'height_mm' => $field->height_mm,
                'font_family' => $field->font_family,
                'font_size_pt' => $field->font_size_pt,
                'font_weight' => $field->font_weight,
                'is_italic' => $field->is_italic,
                'is_underline' => $field->is_underline,
                'text_align' => $field->text_align,
                'is_visible' => $field->is_visible,
            ])->values(),
        ];
    }

    private function bankOptions()
    {
        return Bank::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Bank $bank) => ['id' => $bank->id, 'name' => $bank->name]);
    }

    private function copyName(ChequeFormat $source): string
    {
        $baseName = $source->name.' Copy';
        $name = $baseName;
        $suffix = 2;

        while (ChequeFormat::query()->where('bank_id', $source->bank_id)->where('name', $name)->exists()) {
            $name = $baseName.' '.$suffix;
            $suffix++;
        }

        return $name;
    }
}
