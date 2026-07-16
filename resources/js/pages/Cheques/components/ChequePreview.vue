<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Minus, Plus, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { ChequeFieldForm } from '../../ChequeFormats/types';

const props = defineProps<{
    widthMm: number;
    heightMm: number;
    fields: ChequeFieldForm[];
    values: Record<string, string>;
    backgroundImageUrl?: string | null;
    logoImageUrl?: string | null;
}>();

const zoom = ref(1);
const defaultBackgroundImageUrl = '/images/cheques/uae-cheque-preview.png';
const previewBackgroundImageUrl = computed(() => props.backgroundImageUrl || defaultBackgroundImageUrl);
const scale = computed(() => 3.2 * zoom.value);
const canvasStyle = computed(() => ({ width: `${props.widthMm * scale.value}px`, height: `${props.heightMm * scale.value}px` }));
const fieldStyle = (field: ChequeFieldForm) => ({
    left: `${Number(field.x_position_mm) * scale.value}px`,
    top: `${Number(field.y_position_mm) * scale.value}px`,
    width: field.width_mm ? `${Number(field.width_mm) * scale.value}px` : 'auto',
    minHeight: field.height_mm ? `${Number(field.height_mm) * scale.value}px` : undefined,
    fontFamily: field.font_family,
    fontSize: `${Number(field.font_size_pt) * (96 / 72) * zoom.value}px`,
    fontWeight: String(field.font_weight),
    fontStyle: field.is_italic ? 'italic' : 'normal',
    textDecoration: field.is_underline ? 'underline' : 'none',
    textAlign: field.text_align,
});

const adjustZoom = (amount: number) => {
    zoom.value = Math.min(2, Math.max(0.5, Math.round((zoom.value + amount) * 10) / 10));
};
</script>

<template>
    <section class="rounded-lg border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-medium">Cheque Preview</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        backgroundImageUrl
                            ? 'The selected bank template is for understanding only and will not be printed.'
                            : 'A generic UAE-style guide is shown for preview only and will not be printed.'
                    }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium">{{ Math.round(zoom * 100) }}%</span
                ><Button type="button" size="icon" variant="outline" @click="adjustZoom(-0.1)"><Minus class="size-4" /></Button
                ><Button type="button" size="icon" variant="outline" @click="zoom = 1"><RotateCcw class="size-4" /></Button
                ><Button type="button" size="icon" variant="outline" @click="adjustZoom(0.1)"><Plus class="size-4" /></Button>
            </div>
        </div>
        <div class="overflow-auto bg-muted/30 p-4 sm:p-6">
            <div class="relative overflow-hidden border bg-white text-black shadow" :style="canvasStyle">
                <img
                    :src="previewBackgroundImageUrl"
                    :alt="backgroundImageUrl ? 'Selected cheque template preview' : 'Generic UAE-style cheque preview guide'"
                    class="pointer-events-none absolute inset-0 size-full select-none object-fill"
                    draggable="false"
                />
                <div
                    v-for="field in fields"
                    v-show="field.is_visible && (field.field_key === 'company_logo' ? logoImageUrl : values[field.field_key])"
                    :key="field.field_key"
                    class="absolute overflow-hidden whitespace-nowrap text-black"
                    :style="fieldStyle(field)"
                >
                    <img
                        v-if="field.field_key === 'company_logo' && logoImageUrl"
                        :src="logoImageUrl"
                        alt="Company logo"
                        class="size-full object-contain"
                    />
                    <template v-else>{{ values[field.field_key] }}</template>
                </div>
            </div>
        </div>
    </section>
</template>
