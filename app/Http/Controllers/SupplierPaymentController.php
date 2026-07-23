<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    public function store(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'payment_method' => ['required', Rule::in(array_keys(SupplierPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $receipt = $request->file('receipt')?->store('supplier-payment-receipts', 'public');

        try {
            DB::transaction(function () use ($data, $receipt, $request, $purchaseBill) {
                $bill = PurchaseBill::query()->lockForUpdate()->withSum('payments as paid_amount', 'amount')->findOrFail($purchaseBill->id);
                $balance = round((float) $bill->total_amount - (float) ($bill->paid_amount ?? 0), 2);
                if ((float) $data['amount'] > $balance + 0.001) {
                    throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance of '.number_format($balance, 2).'.']);
                }
                $bill->payments()->create([
                    ...$data, 'supplier_id' => $bill->supplier_id,
                    'receipt_path' => $receipt, 'created_by' => $request->user()?->id,
                ]);
                $newPaid = (float) ($bill->paid_amount ?? 0) + (float) $data['amount'];
                $bill->update(['status' => $newPaid + 0.001 >= (float) $bill->total_amount ? 'paid' : 'partial']);
            });
        } catch (\Throwable $exception) {
            if ($receipt) Storage::disk('public')->delete($receipt);
            throw $exception;
        }

        return back()->with('success', 'Supplier payment recorded.');
    }

    public function destroy(SupplierPayment $supplierPayment): RedirectResponse
    {
        $receipt = $supplierPayment->receipt_path;
        DB::transaction(function () use ($supplierPayment) {
            $bill = PurchaseBill::query()->lockForUpdate()->findOrFail($supplierPayment->purchase_bill_id);
            $supplierPayment->delete();
            $paid = (float) $bill->payments()->sum('amount');
            $bill->update(['status' => $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= (float) $bill->total_amount ? 'paid' : 'partial')]);
        });
        if ($receipt) Storage::disk('public')->delete($receipt);

        return back()->with('success', 'Supplier payment deleted.');
    }
}
