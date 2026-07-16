<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import type { ChequeFieldForm } from '../ChequeFormats/types';

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
}>();

const sheetStyle = { width: `${props.cheque.widthMm}mm`, height: `${props.cheque.heightMm}mm` };
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

const printCheque = () => {
    router.post(`/cheques/${props.cheque.id}/mark-printed`, {}, { preserveState: true, preserveScroll: true, onSuccess: () => window.print() });
};
</script>

<template>
    <Head title="Cheque Print" />
    <main class="cheque-print-page min-h-screen bg-slate-200 p-4 text-slate-950">
        <div
            class="cheque-print-controls mx-auto mb-4 flex max-w-5xl flex-col gap-3 rounded-lg bg-white p-4 shadow sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="font-semibold">Cheque Print Preview</h1>
                <p class="mt-1 text-xs text-slate-500">Load the physical cheque, use Actual Size / 100%, and disable browser headers and footers.</p>
            </div>
            <div class="flex gap-2">
                <Button type="button" variant="outline" @click="window.close()"><ArrowLeft class="size-4" />Close</Button
                ><Button type="button" @click="printCheque"><Printer class="size-4" />Mark Printed & Print</Button>
            </div>
        </div>
        <div class="overflow-auto pb-6">
            <div class="cheque-print-sheet relative mx-auto overflow-hidden bg-white shadow-xl" :style="sheetStyle">
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
        </div>
    </main>
</template>

<style>
@media print {
    @page {
        margin: 0;
    }
    html,
    body,
    #app {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .cheque-print-page {
        min-height: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .cheque-print-controls {
        display: none !important;
    }
    .cheque-print-sheet {
        margin: 0 !important;
        box-shadow: none !important;
    }
}
</style>
