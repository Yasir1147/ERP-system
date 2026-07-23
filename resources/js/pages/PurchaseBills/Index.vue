<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
const props = defineProps<{ bills: any[]; pagination: any; filters: any; suppliers: any[]; statuses: Record<string, string> }>();
const page = usePage();
const search = ref(props.filters.search);
const supplierId = ref(props.filters.supplierId);
const status = ref(props.filters.status);
const perPage = ref(String(props.filters.perPage));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Purchase Bills', href: '/purchase-bills' }];
const money = (v: number) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(v);
const reload = (p = 1) =>
    router.get(
        '/purchase-bills',
        {
            search: search.value || undefined,
            supplier_id: supplierId.value || undefined,
            status: status.value || undefined,
            per_page: perPage.value,
            page: p,
        },
        { preserveState: true, replace: true },
    );
const remove = (bill: any) => {
    if (window.confirm(`Delete purchase bill ${bill.billNumber}?`)) router.delete(`/purchase-bills/${bill.id}`);
};
const badge = (value: string) =>
    value === 'paid'
        ? 'border-green-600/30 bg-green-600/10 text-green-700'
        : value === 'partial'
          ? 'border-amber-600/30 bg-amber-600/10 text-amber-700'
          : 'border-red-600/30 bg-red-600/10 text-red-700';
</script>
<template>
    <Head title="Purchase Bills" /><AppLayout :breadcrumbs="breadcrumbs"
        ><div class="purchase-bill-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Purchase Bills</h1>
                    <p class="text-sm text-muted-foreground">Record supplier invoices, material and equipment items, VAT and payments.</p>
                </div>
                <Button as-child
                    ><Link href="/purchase-bills/create"><Plus class="size-4" />New Purchase Bill</Link></Button
                >
            </div>
            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.errors?.bill" class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                {{ page.props.errors.bill }}
            </div>
            <section class="overflow-hidden rounded-lg border bg-card shadow-sm">
                <form class="grid gap-3 border-b p-4 lg:grid-cols-[1fr_240px_180px_100px_auto]" @submit.prevent="reload()">
                    <div class="grid gap-1.5">
                        <Label>Search</Label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input
                                v-model="search"
                                class="pl-9"
                                placeholder="Bill number, supplier or remarks"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Supplier</Label
                        ><select v-model="supplierId" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All suppliers</option>
                            <option v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                        </select>
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
                <div v-if="!pagination.total" class="flex min-h-52 items-center justify-center text-sm text-muted-foreground">
                    No purchase bills found.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="p-3">Bill #</th>
                                <th class="p-3">Supplier</th>
                                <th class="p-3">Date</th>
                                <th class="p-3">Due</th>
                                <th class="p-3">Project</th>
                                <th class="p-3 text-right">Total</th>
                                <th class="p-3 text-right">Paid</th>
                                <th class="p-3 text-right">Balance</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="bill in bills" :key="bill.id" class="hover:bg-muted/30">
                                <td class="p-3 font-medium">{{ bill.billNumber }}</td>
                                <td class="p-3">{{ bill.supplierName }}</td>
                                <td class="p-3">{{ bill.billDate }}</td>
                                <td class="p-3">{{ bill.dueDate || '-' }}</td>
                                <td class="p-3">{{ bill.projectName || '-' }}</td>
                                <td class="p-3 text-right">{{ money(bill.total) }}</td>
                                <td class="p-3 text-right">{{ money(bill.paid) }}</td>
                                <td class="p-3 text-right font-medium">{{ money(bill.balance) }}</td>
                                <td class="p-3">
                                    <span class="rounded-full border px-2 py-1 text-xs" :class="badge(bill.status)">{{ bill.statusLabel }}</span>
                                </td>
                                <td class="p-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child size="icon" variant="ghost"
                                            ><Link :href="`/purchase-bills/${bill.id}`"><Eye class="size-4" /></Link></Button
                                        ><Button as-child size="icon" variant="ghost"
                                            ><Link :href="`/purchase-bills/${bill.id}/edit`"><Pencil class="size-4" /></Link></Button
                                        ><Button size="icon" variant="ghost" class="text-destructive" @click="remove(bill)"
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
