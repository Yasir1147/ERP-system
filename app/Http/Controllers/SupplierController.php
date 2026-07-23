<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $active = (string) $request->query('active', '');
        $perPage = in_array((int) $request->query('per_page', 10), [10, 15, 25, 50], true)
            ? (int) $request->query('per_page', 10) : 10;

        $suppliers = Supplier::query()
            ->withSum('bills as purchases_total', 'total_amount')
            ->withSum('payments as payments_total', 'amount')
            ->withCount(['bills', 'equipment'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('trn', 'like', "%{$search}%");
            }))
            ->when($active !== '', fn ($query) => $query->where('is_active', $active === '1'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => $this->supplierRow($supplier));

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers->items(),
            'pagination' => [
                'currentPage' => $suppliers->currentPage(), 'lastPage' => $suppliers->lastPage(),
                'perPage' => $suppliers->perPage(), 'total' => $suppliers->total(),
                'from' => $suppliers->firstItem(), 'to' => $suppliers->lastItem(),
            ],
            'filters' => ['search' => $search, 'active' => $active, 'perPage' => $perPage],
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        $supplier->loadSum('bills as purchases_total', 'total_amount')
            ->loadSum('payments as payments_total', 'amount');

        $bills = $supplier->bills()
            ->withSum('payments as paid_amount', 'amount')
            ->latest('bill_date')
            ->latest('id')
            ->get()
            ->map(fn (PurchaseBill $bill) => [
                'id' => $bill->id,
                'billNumber' => $bill->bill_number,
                'billDate' => $bill->bill_date->format('d/m/Y'),
                'dueDate' => $bill->due_date?->format('d/m/Y'),
                'total' => (float) $bill->total_amount,
                'paid' => (float) ($bill->paid_amount ?? 0),
                'balance' => max(0, (float) $bill->total_amount - (float) ($bill->paid_amount ?? 0)),
                'status' => $bill->status,
            ]);

        $payments = $supplier->payments()
            ->with('bill:id,bill_number')
            ->latest('payment_date')
            ->latest('id')
            ->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'billNumber' => $payment->bill?->bill_number,
                'date' => $payment->payment_date->format('d/m/Y'),
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'reference' => $payment->reference,
            ]);

        return Inertia::render('Suppliers/Show', [
            'supplier' => array_merge($this->supplierRow($supplier), [
                'address' => $supplier->address,
                'notes' => $supplier->notes,
            ]),
            'bills' => $bills,
            'payments' => $payments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request) + ['created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);

        return back()->with('success', 'Supplier created.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier) + ['updated_by' => $request->user()?->id]);

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->bills()->exists() || $supplier->payments()->exists() || $supplier->equipment()->exists()) {
            return back()->withErrors(['supplier' => 'This supplier has financial or equipment records. Mark it inactive instead of deleting it.']);
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'category' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'trn' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'trn')->ignore($supplier)],
            'address' => ['nullable', 'string', 'max:2000'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ], [], ['trn' => 'TRN']);
    }

    private function supplierRow(Supplier $supplier): array
    {
        $purchases = (float) ($supplier->purchases_total ?? 0);
        $payments = (float) ($supplier->payments_total ?? 0);
        $opening = (float) $supplier->opening_balance;

        return [
            'id' => $supplier->id, 'name' => $supplier->name, 'category' => $supplier->category,
            'contactPerson' => $supplier->contact_person, 'email' => $supplier->email,
            'phone' => $supplier->phone, 'trn' => $supplier->trn, 'address' => $supplier->address,
            'paymentTermsDays' => $supplier->payment_terms_days, 'openingBalance' => $opening,
            'notes' => $supplier->notes, 'isActive' => $supplier->is_active,
            'billCount' => (int) ($supplier->bills_count ?? 0),
            'equipmentCount' => (int) ($supplier->equipment_count ?? 0),
            'purchasesTotal' => $purchases, 'paymentsTotal' => $payments,
            'outstanding' => max(0, $opening + $purchases - $payments),
        ];
    }
}
