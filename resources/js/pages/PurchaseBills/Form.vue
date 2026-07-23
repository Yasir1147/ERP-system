<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ bill: any | null; suppliers: any[]; projects: any[] }>();
const querySupplier = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('supplier_id') ?? '';
const form = useForm({
    supplier_id: props.bill?.supplierId ?? querySupplier,
    project_id: props.bill?.projectId ?? '',
    bill_number: props.bill?.billNumber ?? '',
    bill_date: props.bill?.billDate ?? new Date().toISOString().slice(0, 10),
    due_date: props.bill?.dueDate ?? '',
    discount: props.bill?.discount ?? 0,
    vat_rate: props.bill?.vatRate ?? 5,
    remarks: props.bill?.remarks ?? '',
    attachment: null as File | null,
    items: props.bill?.items?.length ? props.bill.items : [{ item_type: 'material', description: '', quantity: 1, unit: '', unit_price: 0 }],
});
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase Bills', href: '/purchase-bills' },
    { title: props.bill ? 'Edit Bill' : 'New Bill', href: props.bill ? `/purchase-bills/${props.bill.id}/edit` : '/purchase-bills/create' },
];
const subtotal = computed(() => form.items.reduce((sum: any, item: any) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0));
const taxable = computed(() => Math.max(0, subtotal.value - (Number(form.discount) || 0)));
const vat = computed(() => (taxable.value * (Number(form.vat_rate) || 0)) / 100);
const total = computed(() => taxable.value + vat.value);
const money = (v: number) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(v);
const addItem = () => form.items.push({ item_type: 'material', description: '', quantity: 1, unit: '', unit_price: 0 });
const removeItem = (i: number) => {
    if (form.items.length > 1) form.items.splice(i, 1);
};
const submit = () => {
    if (props.bill) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/purchase-bills/${props.bill.id}`, { forceFormData: true });
    } else form.post('/purchase-bills', { forceFormData: true });
};
</script>
<template>
    <Head :title="bill ? 'Edit Purchase Bill' : 'New Purchase Bill'" /><AppLayout :breadcrumbs="breadcrumbs"
        ><form class="purchase-bill-module flex min-w-0 flex-1 flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ bill ? 'Edit Purchase Bill' : 'New Purchase Bill' }}</h1>
                    <p class="text-sm text-muted-foreground">Amounts and VAT are recalculated and validated by the server.</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child type="button" variant="outline"><Link href="/purchase-bills">Cancel</Link></Button
                    ><Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Bill' }}</Button>
                </div>
            </div>
            <section class="rounded-lg border bg-card">
                <div class="border-b p-4 font-semibold">Bill Information</div>
                <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="grid gap-1.5 xl:col-span-2">
                        <Label>Supplier *</Label
                        ><select v-model="form.supplier_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Select supplier</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select
                        ><InputError :message="form.errors.supplier_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Bill Number *</Label><Input v-model="form.bill_number" /><InputError :message="form.errors.bill_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Project</Label
                        ><select v-model="form.project_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">No project</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Bill Date *</Label><Input v-model="form.bill_date" type="date" /><InputError :message="form.errors.bill_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Due Date</Label><Input v-model="form.due_date" type="date" /><InputError :message="form.errors.due_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Discount (AED)</Label><Input v-model.number="form.discount" type="number" min="0" step="0.01" /><InputError
                            :message="form.errors.discount"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>VAT Rate (%)</Label><Input v-model.number="form.vat_rate" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Invoice Attachment</Label
                        ><Input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            @input="form.attachment = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        /><a v-if="bill?.attachmentUrl" :href="bill.attachmentUrl" target="_blank" class="text-xs text-primary hover:underline"
                            >View current attachment</a
                        ><InputError :message="form.errors.attachment" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Remarks</Label
                        ><textarea v-model="form.remarks" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                </div>
            </section>
            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="font-semibold">Bill Items</h2>
                        <p class="text-xs text-muted-foreground">Mark equipment lines so they can be linked to the Equipment Register.</p>
                    </div>
                    <Button type="button" size="sm" variant="outline" @click="addItem"><Plus class="size-4" />Add Item</Button>
                </div>
                <InputError class="px-4 pt-3" :message="form.errors.items" />
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="p-3">Type</th>
                                <th class="p-3">Description</th>
                                <th class="p-3">Quantity</th>
                                <th class="p-3">Unit</th>
                                <th class="p-3">Unit Price</th>
                                <th class="p-3 text-right">Line Total</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(item, i) in form.items" :key="i">
                                <td class="p-3">
                                    <select v-model="item.item_type" class="h-10 w-full rounded-md border border-input bg-background px-2">
                                        <option value="material">Material</option>
                                        <option value="equipment">Equipment</option>
                                    </select>
                                </td>
                                <td class="p-3">
                                    <Input v-model="item.description" /><InputError :message="form.errors[`items.${i}.description`]" />
                                </td>
                                <td class="p-3"><Input v-model.number="item.quantity" type="number" min="0.001" step="0.001" /></td>
                                <td class="p-3"><Input v-model="item.unit" placeholder="pcs, kg, m" /></td>
                                <td class="p-3"><Input v-model.number="item.unit_price" type="number" min="0" step="0.01" /></td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ money((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)) }}
                                </td>
                                <td class="p-3">
                                    <Button type="button" size="icon" variant="ghost" :disabled="form.items.length === 1" @click="removeItem(i)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="ml-auto grid max-w-md gap-2 border-t p-4 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal</span><strong>{{ money(subtotal) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Discount</span><span>- {{ money(Number(form.discount) || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>VAT</span><span>{{ money(vat) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base">
                        <strong>Total</strong><strong>{{ money(total) }}</strong>
                    </div>
                    <div v-if="bill?.paidAmount" class="flex justify-between text-green-700">
                        <span>Already Paid</span><strong>{{ money(bill.paidAmount) }}</strong>
                    </div>
                </div>
            </section>
        </form></AppLayout
    >
</template>
