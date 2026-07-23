<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseBillController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $supplierId = (string) $request->query('supplier_id', '');
        $status = (string) $request->query('status', '');
        $perPage = in_array((int) $request->query('per_page', 10), [10, 15, 25, 50], true)
            ? (int) $request->query('per_page', 10) : 10;

        $bills = PurchaseBill::query()
            ->with(['supplier:id,name', 'project:id,name'])
            ->withSum('payments as paid_amount', 'amount')
            ->when($supplierId !== '', fn ($query) => $query->where('supplier_id', $supplierId))
            ->when(isset(PurchaseBill::STATUSES[$status]), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('bill_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest('bill_date')->latest('id')->paginate($perPage)->withQueryString()
            ->through(fn (PurchaseBill $bill) => $this->billRow($bill));

        return Inertia::render('PurchaseBills/Index', [
            'bills' => $bills->items(),
            'pagination' => [
                'currentPage' => $bills->currentPage(), 'lastPage' => $bills->lastPage(),
                'perPage' => $bills->perPage(), 'total' => $bills->total(),
                'from' => $bills->firstItem(), 'to' => $bills->lastItem(),
            ],
            'filters' => ['search' => $search, 'supplierId' => $supplierId, 'status' => $status, 'perPage' => $perPage],
            'suppliers' => $this->supplierOptions(false),
            'statuses' => PurchaseBill::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return $this->formResponse();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $attachment = $request->file('attachment')?->store('purchase-bills', 'public');

        try {
            $bill = DB::transaction(function () use ($data, $attachment, $request) {
                [$totals, $items] = $this->totals($data);
                $bill = PurchaseBill::create([
                    ...$this->billValues($data, $totals),
                    'attachment_path' => $attachment,
                    'status' => 'unpaid',
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
                $bill->items()->createMany($items);

                return $bill;
            });
        } catch (\Throwable $exception) {
            if ($attachment) Storage::disk('public')->delete($attachment);
            throw $exception;
        }

        return to_route('purchase-bills.show', $bill)->with('success', 'Purchase bill created.');
    }

    public function show(PurchaseBill $purchaseBill): Response
    {
        $purchaseBill->load(['supplier:id,name,phone,email', 'project:id,name', 'items', 'payments' => fn ($query) => $query->latest('payment_date')->latest('id')])
            ->loadSum('payments as paid_amount', 'amount');

        return Inertia::render('PurchaseBills/Show', [
            'bill' => array_merge($this->billRow($purchaseBill), [
                'remarks' => $purchaseBill->remarks,
                'attachmentUrl' => $purchaseBill->attachment_path ? Storage::url($purchaseBill->attachment_path) : null,
                'items' => $purchaseBill->items->map(fn ($item) => [
                    'id' => $item->id, 'type' => $item->item_type, 'description' => $item->description,
                    'quantity' => (float) $item->quantity, 'unit' => $item->unit,
                    'unitPrice' => (float) $item->unit_price, 'lineTotal' => (float) $item->line_total,
                ]),
                'payments' => $purchaseBill->payments->map(fn ($payment) => [
                    'id' => $payment->id, 'date' => $payment->payment_date->format('d/m/Y'),
                    'amount' => (float) $payment->amount, 'method' => $payment->payment_method,
                    'reference' => $payment->reference, 'notes' => $payment->notes,
                    'receiptUrl' => $payment->receipt_path ? Storage::url($payment->receipt_path) : null,
                ]),
            ]),
            'paymentMethods' => \App\Models\SupplierPayment::METHODS,
        ]);
    }

    public function edit(PurchaseBill $purchaseBill): Response
    {
        $purchaseBill->load('items')->loadSum('payments as paid_amount', 'amount');

        return $this->formResponse($purchaseBill);
    }

    public function update(Request $request, PurchaseBill $purchaseBill): RedirectResponse
    {
        if ($purchaseBill->equipment()->exists()) {
            throw ValidationException::withMessages([
                'items' => 'This bill is linked to the Equipment Register. Remove those equipment links before editing the bill.',
            ]);
        }

        $data = $this->validated($request, $purchaseBill);
        $newAttachment = $request->file('attachment')?->store('purchase-bills', 'public');
        $oldAttachment = $purchaseBill->attachment_path;

        try {
            DB::transaction(function () use ($data, $newAttachment, $request, $purchaseBill) {
                $purchaseBill = PurchaseBill::query()->lockForUpdate()->withSum('payments as paid_amount', 'amount')->findOrFail($purchaseBill->id);
                [$totals, $items] = $this->totals($data);
                if ($totals['total_amount'] + 0.001 < (float) ($purchaseBill->paid_amount ?? 0)) {
                    throw ValidationException::withMessages(['discount' => 'The revised bill total cannot be lower than payments already recorded.']);
                }
                $purchaseBill->update([
                    ...$this->billValues($data, $totals),
                    'attachment_path' => $newAttachment ?: $purchaseBill->attachment_path,
                    'status' => $this->status((float) ($purchaseBill->paid_amount ?? 0), $totals['total_amount']),
                    'updated_by' => $request->user()?->id,
                ]);
                $purchaseBill->items()->delete();
                $purchaseBill->items()->createMany($items);
            });
        } catch (\Throwable $exception) {
            if ($newAttachment) Storage::disk('public')->delete($newAttachment);
            throw $exception;
        }

        if ($newAttachment && $oldAttachment) Storage::disk('public')->delete($oldAttachment);

        return to_route('purchase-bills.show', $purchaseBill)->with('success', 'Purchase bill updated.');
    }

    public function destroy(PurchaseBill $purchaseBill): RedirectResponse
    {
        if ($purchaseBill->payments()->exists() || $purchaseBill->equipment()->exists()) {
            return back()->withErrors(['bill' => 'A bill with payment or equipment records cannot be deleted.']);
        }
        $path = $purchaseBill->attachment_path;
        $purchaseBill->delete();
        if ($path) Storage::disk('public')->delete($path);

        return to_route('purchase-bills.index')->with('success', 'Purchase bill deleted.');
    }

    private function formResponse(?PurchaseBill $bill = null): Response
    {
        return Inertia::render('PurchaseBills/Form', [
            'bill' => $bill ? [
                'id' => $bill->id, 'supplierId' => $bill->supplier_id, 'projectId' => $bill->project_id,
                'billNumber' => $bill->bill_number, 'billDate' => $bill->bill_date->toDateString(),
                'dueDate' => $bill->due_date?->toDateString(), 'discount' => (float) $bill->discount,
                'vatRate' => (float) $bill->vat_rate, 'remarks' => $bill->remarks,
                'attachmentUrl' => $bill->attachment_path ? Storage::url($bill->attachment_path) : null,
                'paidAmount' => (float) ($bill->paid_amount ?? 0),
                'items' => $bill->items->map(fn ($item) => [
                    'item_type' => $item->item_type, 'description' => $item->description,
                    'quantity' => (float) $item->quantity, 'unit' => $item->unit,
                    'unit_price' => (float) $item->unit_price,
                ]),
            ] : null,
            'suppliers' => $this->supplierOptions(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function validated(Request $request, ?PurchaseBill $bill = null): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'bill_number' => ['required', 'string', 'max:255', Rule::unique('purchase_bills')->where('supplier_id', $request->input('supplier_id'))->ignore($bill)],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'discount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_type' => ['required', Rule::in(['material', 'equipment'])],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
    }

    private function totals(array $data): array
    {
        $items = collect($data['items'])->map(function ($item) {
            $lineTotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
            return [...$item, 'unit' => $item['unit'] ?: null, 'line_total' => $lineTotal];
        })->all();
        $subtotal = round((float) collect($items)->sum('line_total'), 2);
        $discount = round((float) $data['discount'], 2);
        if ($discount > $subtotal) throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the subtotal.']);
        $vatAmount = round(($subtotal - $discount) * ((float) $data['vat_rate'] / 100), 2);
        return [[
            'subtotal' => $subtotal, 'discount' => $discount, 'vat_rate' => round((float) $data['vat_rate'], 2),
            'vat_amount' => $vatAmount, 'total_amount' => round($subtotal - $discount + $vatAmount, 2),
        ], $items];
    }

    private function billValues(array $data, array $totals): array
    {
        return [
            'supplier_id' => $data['supplier_id'], 'project_id' => $data['project_id'] ?? null,
            'bill_number' => $data['bill_number'], 'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null, ...$totals, 'remarks' => $data['remarks'] ?? null,
        ];
    }

    private function supplierOptions(bool $activeOnly = true)
    {
        return Supplier::query()->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->get(['id', 'name', 'payment_terms_days']);
    }

    private function billRow(PurchaseBill $bill): array
    {
        $paid = (float) ($bill->paid_amount ?? 0);
        return [
            'id' => $bill->id, 'supplierId' => $bill->supplier_id, 'supplierName' => $bill->supplier?->name,
            'projectName' => $bill->project?->name, 'billNumber' => $bill->bill_number,
            'billDate' => $bill->bill_date->format('d/m/Y'), 'dueDate' => $bill->due_date?->format('d/m/Y'),
            'subtotal' => (float) $bill->subtotal, 'discount' => (float) $bill->discount,
            'vatRate' => (float) $bill->vat_rate, 'vatAmount' => (float) $bill->vat_amount,
            'total' => (float) $bill->total_amount, 'paid' => $paid,
            'balance' => max(0, (float) $bill->total_amount - $paid),
            'status' => $bill->status, 'statusLabel' => PurchaseBill::STATUSES[$bill->status] ?? $bill->status,
        ];
    }

    private function status(float $paid, float $total): string
    {
        if ($paid <= 0) return 'unpaid';
        return $paid + 0.001 >= $total ? 'paid' : 'partial';
    }
}
