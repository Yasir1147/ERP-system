<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
const props = defineProps<{ supplier: any; bills: any[]; payments: any[] }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suppliers', href: '/suppliers' },
    { title: props.supplier.name, href: `/suppliers/${props.supplier.id}` },
];
const money = (v: number) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(v);
</script>
<template>
    <Head :title="supplier.name" /><AppLayout :breadcrumbs="breadcrumbs"
        ><div class="supplier-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ supplier.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ supplier.category || 'Supplier ledger' }}</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline"><Link href="/suppliers">Back</Link></Button
                    ><Button as-child><Link :href="`/purchase-bills/create?supplier_id=${supplier.id}`">New Bill</Link></Button>
                </div>
            </div>
            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Opening Balance</p>
                    <p class="mt-2 text-xl font-semibold">{{ money(supplier.openingBalance) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Purchases</p>
                    <p class="mt-2 text-xl font-semibold">{{ money(supplier.purchasesTotal) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Payments</p>
                    <p class="mt-2 text-xl font-semibold text-green-700">{{ money(supplier.paymentsTotal) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Outstanding</p>
                    <p class="mt-2 text-xl font-semibold text-amber-700">{{ money(supplier.outstanding) }}</p>
                </div>
            </section>
            <section class="rounded-lg border bg-card">
                <div class="border-b p-4"><h2 class="font-semibold">Purchase Bills</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Bill #</th>
                                <th class="p-3">Date</th>
                                <th class="p-3">Due</th>
                                <th class="p-3 text-right">Total</th>
                                <th class="p-3 text-right">Paid</th>
                                <th class="p-3 text-right">Balance</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="bill in bills" :key="bill.id">
                                <td class="p-3 font-medium">
                                    <Link class="hover:underline" :href="`/purchase-bills/${bill.id}`">{{ bill.billNumber }}</Link>
                                </td>
                                <td class="p-3">{{ bill.billDate }}</td>
                                <td class="p-3">{{ bill.dueDate || '-' }}</td>
                                <td class="p-3 text-right">{{ money(bill.total) }}</td>
                                <td class="p-3 text-right">{{ money(bill.paid) }}</td>
                                <td class="p-3 text-right">{{ money(bill.balance) }}</td>
                                <td class="p-3 capitalize">{{ bill.status }}</td>
                            </tr>
                            <tr v-if="!bills.length">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">No purchase bills yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="rounded-lg border bg-card">
                <div class="border-b p-4"><h2 class="font-semibold">Payment History</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[650px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Bill #</th>
                                <th class="p-3">Method</th>
                                <th class="p-3">Reference</th>
                                <th class="p-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="payment in payments" :key="payment.id">
                                <td class="p-3">{{ payment.date }}</td>
                                <td class="p-3">{{ payment.billNumber }}</td>
                                <td class="p-3 capitalize">{{ payment.method }}</td>
                                <td class="p-3">{{ payment.reference || '-' }}</td>
                                <td class="p-3 text-right">{{ money(payment.amount) }}</td>
                            </tr>
                            <tr v-if="!payments.length">
                                <td colspan="5" class="p-8 text-center text-muted-foreground">No payments recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div></AppLayout
    >
</template>
