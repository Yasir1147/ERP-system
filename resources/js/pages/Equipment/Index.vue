<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Wrench, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps<{
    equipment: any[];
    pagination: any;
    filters: any;
    statuses: Record<string, string>;
    suppliers: any[];
    bills: any[];
    projects: any[];
    employees: any[];
}>();
const page = usePage();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const perPage = ref(String(props.filters.perPage));
const editingId = ref<number | null>(null);
const showForm = ref(false);
const form = useForm({
    supplier_id: '',
    purchase_bill_id: '',
    purchase_bill_item_id: '',
    assigned_project_id: '',
    assigned_employee_id: '',
    name: '',
    category: '',
    asset_code: '',
    brand: '',
    model: '',
    serial_number: '',
    purchase_date: '',
    purchase_cost: 0,
    warranty_expiry: '',
    status: 'available',
    notes: '',
    is_active: true,
});
const selectedBill = computed(() => props.bills.find((b: any) => String(b.id) === String(form.purchase_bill_id)) ?? null);
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Equipment', href: '/equipment' }];
const reset = () => {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};
const add = () => {
    reset();
    showForm.value = true;
};
const edit = (e: any) => {
    editingId.value = e.id;
    showForm.value = true;
    form.supplier_id = e.supplierId ?? '';
    form.purchase_bill_id = e.billId ?? '';
    form.purchase_bill_item_id = e.billItemId ?? '';
    form.assigned_project_id = e.projectId ?? '';
    form.assigned_employee_id = e.employeeId ?? '';
    form.name = e.name;
    form.category = e.category ?? '';
    form.asset_code = e.assetCode ?? '';
    form.brand = e.brand ?? '';
    form.model = e.model ?? '';
    form.serial_number = e.serialNumber ?? '';
    form.purchase_date = e.purchaseDate ?? '';
    form.purchase_cost = e.purchaseCost;
    form.warranty_expiry = e.warrantyExpiry ?? '';
    form.status = e.status;
    form.notes = e.notes ?? '';
    form.is_active = e.isActive;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const save = () => {
    const options = { preserveScroll: true, onSuccess: reset };
    editingId.value ? form.put(`/equipment/${editingId.value}`, options) : form.post('/equipment', options);
};
const remove = (e: any) => {
    if (window.confirm(`Delete equipment ${e.name}?`)) router.delete(`/equipment/${e.id}`, { preserveScroll: true });
};
const billChanged = () => {
    form.purchase_bill_item_id = '';
    if (selectedBill.value) {
        form.supplier_id = selectedBill.value.supplier_id;
        form.purchase_date = selectedBill.value.bill_date;
    }
};
const reload = (p = 1) =>
    router.get(
        '/equipment',
        { search: search.value || undefined, status: status.value || undefined, per_page: perPage.value, page: p },
        { preserveState: true, replace: true },
    );
const money = (v: number) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(v);
</script>
<template>
    <Head title="Equipment Register" /><AppLayout :breadcrumbs="breadcrumbs"
        ><div class="equipment-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Equipment Register</h1>
                    <p class="text-sm text-muted-foreground">Track purchased equipment, source bills, assets and assignments.</p>
                </div>
                <Button @click="add"><Plus class="size-4" />Add Equipment</Button>
            </div>
            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <section v-if="showForm" class="rounded-lg border bg-card">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="font-semibold">{{ editingId ? 'Edit Equipment' : 'Add Equipment' }}</h2>
                        <p class="text-xs text-muted-foreground">
                            Purchase bill linkage is optional, but only equipment-type bill lines can be selected.
                        </p>
                    </div>
                    <Button size="icon" variant="ghost" @click="reset"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="save">
                    <div class="grid gap-1.5 xl:col-span-2">
                        <Label>Name *</Label><Input v-model="form.name" /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5"><Label>Category</Label><Input v-model="form.category" placeholder="Power tool, vehicle, safety" /></div>
                    <div class="grid gap-1.5">
                        <Label>Asset Code</Label><Input v-model="form.asset_code" /><InputError :message="form.errors.asset_code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Supplier</Label
                        ><select v-model="form.supplier_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">No supplier</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Purchase Bill</Label
                        ><select
                            v-model="form.purchase_bill_id"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            @change="billChanged"
                        >
                            <option value="">No linked bill</option>
                            <option v-for="b in bills" :key="b.id" :value="b.id">{{ b.bill_number }} - {{ b.supplier.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5 xl:col-span-2">
                        <Label>Equipment Bill Item</Label
                        ><select
                            v-model="form.purchase_bill_item_id"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            :disabled="!selectedBill"
                        >
                            <option value="">No linked item</option>
                            <option v-for="i in selectedBill?.items || []" :key="i.id" :value="i.id">{{ i.description }}</option></select
                        ><InputError :message="form.errors.purchase_bill_item_id" />
                    </div>
                    <div class="grid gap-1.5"><Label>Brand</Label><Input v-model="form.brand" /></div>
                    <div class="grid gap-1.5"><Label>Model</Label><Input v-model="form.model" /></div>
                    <div class="grid gap-1.5">
                        <Label>Serial Number</Label><Input v-model="form.serial_number" /><InputError :message="form.errors.serial_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Status</Label
                        ><select v-model="form.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Purchase Date</Label><Input v-model="form.purchase_date" type="date" /></div>
                    <div class="grid gap-1.5">
                        <Label>Purchase Cost</Label><Input v-model.number="form.purchase_cost" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-1.5"><Label>Warranty Expiry</Label><Input v-model="form.warranty_expiry" type="date" /></div>
                    <div class="grid gap-1.5">
                        <Label>Assigned Project</Label
                        ><select v-model="form.assigned_project_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Not assigned</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5 xl:col-span-2">
                        <Label>Assigned Employee</Label
                        ><select v-model="form.assigned_employee_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Not assigned</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.code }} - {{ e.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5 xl:col-span-2"><Label>Notes</Label><Input v-model="form.notes" /></div>
                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm"
                        ><input v-model="form.is_active" type="checkbox" />Active asset</label
                    >
                    <div class="flex justify-end gap-2 md:col-span-2 xl:col-span-3">
                        <Button type="button" variant="outline" @click="reset">Cancel</Button
                        ><Button :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Equipment' }}</Button>
                    </div>
                </form>
            </section>
            <section class="overflow-hidden rounded-lg border bg-card">
                <form class="grid gap-3 border-b p-4 md:grid-cols-[1fr_200px_100px_auto]" @submit.prevent="reload()">
                    <div class="grid gap-1.5">
                        <Label>Search</Label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input
                                v-model="search"
                                class="pl-9"
                                placeholder="Name, asset code, serial, supplier"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Status</Label
                        ><select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All statuses</option>
                            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Rows</Label
                        ><select v-model="perPage" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="n in [10, 15, 25, 50]" :key="n">{{ n }}</option>
                        </select>
                    </div>
                    <Button class="self-end">Apply</Button>
                </form>
                <div v-if="!pagination.total" class="flex min-h-52 flex-col items-center justify-center gap-2 text-muted-foreground">
                    <Wrench class="size-9" />No equipment found.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Equipment</th>
                                <th class="p-3">Asset / Serial</th>
                                <th class="p-3">Supplier / Bill</th>
                                <th class="p-3">Assignment</th>
                                <th class="p-3 text-right">Cost</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="e in equipment" :key="e.id">
                                <td class="p-3">
                                    <div class="font-medium">{{ e.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ [e.brand, e.model, e.category].filter(Boolean).join(' · ') || '-' }}
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div>{{ e.assetCode || '-' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ e.serialNumber || '' }}</div>
                                </td>
                                <td class="p-3">
                                    <div>{{ e.supplierName || '-' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ e.billNumber ? `Bill ${e.billNumber}` : '' }}</div>
                                </td>
                                <td class="p-3">
                                    <div>{{ e.projectName || e.employeeName || '-' }}</div>
                                    <div v-if="e.projectName && e.employeeName" class="text-xs text-muted-foreground">{{ e.employeeName }}</div>
                                </td>
                                <td class="p-3 text-right">{{ money(e.purchaseCost) }}</td>
                                <td class="p-3">{{ e.statusLabel }}</td>
                                <td class="p-3">
                                    <div class="flex justify-end gap-1">
                                        <Button size="icon" variant="ghost" @click="edit(e)"><Pencil class="size-4" /></Button
                                        ><Button size="icon" variant="ghost" class="text-destructive" @click="remove(e)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination.lastPage > 1" class="flex justify-between border-t p-4 text-sm">
                    <span>{{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</span>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" :disabled="pagination.currentPage === 1" @click="reload(pagination.currentPage - 1)"
                            >Previous</Button
                        ><Button
                            size="sm"
                            variant="outline"
                            :disabled="pagination.currentPage === pagination.lastPage"
                            @click="reload(pagination.currentPage + 1)"
                            >Next</Button
                        >
                    </div>
                </div>
            </section>
        </div></AppLayout
    >
</template>
