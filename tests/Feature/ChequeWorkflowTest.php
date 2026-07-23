<?php

use App\Models\Bank;
use App\Models\Cheque;
use App\Models\ChequeBook;
use App\Models\ChequeBookLeaf;
use App\Models\ChequeFormat;
use App\Models\ChequeFormatField;
use App\Models\ChequeParty;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function workflowChequeFormat(User $user): ChequeFormat
{
    $bank = Bank::query()->create(['name' => 'Workflow Bank']);
    $format = ChequeFormat::query()->create([
        'bank_id' => $bank->id,
        'name' => 'Workflow Format',
        'cheque_width_mm' => 200,
        'cheque_height_mm' => 90,
        'date_format' => 'DD/MM/YYYY',
        'party_name_max_length' => 60,
        'amount_words_max_length' => 60,
        'account_payee_text' => 'A/C PAYEE ONLY',
        'signature_text' => 'Signature',
        'background_image_path' => 'cheque-format-backgrounds/preview-only.png',
        'next_cheque_number' => 10010,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    foreach (ChequeFormatField::DEFINITIONS as $index => $label) {
        $format->fields()->create([
            'field_key' => $index,
            'display_name' => $label,
            'x_position_mm' => 5,
            'y_position_mm' => 5 + ($format->fields()->count() * 7),
            'width_mm' => 60,
            'height_mm' => 6,
            'font_family' => 'Arial',
            'font_size_pt' => 10,
            'font_weight' => 400,
            'is_italic' => false,
            'is_underline' => false,
            'text_align' => 'left',
            'is_visible' => true,
            'sort_order' => $format->fields()->count(),
        ]);
    }

    return $format->fresh('fields');
}

function workflowChequeBook(User $user, ChequeFormat $format, int $start = 10010, int $end = 10012): ChequeBook
{
    $book = ChequeBook::query()->create([
        'cheque_format_id' => $format->id,
        'reference' => 'BOOK-'.$start,
        'start_number' => $start,
        'end_number' => $end,
        'next_number' => $start,
        'status' => ChequeBook::STATUS_ACTIVE,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    foreach (range($start, $end) as $number) {
        $book->leaves()->create([
            'cheque_number' => $number,
            'status' => ChequeBookLeaf::STATUS_AVAILABLE,
        ]);
    }

    return $book;
}

test('an administrator can add a party and prepare a cheque with a format snapshot', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $book = workflowChequeBook($admin, $format);
    $party = ChequeParty::query()->create(['name' => 'Acme LLC', 'is_active' => true]);

    $this->actingAs($admin)->post('/cheques', [
        'cheque_format_id' => $format->id,
        'cheque_book_id' => $book->id,
        'cheque_party_id' => $party->id,
        'cheque_number' => 'CH-1001',
        'cheque_date' => '2026-07-14',
        'issued_date' => '2026-07-15',
        'amount' => 500.50,
        'fils_on_second_line' => true,
        'payee_name' => 'Acme LLC',
        'account_payee' => true,
        'signature_text' => 'Authorized Signature',
        'label_1_text' => 'Valid for 2 months',
        'label_2_text' => null,
        'voucher_number' => 'V-1001',
        'remarks' => 'Test cheque',
        'purpose' => 'Invoice payment',
        'prepared_by' => 'Administrator',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $cheque = Cheque::query()->firstOrFail();

    expect($cheque->cheque_number)->toBe('10010')
        ->and($book->fresh()->next_number)->toBe(10011)
        ->and($cheque->book?->is($book))->toBeTrue()
        ->and($cheque->leaf?->status)->toBe(ChequeBookLeaf::STATUS_ISSUED)
        ->and($cheque->amount_in_words)->toBe('Five Hundred And Fils Fifty')
        ->and($cheque->fils_on_second_line)->toBeTrue()
        ->and($cheque->format_snapshot['fields'])->toHaveCount(count(ChequeFormatField::DEFINITIONS))
        ->and($cheque->format_snapshot)->not->toHaveKey('backgroundImageUrl')
        ->and($cheque->created_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->get("/cheques/{$cheque->id}/print")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cheques/Print')
            ->where('cheque.fieldValues.amount_words_1', 'Five Hundred')
            ->where('cheque.fieldValues.amount_words_2', 'And Fils Fifty'));

    $this->actingAs($admin)
        ->get("/cheques/{$cheque->id}/voucher")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cheques/Voucher')
            ->where('includeCheque', true)
            ->where('voucher.beneficiary', 'Acme LLC')
            ->where('voucher.purpose', 'Invoice payment')
            ->where('voucher.issuedDate', '15/07/2026'));
});

test('the print page contains positioned values but never the preview background', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $book = workflowChequeBook($admin, $format);
    $party = ChequeParty::query()->create(['name' => 'Print Party', 'is_active' => true]);

    $this->actingAs($admin)->post('/cheques', [
        'cheque_format_id' => $format->id,
        'cheque_book_id' => $book->id,
        'cheque_party_id' => $party->id,
        'cheque_number' => 'CH-PRINT',
        'cheque_date' => '2026-07-14',
        'issued_date' => '2026-07-15',
        'amount' => 100,
        'payee_name' => 'Print Party',
        'account_payee' => true,
        'signature_text' => '',
        'label_1_text' => '',
        'label_2_text' => '',
        'voucher_number' => '',
        'remarks' => '',
    ]);

    $cheque = Cheque::query()->firstOrFail();

    $this->actingAs($admin)
        ->get("/cheques/{$cheque->id}/print")
        ->assertOk()
        ->assertDontSee('preview-only.png')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cheques/Print')
            ->where('cheque.fieldValues.party_name_1', 'Print Party')
            ->where('cheque.fieldValues.amount_figures', '100.00'));
});

test('a printable logo is stored with the format and included in cheque print data', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $book = workflowChequeBook($admin, $format);

    $this->actingAs($admin)
        ->post("/cheque-formats/{$format->id}/logo", [
            'logo_image' => UploadedFile::fake()->image('company-logo.png', 300, 200),
        ])
        ->assertSessionHasNoErrors();

    $format->refresh();
    Storage::disk('public')->assertExists($format->logo_image_path);

    $party = ChequeParty::query()->create(['name' => 'Logo Party', 'is_active' => true]);
    $this->actingAs($admin)->post('/cheques', [
        'cheque_format_id' => $format->id,
        'cheque_book_id' => $book->id,
        'cheque_party_id' => $party->id,
        'cheque_number' => 'CH-LOGO',
        'cheque_date' => '2026-07-14',
        'issued_date' => '2026-07-15',
        'amount' => 100,
        'payee_name' => $party->name,
        'account_payee' => true,
        'signature_text' => '',
        'label_1_text' => '',
        'label_2_text' => '',
        'voucher_number' => '',
        'remarks' => '',
    ])->assertSessionHasNoErrors();

    $cheque = Cheque::query()->firstOrFail();

    $this->actingAs($admin)
        ->get("/cheques/{$cheque->id}/print")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cheques/Print')
            ->where('cheque.logoImageUrl', Storage::url($format->logo_image_path)));
});

test('a cheque book creates its leaf inventory and is exhausted without reusing numbers', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $party = ChequeParty::query()->create(['name' => 'Book Party', 'is_active' => true]);

    $this->actingAs($admin)->post('/cheque-books', [
        'cheque_format_id' => $format->id,
        'reference' => 'ADCB-ONE',
        'start_number' => 501,
        'end_number' => 502,
        'received_date' => '2026-07-22',
        'remarks' => 'First physical book',
    ])->assertSessionHasNoErrors();

    $book = ChequeBook::query()->where('reference', 'ADCB-ONE')->firstOrFail();
    expect($book->leaves()->count())->toBe(2);

    foreach ([501, 502] as $number) {
        $this->actingAs($admin)->post('/cheques', [
            'cheque_format_id' => $format->id,
            'cheque_book_id' => $book->id,
            'cheque_party_id' => $party->id,
            'cheque_date' => '2026-07-22',
            'issued_date' => '2026-07-22',
            'amount' => 100,
            'payee_name' => $party->name,
            'account_payee' => true,
        ])->assertSessionHasNoErrors();

        expect(Cheque::query()->where('cheque_number', (string) $number)->exists())->toBeTrue();
    }

    expect($book->fresh()->status)->toBe(ChequeBook::STATUS_EXHAUSTED)
        ->and($book->next_number)->toBeNull()
        ->and($book->leaves()->where('status', ChequeBookLeaf::STATUS_AVAILABLE)->count())->toBe(0);
});

test('repeating the same cheque submission does not consume another leaf', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $book = workflowChequeBook($admin, $format);
    $party = ChequeParty::query()->create(['name' => 'Idempotent Party', 'is_active' => true]);
    $payload = [
        'submission_token' => '48f8186e-3598-4e75-b7ba-c4c05ab98513',
        'cheque_format_id' => $format->id,
        'cheque_book_id' => $book->id,
        'cheque_party_id' => $party->id,
        'cheque_date' => '2026-07-22',
        'issued_date' => '2026-07-22',
        'amount' => 1025.52,
        'payee_name' => $party->name,
        'account_payee' => true,
        'remarks' => 'Duplicate-click protection',
    ];

    $this->actingAs($admin)->post('/cheques', $payload)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/cheques', $payload)->assertSessionHasNoErrors();

    expect(Cheque::query()->count())->toBe(1)
        ->and($book->fresh()->next_number)->toBe(10011)
        ->and($book->leaves()->where('status', ChequeBookLeaf::STATUS_ISSUED)->count())->toBe(1);
});

test('cheque books preserve leading zeros in allocated cheque numbers', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $party = ChequeParty::query()->create(['name' => 'Padded Number Party', 'is_active' => true]);

    $this->actingAs($admin)->post('/cheque-books', [
        'cheque_format_id' => $format->id,
        'reference' => 'PADDED-BOOK',
        'start_number' => '00100',
        'end_number' => '00101',
    ])->assertSessionHasNoErrors();

    $book = ChequeBook::query()->where('reference', 'PADDED-BOOK')->firstOrFail();
    expect($book->number_length)->toBe(5)
        ->and($book->formatNumber($book->start_number))->toBe('00100')
        ->and($book->formatNumber($book->end_number))->toBe('00101');

    $this->actingAs($admin)->post('/cheques', [
        'submission_token' => '287c7a37-c48a-4562-889a-8f925559a992',
        'cheque_format_id' => $format->id,
        'cheque_book_id' => $book->id,
        'cheque_party_id' => $party->id,
        'cheque_date' => '2026-07-23',
        'issued_date' => '2026-07-23',
        'amount' => 100,
        'payee_name' => $party->name,
        'account_payee' => true,
    ])->assertSessionHasNoErrors();

    expect(Cheque::query()->firstOrFail()->cheque_number)->toBe('00100')
        ->and($book->fresh()->formatNumber($book->next_number))->toBe('00101');
});

test('parties and formats referenced by cheques cannot be deleted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $format = workflowChequeFormat($admin);
    $party = ChequeParty::query()->create(['name' => 'Protected Party', 'is_active' => true]);
    Cheque::query()->create([
        'cheque_format_id' => $format->id,
        'cheque_party_id' => $party->id,
        'cheque_date' => '2026-07-14',
        'amount' => 10,
        'payee_name' => $party->name,
        'amount_in_words' => 'Ten',
        'status' => Cheque::STATUS_PREPARED,
        'format_snapshot' => ['chequeWidthMm' => 200, 'chequeHeightMm' => 90, 'fields' => []],
    ]);

    $this->actingAs($admin)->delete("/cheque-parties/{$party->id}")->assertSessionHasErrors('party');
    $this->actingAs($admin)->delete("/cheque-formats/{$format->id}")->assertSessionHasErrors('format');

    expect($party->fresh())->not->toBeNull()->and($format->fresh())->not->toBeNull();
});
