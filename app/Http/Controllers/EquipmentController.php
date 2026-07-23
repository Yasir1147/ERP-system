<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $perPage = in_array((int) $request->query('per_page', 10), [10, 15, 25, 50], true)
            ? (int) $request->query('per_page', 10) : 10;

        $rows = Equipment::query()
            ->with(['supplier:id,name', 'bill:id,bill_number,supplier_id', 'billItem:id,description,item_type', 'project:id,name', 'employee:id,code,name'])
            ->when(isset(Equipment::STATUSES[$status]), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('name')->paginate($perPage)->withQueryString()
            ->through(fn (Equipment $item) => [
                'id' => $item->id, 'name' => $item->name, 'category' => $item->category,
                'assetCode' => $item->asset_code, 'brand' => $item->brand, 'model' => $item->model,
                'serialNumber' => $item->serial_number, 'purchaseDate' => $item->purchase_date?->toDateString(),
                'purchaseCost' => (float) $item->purchase_cost, 'warrantyExpiry' => $item->warranty_expiry?->toDateString(),
                'status' => $item->status, 'statusLabel' => Equipment::STATUSES[$item->status] ?? $item->status,
                'notes' => $item->notes, 'isActive' => $item->is_active,
                'supplierId' => $item->supplier_id, 'supplierName' => $item->supplier?->name,
                'billId' => $item->purchase_bill_id, 'billNumber' => $item->bill?->bill_number,
                'billItemId' => $item->purchase_bill_item_id, 'billItemName' => $item->billItem?->description,
                'projectId' => $item->assigned_project_id, 'projectName' => $item->project?->name,
                'employeeId' => $item->assigned_employee_id,
                'employeeName' => $item->employee ? "{$item->employee->code} - {$item->employee->name}" : null,
            ]);

        return Inertia::render('Equipment/Index', [
            'equipment' => $rows->items(),
            'pagination' => [
                'currentPage' => $rows->currentPage(), 'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(), 'total' => $rows->total(),
                'from' => $rows->firstItem(), 'to' => $rows->lastItem(),
            ],
            'filters' => ['search' => $search, 'status' => $status, 'perPage' => $perPage],
            'statuses' => Equipment::STATUSES,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bills' => PurchaseBill::query()->with(['supplier:id,name', 'items' => fn ($query) => $query->where('item_type', 'equipment')])
                ->latest('bill_date')->get(['id', 'supplier_id', 'bill_number', 'bill_date']),
            'projects' => Project::query()->where('status', 'ongoing')->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Equipment::create($this->validated($request) + ['created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
        return back()->with('success', 'Equipment added.');
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $equipment->update($this->validated($request, $equipment) + ['updated_by' => $request->user()?->id]);
        return back()->with('success', 'Equipment updated.');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        $equipment->delete();
        return back()->with('success', 'Equipment deleted.');
    }

    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchase_bill_id' => ['nullable', 'integer', 'exists:purchase_bills,id'],
            'purchase_bill_item_id' => ['nullable', 'integer', 'exists:purchase_bill_items,id'],
            'assigned_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'asset_code' => ['nullable', 'string', 'max:100', Rule::unique('equipment')->ignore($equipment)],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('equipment')->ignore($equipment)],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'warranty_expiry' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'status' => ['required', Rule::in(array_keys(Equipment::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! empty($data['purchase_bill_item_id'])) {
            $validItem = \App\Models\PurchaseBillItem::query()
                ->whereKey($data['purchase_bill_item_id'])
                ->where('purchase_bill_id', $data['purchase_bill_id'] ?? 0)
                ->where('item_type', 'equipment')->exists();
            if (! $validItem) {
                throw \Illuminate\Validation\ValidationException::withMessages(['purchase_bill_item_id' => 'Select an equipment item from the selected purchase bill.']);
            }
        }

        return $data;
    }
}
