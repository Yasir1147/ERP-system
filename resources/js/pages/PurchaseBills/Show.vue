<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Trash2 } from 'lucide-vue-next';
const props = defineProps<{ bill: any; paymentMethods: Record<string, string> }>();
const page = usePage();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase Bills', href: '/purchase-bills' },
    { title: props.bill.billNumber, href: `/purchase-bills/${props.bill.id}` },
];
const money = (v: number) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(v);
const form = useForm({
    payment_date: new Date().toISOString().slice(0, 10),
    amount: props.bill.balance,
    payment_method: 'bank',
    reference: '',
    receipt: null as File | null,
    notes: '',
});
const pay = () =>
    form.post(`/purchase-bills/${props.bill.id}/payments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('reference', 'receipt', 'notes'),
    });
const removePayment = (p: any) => {
    if (window.confirm(`Delete payment of ${money(p.amount)}?`)) router.delete(`/supplier-payments/${p.id}`, { preserveScroll: true });
};
</script>
<template>
    <Head :title="`Bill ${bill.billNumber}`" /><AppLayout :breadcrumbs="breadcrumbs"
        ><div class="purchase-bill-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Purchase Bill {{ bill.billNumber }}</h1>
                    <p class="text-sm text-muted-foreground">{{ bill.supplierName }} · {{ bill.billDate }}</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline"><Link href="/purchase-bills">Back</Link></Button
                    ><Button as-child
                        ><Link :href="`/purchase-bills/${bill.id}/edit`"><Pencil class="size-4" />Edit Bill</Link></Button
                    >
                </div>
            </div>
            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Bill Total</p>
                    <p class="mt-2 text-xl font-semibold">{{ money(bill.total) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Paid</p>
                    <p class="mt-2 text-xl font-semibold text-green-700">{{ money(bill.paid) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Outstanding</p>
                    <p class="mt-2 text-xl font-semibold text-amber-700">{{ money(bill.balance) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Status</p>
                    <p class="mt-2 text-xl font-semibold">{{ bill.statusLabel }}</p>
                </div>
            </section>
            <section class="rounded-lg border bg-card">
                <div class="grid gap-4 p-4 md:grid-cols-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Supplier</p>
                        <Link :href="`/suppliers/${bill.supplierId}`" class="font-medium hover:underline">{{ bill.supplierName }}</Link>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Project</p>
                        <p>{{ bill.projectName || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Due Date</p>
                        <p>{{ bill.dueDate || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Attachment</p>
                        <a
                            v-if="bill.attachmentUrl"
                            :href="bill.attachmentUrl"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-primary hover:underline"
                            >Open invoice <ExternalLink class="size-3" /></a
                        ><span v-else>-</span>
                    </div>
                </div>
                <div v-if="bill.remarks" class="border-t p-4 text-sm">{{ bill.remarks }}</div>
            </section>
            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b p-4 font-semibold">Bill Items</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[750px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Type</th>
                                <th class="p-3">Description</th>
                                <th class="p-3 text-right">Quantity</th>
                                <th class="p-3">Unit</th>
                                <th class="p-3 text-right">Unit Price</th>
                                <th class="p-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in bill.items" :key="item.id">
                                <td class="p-3 capitalize">{{ item.type }}</td>
                                <td class="p-3 font-medium">{{ item.description }}</td>
                                <td class="p-3 text-right">{{ item.quantity }}</td>
                                <td class="p-3">{{ item.unit || '-' }}</td>
                                <td class="p-3 text-right">{{ money(item.unitPrice) }}</td>
                                <td class="p-3 text-right">{{ money(item.lineTotal) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t">
                            <tr>
                                <td colspan="5" class="p-3 text-right">Subtotal</td>
                                <td class="p-3 text-right font-medium">{{ money(bill.subtotal) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="p-3 text-right">Discount</td>
                                <td class="p-3 text-right">- {{ money(bill.discount) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="p-3 text-right">VAT ({{ bill.vatRate }}%)</td>
                                <td class="p-3 text-right">{{ money(bill.vatAmount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
            <section v-if="bill.balance > 0" class="rounded-lg border bg-card">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Record Payment</h2>
                    <p class="text-xs text-muted-foreground">Partial payments are allowed; overpayments are blocked.</p>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="pay">
                    <div class="grid gap-1.5"><Label>Date *</Label><Input v-model="form.payment_date" type="date" /></div>
                    <div class="grid gap-1.5">
                        <Label>Amount *</Label
                        ><Input v-model.number="form.amount" type="number" min="0.01" :max="bill.balance" step="0.01" /><InputError
                            :message="form.errors.amount"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Method *</Label
                        ><select v-model="form.payment_method" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="(label, key) in paymentMethods" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Reference</Label><Input v-model="form.reference" /></div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Receipt</Label
                        ><Input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            @input="form.receipt = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                    </div>
                    <div class="grid gap-1.5"><Label>Notes</Label><Input v-model="form.notes" /></div>
                    <Button class="self-end" :disabled="form.processing">{{ form.processing ? 'Recording...' : 'Record Payment' }}</Button>
                </form>
            </section>
            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b p-4 font-semibold">Payments</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[750px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Method</th>
                                <th class="p-3">Reference</th>
                                <th class="p-3">Receipt</th>
                                <th class="p-3 text-right">Amount</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in bill.payments" :key="p.id">
                                <td class="p-3">{{ p.date }}</td>
                                <td class="p-3 capitalize">{{ p.method }}</td>
                                <td class="p-3">{{ p.reference || '-' }}</td>
                                <td class="p-3">
                                    <a v-if="p.receiptUrl" :href="p.receiptUrl" target="_blank" class="text-primary hover:underline">Open</a
                                    ><span v-else>-</span>
                                </td>
                                <td class="p-3 text-right">{{ money(p.amount) }}</td>
                                <td class="p-3 text-right">
                                    <Button size="icon" variant="ghost" class="text-destructive" @click="removePayment(p)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </td>
                            </tr>
                            <tr v-if="!bill.payments.length">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">No payments recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div></AppLayout
    >
</template>
