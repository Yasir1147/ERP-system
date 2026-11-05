<?php

use App\Models\Bank;
use App\Models\ChequeFormat;
use App\Models\ChequeFormatField;
use App\Models\User;

function chequeFormatPayload(Bank $bank, array $overrides = []): array
{
    $geometry = [
        'party_name_1' => [25, 27, 125, 7],
        'party_name_2' => [25, 36, 125, 7],
        'amount_words_1' => [25, 47, 125, 7],
        'amount_words_2' => [25, 56, 125, 7],
        'amount_figures' => [155, 47, 38, 8],
        'cheque_date' => [155, 16, 38, 7],
        'account_payee' => [72, 8, 56, 6],
        'label_1' => [25, 68, 65, 7],
        'label_2' => [100, 68, 65, 7],
        'signature' => [145, 78, 48, 7],
        'company_logo' => [85, 65, 25, 18],
    ];

    $fields = collect(ChequeFormatField::DEFINITIONS)->keys()->map(function (string $key) use ($geometry) {
        [$x, $y, $width, $height] = $geometry[$key];

        return [
            'field_key' => $key,
            'x_position_mm' => $x,
            'y_position_mm' => $y,
            'width_mm' => $width,
            'height_mm' => $height,
            'font_family' => 'Arial',
            'font_size_pt' => 10,
            'font_weight' => 400,
            'is_italic' => false,
            'is_underline' => false,
            'text_align' => 'left',
            'is_visible' => true,
        ];
    })->all();

    return array_replace([
        'bank_id' => $bank->id,
        'name' => 'Standard Business Cheque',
        'cheque_width_mm' => 200,
        'cheque_height_mm' => 90,
        'date_format' => 'DD/MM/YYYY',
        'amount_figures_prefix' => 'AED',
        'amount_figures_suffix' => '/-',
        'amount_words_prefix' => '',
        'amount_words_suffix' => 'Only',
        'party_name_prefix' => '',
        'party_name_suffix' => '',
        'party_name_max_length' => 60,
        'amount_words_max_length' => 60,
        'account_payee_text' => 'A/C PAYEE ONLY',
        'label_1_text' => 'Valid for 2 months',
        'label_2_text' => '',
        'signature_text' => 'Signature',
        'version' => null,
        'fields' => $fields,
    ], $overrides);
}

test('cheque format pages are restricted to administrators', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $attendanceUser = User::factory()->create(['role' => User::ROLE_ATTENDANCE]);

    $this->actingAs($admin)->get('/cheque-formats')->assertOk();
    $this->actingAs($admin)->get('/cheque-formats/create')->assertOk();
    $this->actingAs($attendanceUser)->get('/cheque-formats')->assertForbidden();
});

test('an administrator can create a cheque format with all field settings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = Bank::query()->create([
        'name' => 'Test Bank',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post('/cheque-formats', chequeFormatPayload($bank))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $format = ChequeFormat::query()->firstOrFail();

    expect($format->name)->toBe('Standard Business Cheque')
        ->and($format->bank_id)->toBe($bank->id)
        ->and($format->created_by)->toBe($admin->id)
        ->and($format->fields()->count())->toBe(count(ChequeFormatField::DEFINITIONS))
        ->and($format->fields()->pluck('field_key')->sort()->values()->all())
        ->toBe(collect(array_keys(ChequeFormatField::DEFINITIONS))->sort()->values()->all());
});

test('field positions outside the cheque are rejected without saving partial data', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = Bank::query()->create(['name' => 'Boundary Bank']);
    $payload = chequeFormatPayload($bank);
    $payload['fields'][0]['x_position_mm'] = 190;

    $this->actingAs($admin)
        ->post('/cheque-formats', $payload)
        ->assertSessionHasErrors('fields.0.x_position_mm');

    expect(ChequeFormat::query()->count())->toBe(0)
        ->and(ChequeFormatField::query()->count())->toBe(0);
});

test('blank optional signature and account payee text save on create and update', function ($blank) {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = Bank::create(['name' => 'Blank Text Bank']);
    $this->actingAs($admin)->post('/cheque-formats', chequeFormatPayload($bank, [
        'signature_text' => $blank, 'account_payee_text' => $blank,
    ]))->assertRedirect()->assertSessionHasNoErrors();
    $format = ChequeFormat::firstOrFail();
    expect($format->signature_text)->toBe('')->and($format->account_payee_text)->toBe('');

    $format->update(['signature_text' => 'Signature', 'account_payee_text' => 'A/C PAYEE ONLY']);
    $this->put("/cheque-formats/{$format->id}", chequeFormatPayload($bank, [
        'version' => $format->version, 'signature_text' => $blank, 'account_payee_text' => $blank,
        'label_1_text' => 'NON-NEGOTIABLE',
    ]))->assertRedirect()->assertSessionHasNoErrors();
    $format->refresh();
    expect($format->signature_text)->toBe('')->and($format->account_payee_text)->toBe('')
        ->and($format->label_1_text)->toBe('NON-NEGOTIABLE')->and($format->version)->toBe(2);
})->with(['empty string' => [''], 'null' => [null]]);

test('a cheque format can be duplicated with independent field rows and deleted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = Bank::query()->create(['name' => 'Copy Bank']);

    $this->actingAs($admin)->post('/cheque-formats', chequeFormatPayload($bank));
    $source = ChequeFormat::query()->firstOrFail();

    $this->actingAs($admin)
        ->post("/cheque-formats/{$source->id}/duplicate")
        ->assertSessionHasNoErrors();

    $copy = ChequeFormat::query()->whereKeyNot($source->id)->firstOrFail();

    expect($copy->name)->toBe('Standard Business Cheque Copy')
        ->and($copy->fields()->count())->toBe(count(ChequeFormatField::DEFINITIONS))
        ->and($copy->fields()->pluck('id')->intersect($source->fields()->pluck('id'))->isEmpty())->toBeTrue();

    $this->actingAs($admin)->delete("/cheque-formats/{$copy->id}")->assertSessionHasNoErrors();

    expect(ChequeFormat::query()->whereKey($copy->id)->exists())->toBeFalse()
        ->and(ChequeFormatField::query()->where('cheque_format_id', $copy->id)->exists())->toBeFalse();
});

test('stale cheque format updates cannot overwrite newer changes', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = Bank::query()->create(['name' => 'Version Bank']);

    $this->actingAs($admin)->post('/cheque-formats', chequeFormatPayload($bank));
    $format = ChequeFormat::query()->firstOrFail();
    $format->update(['name' => 'Newer Name', 'version' => 2]);

    $payload = chequeFormatPayload($bank, ['name' => 'Stale Name', 'version' => 1]);

    $this->actingAs($admin)
        ->put("/cheque-formats/{$format->id}", $payload)
        ->assertSessionHasErrors('version');

    expect($format->fresh()->name)->toBe('Newer Name')
        ->and($format->fresh()->version)->toBe(2);
});

test('cheque field colors save update duplicate and reject unsafe values', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $bank = Bank::create(['name' => 'Color Bank']);
    $payload = chequeFormatPayload($bank);
    $index = array_search('account_payee', array_column($payload['fields'], 'field_key'));
    $payload['fields'][$index]['text_color'] = '#C03030';
    $this->actingAs($admin)->post('/cheque-formats', $payload)->assertSessionHasNoErrors();
    $format = ChequeFormat::firstOrFail();
    expect($format->fields()->where('field_key', 'account_payee')->first()->text_color)->toBe('#C03030');
    expect($format->fields()->where('field_key', 'party_name_1')->first()->text_color)->toBe('#000000');
    $payload['version'] = $format->version;
    $payload['fields'][$index]['text_color'] = '#123ABC';
    $this->put("/cheque-formats/{$format->id}", $payload)->assertSessionHasNoErrors();
    expect($format->fresh()->fields()->where('field_key', 'account_payee')->first()->text_color)->toBe('#123ABC');
    $this->post("/cheque-formats/{$format->id}/duplicate")->assertSessionHasNoErrors();
    expect(ChequeFormat::whereKeyNot($format->id)->firstOrFail()->fields()->where('field_key', 'account_payee')->first()->text_color)->toBe('#123ABC');
    $payload['version'] = $format->fresh()->version;
    foreach (['red', '#fff', '#GG0000', 'url(https://example.com)', null] as $invalid) {
        $payload['fields'][$index]['text_color'] = $invalid;
        $this->put("/cheque-formats/{$format->id}", $payload)->assertSessionHasErrors("fields.$index.text_color");
    }
});
