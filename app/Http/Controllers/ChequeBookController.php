<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\ChequeBookLeaf;
use App\Models\ChequeFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChequeBookController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cheque_format_id' => ['required', 'integer', Rule::exists('cheque_formats', 'id')],
            'reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cheque_books')->where('cheque_format_id', $request->integer('cheque_format_id')),
            ],
            'start_number' => ['required', 'regex:/^\d{1,18}$/'],
            'end_number' => ['required', 'regex:/^\d{1,18}$/'],
            'received_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $startNumber = (int) $data['start_number'];
        $endNumber = (int) $data['end_number'];
        $numberLength = max(strlen((string) $data['start_number']), strlen((string) $data['end_number']));

        if ($startNumber < 1) {
            throw ValidationException::withMessages([
                'start_number' => 'The starting cheque number must be at least 1.',
            ]);
        }

        if ($endNumber < $startNumber) {
            throw ValidationException::withMessages([
                'end_number' => 'The ending cheque number must be greater than or equal to the starting cheque number.',
            ]);
        }

        if (($endNumber - $startNumber) + 1 > 500) {
            throw ValidationException::withMessages([
                'end_number' => 'A cheque book can contain a maximum of 500 cheque leaves.',
            ]);
        }

        $book = DB::transaction(function () use ($data, $request, $startNumber, $endNumber, $numberLength) {
            $format = ChequeFormat::query()->lockForUpdate()->findOrFail($data['cheque_format_id']);

            if (ChequeBook::query()->where('cheque_format_id', $format->id)->where('status', ChequeBook::STATUS_ACTIVE)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'cheque_format_id' => 'This cheque format already has an active cheque book. Finish or close it before creating another active book.',
                ]);
            }

            $overlaps = ChequeBook::query()
                ->where('cheque_format_id', $format->id)
                ->where('start_number', '<=', $endNumber)
                ->where('end_number', '>=', $startNumber)
                ->lockForUpdate()
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'start_number' => 'This cheque number range overlaps an existing cheque book for the selected format.',
                ]);
            }

            $numbers = range($startNumber, $endNumber);
            $numberCandidates = collect($numbers)
                ->flatMap(fn (int $number) => [
                    (string) $number,
                    str_pad((string) $number, $numberLength, '0', STR_PAD_LEFT),
                ])
                ->unique()
                ->values()
                ->all();

            if (Cheque::query()->where('cheque_format_id', $format->id)->whereIn('cheque_number', $numberCandidates)->exists()) {
                throw ValidationException::withMessages([
                    'start_number' => 'This range contains a cheque number that already exists in legacy cheque history.',
                ]);
            }

            $book = ChequeBook::query()->create([
                ...$data,
                'start_number' => $startNumber,
                'end_number' => $endNumber,
                'number_length' => $numberLength,
                'next_number' => $startNumber,
                'status' => ChequeBook::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $now = now();
            $rows = [];

            for ($number = $startNumber; $number <= $endNumber; $number++) {
                $rows[] = [
                    'cheque_book_id' => $book->id,
                    'cheque_number' => $number,
                    'status' => ChequeBookLeaf::STATUS_AVAILABLE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ChequeBookLeaf::query()->insert($rows);

            return $book;
        });

        return to_route('cheques.index', ['book_id' => $book->id])->with('success', 'Cheque book created successfully.');
    }

    public function close(Request $request, ChequeBook $chequeBook): RedirectResponse
    {
        DB::transaction(function () use ($request, $chequeBook) {
            $book = ChequeBook::query()->lockForUpdate()->findOrFail($chequeBook->id);

            if ($book->status !== ChequeBook::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['book' => 'Only an active cheque book can be closed.']);
            }

            $book->update([
                'status' => ChequeBook::STATUS_CLOSED,
                'next_number' => null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return to_route('cheques.index', ['book_id' => $chequeBook->id])->with('success', 'Cheque book closed. Unused leaves remain recorded.');
    }
}
