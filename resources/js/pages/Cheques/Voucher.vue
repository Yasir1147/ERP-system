<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import type { ChequeFieldForm } from '../ChequeFormats/types';
import { ref } from 'vue';

const props = defineProps<{
    cheque: {
        id: number;
        widthMm: number;
        heightMm: number;
        fields: ChequeFieldForm[];
        fieldValues: Record<string, string>;
        logoImageUrl: string | null;
        chequeNumber: string | null;
    };
    voucher: {
        voucherNumber: string | null;
        issuedDate: string | null;
        chequeDate: string | null;
        amount: string;
        amountWords: string;
        beneficiary: string;
        purpose: string | null;
        receivedBy: string | null;
        receiverId: string | null;
        receiverMobile: string | null;
        preparedBy: string | null;
        checkedBy: string | null;
        approvedBy: string | null;
    };
    includeCheque: boolean;
}>();

const includeChequeCopy = ref(props.includeCheque);

const chequeStyle = { width: `${props.cheque.widthMm}mm`, height: `${props.cheque.heightMm}mm` };
const fieldStyle = (field: ChequeFieldForm) => ({
    left: `${Number(field.x_position_mm)}mm`,
    top: `${Number(field.y_position_mm)}mm`,
    width: field.width_mm ? `${Number(field.width_mm)}mm` : 'auto',
    minHeight: field.height_mm ? `${Number(field.height_mm)}mm` : undefined,
    fontFamily: field.font_family,
    fontSize: `${Number(field.font_size_pt)}pt`,
    fontWeight: String(field.font_weight),
    fontStyle: field.is_italic ? 'italic' : 'normal',
    textDecoration: field.is_underline ? 'underline' : 'none',
    textAlign: field.text_align,
});
</script>

<template>
    <Head title="Cheque Payment Voucher" />
    <main class="voucher-print-page min-h-screen bg-slate-200 p-4 text-slate-950">
        <div class="voucher-print-controls mx-auto mb-4 flex max-w-4xl flex-col gap-3 rounded-lg bg-white p-4 shadow sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-semibold">Cheque Payment Voucher</h1>
                <p class="mt-1 text-xs text-slate-500">Use Print to print this A4 page or choose Save as PDF.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                    <input v-model="includeChequeCopy" type="checkbox" class="size-4 rounded border-slate-300" />
                    Include cheque copy at top
                </label>
                <Button type="button" variant="outline" @click="window.close()"><ArrowLeft class="size-4" />Close</Button>
                <Button type="button" @click="window.print()"><Printer class="size-4" />Print / Save PDF</Button>
            </div>
        </div>

        <article class="voucher-sheet mx-auto min-h-[297mm] w-[210mm] bg-white p-[5mm] shadow-xl">
            <section v-if="includeChequeCopy" class="mb-[6mm]">
                <div class="mb-1 text-center text-[9pt] font-semibold uppercase tracking-wide">Cheque Copy</div>
                <div class="relative mx-auto overflow-hidden border border-slate-300 bg-white" :style="chequeStyle">
                    <div
                        v-for="field in cheque.fields"
                        v-show="field.is_visible && (field.field_key === 'company_logo' ? cheque.logoImageUrl : cheque.fieldValues[field.field_key])"
                        :key="field.field_key"
                        class="absolute overflow-hidden whitespace-nowrap text-black"
                        :style="fieldStyle(field)"
                    >
                        <img
                            v-if="field.field_key === 'company_logo' && cheque.logoImageUrl"
                            :src="cheque.logoImageUrl"
                            alt="Company logo"
                            class="size-full object-contain"
                        />
                        <template v-else>{{ cheque.fieldValues[field.field_key] }}</template>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-[175mm] text-[9pt]">
                <div class="mb-[3mm] text-center text-[14pt] font-bold uppercase">Cheque Payment Voucher</div>
                <div class="grid grid-cols-3 border-2 border-black">
                    <div class="border-r border-black p-2"><strong>Cheque #:</strong> {{ cheque.chequeNumber || '—' }}</div>
                    <div class="border-r border-black p-2"><strong>Issued Date:</strong> {{ voucher.issuedDate || '—' }}</div>
                    <div class="p-2"><strong>Cheque Date:</strong> {{ voucher.chequeDate || '—' }}</div>
                    <div class="border-r border-t border-black p-2"><strong>Amount:</strong> {{ voucher.amount }}</div>
                    <div class="col-span-2 border-t border-black p-2"><strong>Amount in Words:</strong> {{ voucher.amountWords }}</div>
                </div>
                <div class="mt-[3mm] grid grid-cols-[30mm_1fr] border-2 border-black">
                    <div class="border-r border-black p-2 font-bold">Beneficiary</div>
                    <div class="p-2">{{ voucher.beneficiary }}</div>
                    <div class="border-r border-t border-black p-2 font-bold">Purpose</div>
                    <div class="border-t border-black p-2">{{ voucher.purpose || '—' }}</div>
                </div>
                <div class="mt-[3mm] grid grid-cols-2 border-2 border-black">
                    <div class="border-r border-black p-2"><strong>Received By:</strong> {{ voucher.receivedBy || '' }}</div>
                    <div class="p-2"><strong>ID:</strong> {{ voucher.receiverId || '' }}</div>
                    <div class="min-h-[16mm] border-r border-t border-black p-2">
                        <strong>Receiver Signature & Mobile:</strong> {{ voucher.receiverMobile || '' }}
                    </div>
                    <div class="min-h-[16mm] border-t border-black p-2"><strong>Company Stamp:</strong></div>
                </div>
                <div class="mt-[3mm] grid grid-cols-3 border-2 border-black text-center">
                    <div class="border-r border-black p-3"><strong>Prepared By:</strong> {{ voucher.preparedBy || '—' }}</div>
                    <div class="border-r border-black p-3"><strong>Checked By:</strong> {{ voucher.checkedBy || '—' }}</div>
                    <div class="p-3"><strong>Approved By:</strong> {{ voucher.approvedBy || '—' }}</div>
                </div>
                <div v-if="voucher.voucherNumber" class="mt-2 text-right text-[8pt]">Voucher #: {{ voucher.voucherNumber }}</div>
            </section>
        </article>
    </main>
</template>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }
    html,
    body,
    #app {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .voucher-print-page {
        min-height: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .voucher-print-controls {
        display: none !important;
    }
    .voucher-sheet {
        box-shadow: none !important;
    }
}
</style>
