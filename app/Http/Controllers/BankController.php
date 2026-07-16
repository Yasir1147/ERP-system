<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('bank', [
            'name' => ['required', 'string', 'max:255', Rule::unique('banks', 'name')],
        ]);

        $bank = Bank::query()->create([
            'name' => trim($data['name']),
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()
            ->with('success', 'Bank added successfully.')
            ->with('new_bank_id', $bank->id);
    }
}
