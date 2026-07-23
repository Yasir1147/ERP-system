<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2, Truck, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Supplier {
    id: number;
    name: string;
    category: string | null;
    contactPerson: string | null;
    email: string | null;
    phone: string | null;
    trn: string | null;
    address: string | null;
    paymentTermsDays: number;
    openingBalance: number;
    notes: string | null;
    isActive: boolean;
    billCount: number;
    equipmentCount: number;
    purchasesTotal: number;
    paymentsTotal: number;
    outstanding: number;
}
const props = defineProps<{
    suppliers: Supplier[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number; from: number | null; to: number | null };
    filters: { search: string; active: string; perPage: number };
}>();
const page = usePage();
const search = ref(props.filters.search);
const active = ref(props.filters.active);
const perPage = ref(String(props.filters.perPage));
const showForm = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({
    name: '',
    category: '',
    contact_person: '',
    email: '',
    phone: '',
    trn: '',
    address: '',
    payment_terms_days: 0,
    opening_balance: 0,
    notes: '',
    is_active: true,
});
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Suppliers', href: '/suppliers' }];
const money = (value: number) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(value);
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
const edit = (supplier: Supplier) => {
    editingId.value = supplier.id;
    showForm.value = true;
    form.name = supplier.name;
    form.category = supplier.category ?? '';
    form.contact_person = supplier.contactPerson ?? '';
    form.email = supplier.email ?? '';
    form.phone = supplier.phone ?? '';
    form.trn = supplier.trn ?? '';
    form.address = supplier.address ?? '';
    form.payment_terms_days = supplier.paymentTermsDays;
    form.opening_balance = supplier.openingBalance;
    form.notes = supplier.notes ?? '';
    form.is_active = supplier.isActive;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const save = () => {
    const options = { preserveScroll: true, onSuccess: reset };
    editingId.value ? form.put(`/suppliers/${editingId.value}`, options) : form.post('/suppliers', options);
};
const remove = (supplier: Supplier) => {
    if (window.confirm(`Delete supplier ${supplier.name}?`)) router.delete(`/suppliers/${supplier.id}`, { preserveScroll: true });
};
const reload = (number = 1) =>
    router.get(
        '/suppliers',
        {
            search: search.value.trim() || undefined,
            active: active.value || undefined,
            per_page: perPage.value,
            page: number,
        },
        { preserveState: true, replace: true },
    );
</script>

<template>
    <Head title="Suppliers" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="supplier-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Supplier Master</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Manage vendors, bills, payments and outstanding balances.</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline"><Link href="/purchase-bills/create">New Purchase Bill</Link></Button
                    ><Button @click="add"><Plus class="size-4" />Add Supplier</Button>
                </div>
            </div>
            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.errors?.supplier"
                class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                {{ page.props.errors.supplier }}
            </div>

            <section v-if="showForm" class="rounded-lg border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="font-medium">{{ editingId ? 'Edit Supplier' : 'Add Supplier' }}</h2>
                        <p class="text-xs text-muted-foreground">Supplier name is required. Opening balance is carried into the ledger.</p>
                    </div>
                    <Button size="icon" variant="ghost" @click="reset"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="save">
                    <div class="grid gap-1.5 xl:col-span-2">
                        <Label>Name *</Label><Input v-model="form.name" /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Category</Label><Input v-model="form.category" placeholder="General supplies, steel, tools" />
                    </div>
                    <div class="grid gap-1.5"><Label>Contact Person</Label><Input v-model="form.contact_person" /></div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label><Input v-model="form.email" type="email" /><InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-1.5"><Label>Phone</Label><Input v-model="form.phone" /></div>
                    <div class="grid gap-1.5"><Label>UAE TRN</Label><Input v-model="form.trn" /><InputError :message="form.errors.trn" /></div>
                    <div class="grid gap-1.5">
                        <Label>Payment Terms (days)</Label><Input v-model.number="form.payment_terms_days" type="number" min="0" max="365" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Opening Balance (AED)</Label><Input v-model.number="form.opening_balance" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Address</Label
                        ><textarea v-model="form.address" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Notes</Label
                        ><textarea v-model="form.notes" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm"
                        ><input v-model="form.is_active" type="checkbox" />Active supplier</label
                    >
                    <div class="flex justify-end gap-2 md:col-span-2 xl:col-span-3">
                        <Button type="button" variant="outline" @click="reset">Cancel</Button
                        ><Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Supplier' }}</Button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card shadow-sm">
                <form class="grid gap-3 border-b p-4 md:grid-cols-[1fr_180px_110px_auto]" @submit.prevent="reload()">
                    <div class="grid gap-1.5">
                        <Label>Search</Label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input
                                v-model="search"
                                class="pl-9"
                                placeholder="Supplier, contact, phone or TRN"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Status</Label
                        ><select v-model="active" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All suppliers</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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
                    <Truck class="size-9" />No suppliers found.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Supplier</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3 text-right">Purchases</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Outstanding</th>
                                <th class="px-4 py-3">Records</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="supplier in suppliers" :key="supplier.id" class="hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ supplier.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ supplier.category || supplier.trn || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ supplier.contactPerson || '-' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ supplier.phone || supplier.email || '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ money(supplier.purchasesTotal) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ money(supplier.paymentsTotal) }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ money(supplier.outstanding) }}</td>
                                <td class="px-4 py-3">{{ supplier.billCount }} bills · {{ supplier.equipmentCount }} equipment</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full border px-2 py-1 text-xs"
                                        :class="supplier.isActive ? 'border-green-600/30 bg-green-600/10 text-green-700' : ''"
                                        >{{ supplier.isActive ? 'Active' : 'Inactive' }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child size="icon" variant="ghost"
                                            ><Link :href="`/suppliers/${supplier.id}`"><Eye class="size-4" /></Link></Button
                                        ><Button size="icon" variant="ghost" @click="edit(supplier)"><Pencil class="size-4" /></Button
                                        ><Button size="icon" variant="ghost" class="text-destructive" @click="remove(supplier)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination.lastPage > 1" class="flex items-center justify-between border-t p-4 text-sm">
                    <span>Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</span>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="pagination.currentPage === 1" @click="reload(pagination.currentPage - 1)"
                            >Previous</Button
                        ><Button
                            variant="outline"
                            size="sm"
                            :disabled="pagination.currentPage === pagination.lastPage"
                            @click="reload(pagination.currentPage + 1)"
                            >Next</Button
                        >
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
