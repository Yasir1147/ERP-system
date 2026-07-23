<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, ImageIcon, Plus, Save, Trash2, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ChequeDesigner from './components/ChequeDesigner.vue';
import FieldSettingsTable from './components/FieldSettingsTable.vue';
import { clamp, createDefaultFields, roundMeasurement } from './designer';
import type { BankOption, ChequeFieldForm, ChequeFormatFormData, FieldDefinition, StoredChequeFormat } from './types';

const props = defineProps<{
    chequeFormat: StoredChequeFormat | null;
    banks: BankOption[];
    dateFormats: string[];
    fontFamilies: string[];
    fieldDefinitions: FieldDefinition[];
}>();

const editing = computed(() => Boolean(props.chequeFormat));
const showBankForm = ref(props.banks.length === 0);
const allowNavigation = ref(false);

const normalizeFields = (fields: ChequeFieldForm[]) =>
    fields.map((field) => ({
        ...field,
        x_position_mm: Number(field.x_position_mm),
        y_position_mm: Number(field.y_position_mm),
        width_mm: field.width_mm === null ? null : Number(field.width_mm),
        height_mm: field.height_mm === null ? null : Number(field.height_mm),
        font_size_pt: Number(field.font_size_pt),
        font_weight: Number(field.font_weight),
    }));

const source = props.chequeFormat;
const form = useForm<ChequeFormatFormData>({
    bank_id: source?.bank_id ?? '',
    name: source?.name ?? '',
    cheque_width_mm: Number(source?.cheque_width_mm ?? 200),
    cheque_height_mm: Number(source?.cheque_height_mm ?? 90),
    date_format: source?.date_format ?? 'DD/MM/YYYY',
    amount_figures_prefix: source?.amount_figures_prefix ?? '',
    amount_figures_suffix: source?.amount_figures_suffix ?? '',
    amount_words_prefix: source?.amount_words_prefix ?? '',
    amount_words_suffix: source?.amount_words_suffix ?? '',
    party_name_prefix: source?.party_name_prefix ?? '',
    party_name_suffix: source?.party_name_suffix ?? '',
    party_name_max_length: Number(source?.party_name_max_length ?? 60),
    amount_words_max_length: Number(source?.amount_words_max_length ?? 60),
    account_payee_text: source?.account_payee_text ?? 'A/C PAYEE ONLY',
    label_1_text: source?.label_1_text ?? '',
    label_2_text: source?.label_2_text ?? '',
    signature_text: source?.signature_text ?? 'Signature',
    version: source?.version ?? null,
    fields: source ? normalizeFields(source.fields) : createDefaultFields(props.fieldDefinitions),
});

watch(
    () => props.chequeFormat?.version,
    (version) => {
        if (version === undefined || version === null) return;

        form.version = Number(version);
        form.defaults('version', Number(version));
    },
    { immediate: true },
);

const bankForm = useForm({ name: '' });
const backgroundForm = useForm<{ background_image: File | null }>({ background_image: null });
const logoForm = useForm<{ logo_image: File | null }>({ logo_image: null });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cheque Formats', href: '/cheque-formats' },
    { title: editing.value ? 'Edit Format' : 'Add Format', href: editing.value ? `/cheque-formats/${source?.id}/edit` : '/cheque-formats/create' },
];

const firstFormError = computed(() => Object.values(form.errors)[0]);

const splitText = (text: string, maximum: number): [string, string] => {
    const clean = text.trim();
    if (clean.length <= maximum) return [clean, ''];

    const firstCandidate = clean.slice(0, maximum + 1);
    const splitAt = firstCandidate.lastIndexOf(' ');
    const safeIndex = splitAt > 0 ? splitAt : maximum;

    return [clean.slice(0, safeIndex).trim(), clean.slice(safeIndex).trim()];
};

const previewText = computed<Record<string, string>>(() => {
    const party = splitText(`${form.party_name_prefix} Party Name Line 1 ${form.party_name_suffix}`, Math.max(1, form.party_name_max_length));
    const words = splitText(`${form.amount_words_prefix} Five Hundred Only ${form.amount_words_suffix}`, Math.max(1, form.amount_words_max_length));
    const sampleDates: Record<string, string> = {
        'DD/MM/YYYY': '14/07/2026',
        'MM/DD/YYYY': '07/14/2026',
        'DD-MM-YYYY': '14-07-2026',
        'YYYY-MM-DD': '2026-07-14',
    };

    return {
        party_name_1: party[0],
        party_name_2: party[1],
        amount_words_1: words[0],
        amount_words_2: words[1],
        amount_figures: `${form.amount_figures_prefix} 500.00 ${form.amount_figures_suffix}`.trim(),
        cheque_date: sampleDates[form.date_format] ?? '14/07/2026',
        account_payee: form.account_payee_text,
        label_1: form.label_1_text,
        label_2: form.label_2_text,
        signature: form.signature_text,
    };
});

const updateField = (index: number, patch: Partial<ChequeFieldForm>) => {
    const current = form.fields[index];
    const updated = { ...current, ...patch };
    const maxX = form.cheque_width_mm - (updated.width_mm ?? 0);
    const maxY = form.cheque_height_mm - (updated.height_mm ?? 0);

    updated.x_position_mm = roundMeasurement(clamp(Number(updated.x_position_mm) || 0, 0, maxX));
    updated.y_position_mm = roundMeasurement(clamp(Number(updated.y_position_mm) || 0, 0, maxY));
    form.fields[index] = updated;
};

watch(
    () => [form.cheque_width_mm, form.cheque_height_mm],
    () => {
        form.fields.forEach((field, index) => updateField(index, field));
    },
);

const addBank = () => {
    const name = bankForm.name.trim();
    if (!name) return;

    bankForm.name = name;
    bankForm.post('/banks', {
        preserveScroll: true,
        errorBag: 'bank',
        onSuccess: () => {
            const created = props.banks.find((bank) => bank.name.toLocaleLowerCase() === name.toLocaleLowerCase());
            if (created) form.bank_id = String(created.id);
            bankForm.reset();
            showBankForm.value = false;
        },
    });
};

const selectBackground = (event: Event) => {
    backgroundForm.background_image = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const uploadBackground = () => {
    if (!source || !backgroundForm.background_image) return;
    backgroundForm.post(`/cheque-formats/${source.id}/background`, {
        forceFormData: true,
        preserveScroll: true,
        errorBag: 'background',
        onSuccess: () => backgroundForm.reset(),
    });
};

const removeBackground = () => {
    if (!source || !window.confirm('Remove this preview template image?')) return;
    router.delete(`/cheque-formats/${source.id}/background`, { preserveScroll: true });
};

const selectLogo = (event: Event) => {
    logoForm.logo_image = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const uploadLogo = () => {
    if (!source || !logoForm.logo_image) return;
    logoForm.post(`/cheque-formats/${source.id}/logo`, {
        forceFormData: true,
        preserveScroll: true,
        errorBag: 'logo',
        onSuccess: () => logoForm.reset(),
    });
};

const removeLogo = () => {
    if (!source || !window.confirm('Remove the printable logo from this cheque format?')) return;
    router.delete(`/cheque-formats/${source.id}/logo`, { preserveScroll: true });
};

const submit = () => {
    allowNavigation.value = true;
    const options = {
        preserveScroll: true,
        onError: () => {
            allowNavigation.value = false;
        },
    };

    if (source) {
        form.put(`/cheque-formats/${source.id}`, options);
    } else {
        form.post('/cheque-formats', options);
    }
};

const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!form.isDirty || allowNavigation.value) return;
    event.preventDefault();
    event.returnValue = '';
};

let removeNavigationListener: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    removeNavigationListener = router.on('before', () => {
        if (!form.isDirty || allowNavigation.value) return true;
        return window.confirm('You have unsaved cheque format changes. Leave this page?');
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    removeNavigationListener?.();
});
</script>

<template>
    <Head :title="editing ? 'Edit Cheque Format' : 'Add Cheque Format'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="cheque-format-module flex h-full min-w-0 flex-1 flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">{{ editing ? 'Edit Cheque Format' : 'Add Cheque Format' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Design the positions that will later print onto a physical pre-printed cheque.</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child type="button" variant="outline"
                        ><Link href="/cheque-formats"><ArrowLeft class="size-4" />Back to List</Link></Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        ><Save class="size-4" />{{ form.processing ? 'Saving...' : 'Save Cheque Format' }}</Button
                    >
                </div>
            </div>

            <div v-if="firstFormError" class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ firstFormError }}
            </div>

            <section class="rounded-lg border bg-card p-3 shadow-sm">
                <div class="grid min-w-0 gap-3 xl:grid-cols-2">
                    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="flex shrink-0 items-center gap-2 sm:w-44">
                            <ImageIcon class="size-4" />
                            <span class="text-sm font-medium">Preview Template</span>
                            <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">Preview only</span>
                        </div>
                        <template v-if="source">
                            <Label for="background-image" class="sr-only">Cheque preview template image</Label>
                            <input
                                id="background-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 py-1 text-xs file:mr-2 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                @change="selectBackground"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="!backgroundForm.background_image || backgroundForm.processing"
                                @click="uploadBackground"
                            >
                                <Upload class="size-3.5" />{{ backgroundForm.processing ? 'Uploading...' : 'Upload' }}
                            </Button>
                            <Button
                                v-if="chequeFormat?.background_image_url"
                                type="button"
                                size="icon"
                                variant="destructive"
                                title="Remove preview template"
                                @click="removeBackground"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </template>
                        <span v-else class="text-xs text-muted-foreground">Save format before uploading.</span>
                        <InputError class="sm:hidden" :message="backgroundForm.errors.background_image" />
                    </div>

                    <div class="flex min-w-0 flex-col gap-2 border-t pt-3 sm:flex-row sm:items-center xl:border-l xl:border-t-0 xl:pl-3 xl:pt-0">
                        <div class="flex shrink-0 items-center gap-2 sm:w-44">
                            <ImageIcon class="size-4" />
                            <span class="text-sm font-medium">Company Logo</span>
                            <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] text-primary">Prints</span>
                        </div>
                        <template v-if="source">
                            <Label for="logo-image" class="sr-only">Printable company logo image</Label>
                            <input
                                id="logo-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 py-1 text-xs file:mr-2 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                @change="selectLogo"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="!logoForm.logo_image || logoForm.processing"
                                @click="uploadLogo"
                            >
                                <Upload class="size-3.5" />{{ logoForm.processing ? 'Uploading...' : 'Upload' }}
                            </Button>
                            <Button
                                v-if="chequeFormat?.logo_image_url"
                                type="button"
                                size="icon"
                                variant="destructive"
                                title="Remove printable logo"
                                @click="removeLogo"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </template>
                        <span v-else class="text-xs text-muted-foreground">Save format before uploading.</span>
                        <InputError class="sm:hidden" :message="logoForm.errors.logo_image" />
                    </div>
                </div>
                <div class="hidden grid-cols-2 gap-3 pt-1 sm:grid xl:grid-cols-2">
                    <InputError :message="backgroundForm.errors.background_image" />
                    <InputError :message="logoForm.errors.logo_image" />
                </div>
            </section>

            <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(360px,0.85fr)_minmax(0,1.35fr)] xl:items-start">
                <div class="grid min-w-0 gap-4">
                    <section class="rounded-lg border bg-card shadow-sm">
                        <div class="border-b p-4">
                            <h2 class="font-medium">Cheque Format Information</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Dimensions and positions use millimetres for consistent future printing.</p>
                        </div>

                        <div class="grid gap-4 p-4 lg:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="format-name">Cheque Format Name <span class="text-destructive">*</span></Label>
                                <Input id="format-name" v-model="form.name" maxlength="255" placeholder="e.g. Askari Bank Standard Cheque" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <Label for="bank-id">Bank Name <span class="text-destructive">*</span></Label>
                                    <button type="button" class="text-xs font-medium text-primary hover:underline" @click="showBankForm = true">
                                        <Plus class="mr-1 inline size-3" />Add Bank
                                    </button>
                                </div>
                                <select id="bank-id" v-model="form.bank_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                    <option value="">Select a bank</option>
                                    <option v-for="bank in banks" :key="bank.id" :value="String(bank.id)">{{ bank.name }}</option>
                                </select>
                                <InputError :message="form.errors.bank_id" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label for="width">Cheque Width (mm) *</Label>
                                    <Input id="width" v-model.number="form.cheque_width_mm" type="number" min="50" max="500" step="0.1" />
                                    <InputError :message="form.errors.cheque_width_mm" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="height">Cheque Height (mm) *</Label>
                                    <Input id="height" v-model.number="form.cheque_height_mm" type="number" min="30" max="300" step="0.1" />
                                    <InputError :message="form.errors.cheque_height_mm" />
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="date-format">Date Format *</Label>
                                <select
                                    id="date-format"
                                    v-model="form.date_format"
                                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option v-for="format in dateFormats" :key="format" :value="format">{{ format }}</option>
                                </select>
                                <InputError :message="form.errors.date_format" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label>Amount Figures Prefix</Label><Input v-model="form.amount_figures_prefix" placeholder="e.g. AED" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Amount Figures Suffix</Label><Input v-model="form.amount_figures_suffix" placeholder="e.g. /-" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5"><Label>Amount Words Prefix</Label><Input v-model="form.amount_words_prefix" /></div>
                                <div class="grid gap-1.5">
                                    <Label>Amount Words Suffix</Label><Input v-model="form.amount_words_suffix" placeholder="Only" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5"><Label>Party Name Prefix</Label><Input v-model="form.party_name_prefix" /></div>
                                <div class="grid gap-1.5"><Label>Party Name Suffix</Label><Input v-model="form.party_name_suffix" /></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label>Party Name Max Length</Label
                                    ><Input v-model.number="form.party_name_max_length" type="number" min="1" max="500" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Amount Words Max Length</Label
                                    ><Input v-model.number="form.amount_words_max_length" type="number" min="1" max="500" />
                                </div>
                            </div>
                            <div class="grid gap-1.5"><Label>A/C Payee Text</Label><Input v-model="form.account_payee_text" /></div>
                            <div class="grid gap-1.5"><Label>Signature Placeholder</Label><Input v-model="form.signature_text" /></div>
                            <div class="grid gap-1.5"><Label>Label 1 Text</Label><Input v-model="form.label_1_text" placeholder="Optional" /></div>
                            <div class="grid gap-1.5"><Label>Label 2 Text</Label><Input v-model="form.label_2_text" placeholder="Optional" /></div>
                        </div>
                    </section>
                </div>

                <div class="min-w-0 xl:sticky xl:top-4 xl:z-20">
                    <ChequeDesigner
                        :width-mm="Number(form.cheque_width_mm)"
                        :height-mm="Number(form.cheque_height_mm)"
                        :fields="form.fields"
                        :definitions="fieldDefinitions"
                        :preview-text="previewText"
                        :background-image-url="chequeFormat?.background_image_url"
                        :logo-image-url="chequeFormat?.logo_image_url"
                        @update-field="updateField"
                    />
                </div>

                <FieldSettingsTable
                    class="xl:col-span-2"
                    :fields="form.fields"
                    :definitions="fieldDefinitions"
                    :font-families="fontFamilies"
                    :width-mm="Number(form.cheque_width_mm)"
                    :height-mm="Number(form.cheque_height_mm)"
                    @update-field="updateField"
                />
            </div>

            <InputError :message="form.errors.fields" />
            <InputError :message="form.errors.version" />

            <div class="sticky bottom-3 z-20 flex justify-end rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur">
                <Button type="submit" size="lg" :disabled="form.processing"
                    ><Save class="size-4" />{{ form.processing ? 'Saving...' : 'Save Cheque Format' }}</Button
                >
            </div>
        </form>

        <Dialog v-model:open="showBankForm">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><Building2 class="size-5" />Add Bank</DialogTitle>
                    <DialogDescription>Create a bank option and select it for this cheque format.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2 py-2">
                    <Label for="new-bank-name">Bank Name *</Label>
                    <Input id="new-bank-name" v-model="bankForm.name" maxlength="255" placeholder="Bank name" @keydown.enter.prevent="addBank" />
                    <InputError :message="bankForm.errors.name" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showBankForm = false">Cancel</Button>
                    <Button type="button" :disabled="bankForm.processing" @click="addBank">
                        {{ bankForm.processing ? 'Adding...' : 'Add Bank' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
