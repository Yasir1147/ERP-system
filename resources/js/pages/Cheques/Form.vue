<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Printer, Save, UserPlus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { amountToWords, splitPreviewText } from './amountToWords';
import ChequePreview from './components/ChequePreview.vue';
import VoucherPreview from './components/VoucherPreview.vue';
import type { ChequeFormatOption, ChequePartyOption } from './types';

interface StoredCheque {
    id: number;
    cheque_format_id: string;
    cheque_party_id: string;
    cheque_number: string | null;
    cheque_date: string;
    issued_date: string | null;
    amount: number;
    fils_on_second_line: boolean;
    payee_name: string;
    account_payee: boolean;
    signature_text: string | null;
    label_1_text: string | null;
    label_2_text: string | null;
    voucher_number: string | null;
    remarks: string | null;
    purpose: string | null;
    received_by: string | null;
    receiver_id: string | null;
    receiver_mobile: string | null;
    prepared_by: string | null;
    checked_by: string | null;
    approved_by: string | null;
}

const props = defineProps<{
    cheque: StoredCheque | null;
    formats: ChequeFormatOption[];
    parties: ChequePartyOption[];
    defaultPreparedBy: string | null;
    defaultIssuedDate: string;
}>();
const source = props.cheque;
const editing = computed(() => Boolean(source));
const showPartyForm = ref(props.parties.length === 0);
const allowNavigation = ref(false);

const form = useForm({
    cheque_format_id: source?.cheque_format_id ?? '',
    cheque_party_id: source?.cheque_party_id ?? '',
    cheque_number: source?.cheque_number ?? '',
    cheque_date: source?.cheque_date ?? new Date().toISOString().slice(0, 10),
    issued_date: source?.issued_date ?? props.defaultIssuedDate,
    amount: Number(source?.amount ?? 0),
    fils_on_second_line: source?.fils_on_second_line ?? false,
    payee_name: source?.payee_name ?? '',
    account_payee: source?.account_payee ?? true,
    signature_text: source?.signature_text ?? '',
    label_1_text: source?.label_1_text ?? '',
    label_2_text: source?.label_2_text ?? '',
    voucher_number: source?.voucher_number ?? '',
    remarks: source?.remarks ?? '',
    purpose: source?.purpose ?? '',
    received_by: source?.received_by ?? '',
    receiver_id: source?.receiver_id ?? '',
    receiver_mobile: source?.receiver_mobile ?? '',
    prepared_by: source?.prepared_by ?? props.defaultPreparedBy ?? '',
    checked_by: source?.checked_by ?? '',
    approved_by: source?.approved_by ?? '',
});

const partyForm = useForm({
    name: '',
    contact_person: '',
    email: '',
    mobile: '',
    phone: '',
    fax: '',
    address: '',
    remarks: '',
    is_active: true,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cheques', href: '/cheques' },
    { title: editing.value ? 'Edit Cheque' : 'Prepare Cheque', href: editing.value ? `/cheques/${source?.id}/edit` : '/cheques/create' },
];

const selectedFormat = computed(() => props.formats.find((format) => String(format.id) === form.cheque_format_id) ?? null);
const selectedParty = computed(() => props.parties.find((party) => String(party.id) === form.cheque_party_id) ?? null);
const words = computed(() => amountToWords(Number(form.amount) || 0));

const amountWordLines = computed<[string, string]>(() => {
    const format = selectedFormat.value;
    if (!format) return ['', ''];

    if (form.fils_on_second_line && words.value.includes(' And Fils ')) {
        const [wholeWords, filsWords] = words.value.split(' And Fils ', 2);

        return [`${format.amountWordsPrefix ?? ''} ${wholeWords}`.trim(), `And Fils ${filsWords} ${format.amountWordsSuffix ?? ''}`.trim()];
    }

    return splitPreviewText(`${format.amountWordsPrefix ?? ''} ${words.value} ${format.amountWordsSuffix ?? ''}`, format.amountWordsMaxLength);
});

const formattedDate = computed(() => {
    const [year, month, day] = form.cheque_date.split('-');
    if (!year || !month || !day) return '';
    if (selectedFormat.value?.dateFormat === 'MM/DD/YYYY') return `${month}/${day}/${year}`;
    if (selectedFormat.value?.dateFormat === 'DD-MM-YYYY') return `${day}-${month}-${year}`;
    if (selectedFormat.value?.dateFormat === 'YYYY-MM-DD') return `${year}-${month}-${day}`;
    return `${day}/${month}/${year}`;
});

const previewValues = computed<Record<string, string>>(() => {
    const format = selectedFormat.value;
    if (!format) return {};
    const [partyOne, partyTwo] = splitPreviewText(
        `${format.partyNamePrefix ?? ''} ${form.payee_name} ${format.partyNameSuffix ?? ''}`,
        format.partyNameMaxLength,
    );
    const [wordsOne, wordsTwo] = amountWordLines.value;
    return {
        party_name_1: partyOne,
        party_name_2: partyTwo,
        amount_words_1: wordsOne,
        amount_words_2: wordsTwo,
        amount_figures: `${format.amountFiguresPrefix ?? ''} ${(Number(form.amount) || 0).toFixed(2)} ${format.amountFiguresSuffix ?? ''}`.trim(),
        cheque_date: formattedDate.value,
        account_payee: form.account_payee ? (format.accountPayeeText ?? '') : '',
        label_1: form.label_1_text,
        label_2: form.label_2_text,
        signature: form.signature_text,
    };
});

watch(
    () => form.cheque_format_id,
    (value, oldValue) => {
        const format = props.formats.find((item) => String(item.id) === value);
        if (!format || (source && oldValue === undefined)) return;
        if (!source) form.cheque_number = format.nextChequeNumber === null ? '' : String(format.nextChequeNumber);
        form.account_payee = true;
        form.signature_text = format.signatureText ?? '';
        form.label_1_text = format.label1Text ?? '';
        form.label_2_text = format.label2Text ?? '';
    },
    { immediate: true },
);

watch(
    () => form.cheque_party_id,
    (value, oldValue) => {
        const party = props.parties.find((item) => String(item.id) === value);
        if (party && (!source || oldValue !== undefined)) form.payee_name = party.name;
    },
    { immediate: true },
);

const addParty = () => {
    const partyName = partyForm.name.trim();
    if (!partyName) return;
    partyForm.name = partyName;
    partyForm.post('/cheque-parties', {
        preserveScroll: true,
        errorBag: 'party',
        onSuccess: () => {
            const party = props.parties.find((item) => item.name.toLocaleLowerCase() === partyName.toLocaleLowerCase());
            if (party) form.cheque_party_id = String(party.id);
            form.payee_name = partyName;
            partyForm.reset();
            showPartyForm.value = false;
        },
    });
};

const submit = () => {
    allowNavigation.value = true;
    const options = {
        preserveScroll: true,
        onError: () => {
            allowNavigation.value = false;
        },
    };
    if (source) form.put(`/cheques/${source.id}`, options);
    else form.post('/cheques', options);
};

const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!form.isDirty || allowNavigation.value) return;
    event.preventDefault();
    event.returnValue = '';
};

let removeNavigationListener: (() => void) | null = null;
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    removeNavigationListener = router.on(
        'before',
        () => !form.isDirty || allowNavigation.value || window.confirm('You have unsaved cheque changes. Leave this page?'),
    );
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    removeNavigationListener?.();
});
</script>

<template>
    <Head :title="editing ? 'Edit Cheque' : 'Prepare Cheque'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="cheque-transaction-module flex h-full min-w-0 flex-1 flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">{{ editing ? 'Edit Cheque' : 'Prepare New Cheque' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Select a format and party, verify the preview, then save before printing.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child type="button" variant="outline"
                        ><Link href="/cheques"><ArrowLeft class="size-4" />Back</Link></Button
                    ><Button v-if="source" as-child type="button" variant="outline"
                        ><a :href="`/cheques/${source.id}/print`" target="_blank"><Printer class="size-4" />Cheque Print / PDF</a></Button
                    ><Button v-if="source" as-child type="button" variant="outline"
                        ><a :href="`/cheques/${source.id}/voucher`" target="_blank"><Printer class="size-4" />Voucher Print / PDF</a></Button
                    ><Button type="submit" :disabled="form.processing"
                        ><Save class="size-4" />{{ form.processing ? 'Saving...' : 'Save Cheque' }}</Button
                    >
                </div>
            </div>

            <div
                v-if="Object.values(form.errors)[0]"
                class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                {{ Object.values(form.errors)[0] }}
            </div>

            <section class="rounded-lg border bg-card shadow-sm">
                <div class="border-b p-4"><h2 class="font-medium">Cheque Information</h2></div>
                <div class="grid gap-4 p-4 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="cheque-format">Cheque Template *</Label
                        ><select
                            id="cheque-format"
                            v-model="form.cheque_format_id"
                            :disabled="editing"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Select cheque format</option>
                            <option v-for="format in formats" :key="format.id" :value="String(format.id)">
                                {{ format.name }}{{ format.bankName ? ` - ${format.bankName}` : '' }}
                            </option></select
                        ><InputError :message="form.errors.cheque_format_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="cheque-party">Party *</Label
                            ><button type="button" class="text-xs font-medium text-primary hover:underline" @click="showPartyForm = true">
                                <Plus class="mr-1 inline size-3" />Add Party
                            </button>
                        </div>
                        <select
                            id="cheque-party"
                            v-model="form.cheque_party_id"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Select party</option>
                            <option v-for="party in parties" :key="party.id" :value="String(party.id)">{{ party.name }}</option></select
                        ><InputError :message="form.errors.cheque_party_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Payee Name *</Label><Input v-model="form.payee_name" maxlength="255" /><InputError :message="form.errors.payee_name" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Cheque #</Label>
                        <Input :model-value="form.cheque_number" readonly class="bg-muted/30 font-medium tabular-nums" />
                        <p v-if="selectedFormat && selectedFormat.nextChequeNumber === null && !editing" class="text-xs text-destructive">
                            Configure the starting cheque number in Cheque Formats.
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Date *</Label><Input v-model="form.cheque_date" type="date" /><InputError :message="form.errors.cheque_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Amount *</Label><Input v-model.number="form.amount" type="number" min="0.01" step="0.01" /><InputError
                            :message="form.errors.amount"
                        />
                    </div>
                    <div class="grid gap-1.5 lg:col-span-2">
                        <Label>Amount in Words</Label>
                        <textarea
                            :value="form.fils_on_second_line ? amountWordLines.filter(Boolean).join('\n') : words"
                            readonly
                            rows="2"
                            class="resize-none rounded-md border border-input bg-muted/30 px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid gap-1.5"><Label>Voucher Number</Label><Input v-model="form.voucher_number" /></div>
                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm"
                        ><input v-model="form.account_payee" type="checkbox" class="size-4 rounded border-input" />Print A/C Payee text</label
                    >
                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                        <input v-model="form.fils_on_second_line" type="checkbox" class="size-4 rounded border-input" />
                        Print Fils on separate second line
                    </label>
                    <div class="grid gap-1.5"><Label>Signature Placeholder</Label><Input v-model="form.signature_text" /></div>
                    <div class="grid gap-1.5"><Label>Additional Text 1</Label><Input v-model="form.label_1_text" /></div>
                    <div class="grid gap-1.5"><Label>Additional Text 2</Label><Input v-model="form.label_2_text" /></div>
                    <div class="grid gap-1.5 lg:col-span-2">
                        <Label>Remarks</Label
                        ><textarea v-model="form.remarks" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div v-if="selectedParty" class="rounded-md border bg-muted/20 p-3 text-xs text-muted-foreground">
                        <div class="font-medium text-foreground">Selected Party Details</div>
                        <div class="mt-1">
                            {{ selectedParty.contactPerson || 'No contact person' }} · {{ selectedParty.mobile || selectedParty.phone || 'No phone' }}
                        </div>
                    </div>
                </div>
            </section>

            <ChequePreview
                v-if="selectedFormat"
                :width-mm="selectedFormat.chequeWidthMm"
                :height-mm="selectedFormat.chequeHeightMm"
                :fields="selectedFormat.fields"
                :values="previewValues"
                :background-image-url="selectedFormat.backgroundImageUrl"
                :logo-image-url="selectedFormat.logoImageUrl"
            />
            <div v-else class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                Select a cheque format to load its template and preview.
            </div>

            <section class="rounded-lg border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h2 class="font-medium">Payment Voucher Information</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Cheque number, dates, amount, words and beneficiary are copied automatically into the voucher.
                    </p>
                </div>
                <div class="grid gap-4 p-4 md:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label>Issued Date *</Label><Input v-model="form.issued_date" type="date" /><InputError :message="form.errors.issued_date" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Purpose</Label><Input v-model="form.purpose" placeholder="e.g. Payment for project or invoice" />
                    </div>
                    <div class="grid gap-1.5"><Label>Received By</Label><Input v-model="form.received_by" /></div>
                    <div class="grid gap-1.5"><Label>Receiver ID</Label><Input v-model="form.receiver_id" /></div>
                    <div class="grid gap-1.5"><Label>Receiver Mobile</Label><Input v-model="form.receiver_mobile" /></div>
                    <div class="grid gap-1.5"><Label>Prepared By</Label><Input v-model="form.prepared_by" /></div>
                    <div class="grid gap-1.5"><Label>Checked By</Label><Input v-model="form.checked_by" /></div>
                    <div class="grid gap-1.5"><Label>Approved By</Label><Input v-model="form.approved_by" /></div>
                </div>
            </section>

            <VoucherPreview
                :cheque-number="form.cheque_number"
                :issued-date="form.issued_date"
                :cheque-date="form.cheque_date"
                :amount="Number(form.amount) || 0"
                :amount-words="words"
                :beneficiary="form.payee_name"
                :purpose="form.purpose"
                :received-by="form.received_by"
                :receiver-id="form.receiver_id"
                :receiver-mobile="form.receiver_mobile"
                :prepared-by="form.prepared_by"
                :checked-by="form.checked_by"
                :approved-by="form.approved_by"
            />

            <div class="sticky bottom-3 z-20 flex justify-end rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur">
                <Button type="submit" size="lg" :disabled="form.processing"
                    ><Save class="size-4" />{{ form.processing ? 'Saving...' : 'Save Cheque' }}</Button
                >
            </div>
        </form>

        <Dialog v-model:open="showPartyForm">
            <DialogScrollContent class="w-[95vw] max-w-4xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><UserPlus class="size-5" />Add Party</DialogTitle>
                    <DialogDescription>Add the party details, then select it automatically for this cheque.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 py-2 md:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1"><Label>Party Name *</Label><Input v-model="partyForm.name" /></div>
                    <div class="grid gap-1"><Label>Contact Person</Label><Input v-model="partyForm.contact_person" /></div>
                    <div class="grid gap-1"><Label>Email</Label><Input v-model="partyForm.email" type="email" /></div>
                    <div class="grid gap-1"><Label>Mobile</Label><Input v-model="partyForm.mobile" /></div>
                    <div class="grid gap-1"><Label>Phone</Label><Input v-model="partyForm.phone" /></div>
                    <div class="grid gap-1"><Label>Fax</Label><Input v-model="partyForm.fax" /></div>
                    <div class="grid gap-1 md:col-span-2"><Label>Address</Label><Input v-model="partyForm.address" /></div>
                    <div class="grid gap-1"><Label>Remarks</Label><Input v-model="partyForm.remarks" /></div>
                    <InputError class="md:col-span-2 lg:col-span-3" :message="partyForm.errors.name || partyForm.errors.email" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showPartyForm = false">Cancel</Button>
                    <Button type="button" :disabled="partyForm.processing" @click="addParty">
                        {{ partyForm.processing ? 'Adding...' : 'Add and Select Party' }}
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </AppLayout>
</template>
