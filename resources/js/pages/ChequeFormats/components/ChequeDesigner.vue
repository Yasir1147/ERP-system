<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Maximize2, Minus, Plus, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { clamp, roundMeasurement } from '../designer';
import type { ChequeFieldForm, FieldDefinition } from '../types';

const props = defineProps<{
    widthMm: number;
    heightMm: number;
    fields: ChequeFieldForm[];
    definitions: FieldDefinition[];
    previewText: Record<string, string>;
    backgroundImageUrl?: string | null;
    logoImageUrl?: string | null;
}>();

const emit = defineEmits<{
    updateField: [index: number, patch: Partial<ChequeFieldForm>];
}>();

const pixelsPerMm = 3.2;
const zoom = ref(1);
const defaultBackgroundImageUrl = '/images/cheques/uae-cheque-preview.png';
const previewBackgroundImageUrl = computed(() => props.backgroundImageUrl || defaultBackgroundImageUrl);
const drag = ref<{
    index: number;
    pointerId: number;
    clientX: number;
    clientY: number;
    startX: number;
    startY: number;
} | null>(null);

const scale = computed(() => pixelsPerMm * zoom.value);
const canvasStyle = computed(() => ({
    width: `${Math.max(50, props.widthMm) * scale.value}px`,
    height: `${Math.max(30, props.heightMm) * scale.value}px`,
}));

const labelFor = (key: string) => props.definitions.find((definition) => definition.key === key)?.label ?? key;

const fieldStyle = (field: ChequeFieldForm) => ({
    left: `${field.x_position_mm * scale.value}px`,
    top: `${field.y_position_mm * scale.value}px`,
    width: field.width_mm ? `${field.width_mm * scale.value}px` : 'auto',
    minHeight: field.height_mm ? `${field.height_mm * scale.value}px` : undefined,
    fontFamily: field.font_family,
    fontSize: `${field.font_size_pt * (96 / 72) * zoom.value}px`,
    fontWeight: String(field.font_weight),
    fontStyle: field.is_italic ? 'italic' : 'normal',
    textDecoration: field.is_underline ? 'underline' : 'none',
    textAlign: field.text_align,
});

const startDrag = (event: PointerEvent, index: number) => {
    const field = props.fields[index];
    if (!field.is_visible) return;

    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    drag.value = {
        index,
        pointerId: event.pointerId,
        clientX: event.clientX,
        clientY: event.clientY,
        startX: field.x_position_mm,
        startY: field.y_position_mm,
    };
};

const moveDrag = (event: PointerEvent) => {
    if (!drag.value || drag.value.pointerId !== event.pointerId) return;

    const field = props.fields[drag.value.index];
    const fieldWidth = field.width_mm ?? 0;
    const fieldHeight = field.height_mm ?? 0;
    const nextX = drag.value.startX + (event.clientX - drag.value.clientX) / scale.value;
    const nextY = drag.value.startY + (event.clientY - drag.value.clientY) / scale.value;

    emit('updateField', drag.value.index, {
        x_position_mm: roundMeasurement(clamp(nextX, 0, props.widthMm - fieldWidth)),
        y_position_mm: roundMeasurement(clamp(nextY, 0, props.heightMm - fieldHeight)),
    });
};

const endDrag = (event: PointerEvent) => {
    if (!drag.value || drag.value.pointerId !== event.pointerId) return;
    (event.currentTarget as HTMLElement).releasePointerCapture(event.pointerId);
    drag.value = null;
};

const changeZoom = (amount: number) => {
    zoom.value = clamp(Math.round((zoom.value + amount) * 10) / 10, 0.5, 2);
};
</script>

<template>
    <section class="cheque-designer rounded-lg border bg-card shadow-sm">
        <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-medium">Cheque Designer / Preview</h2>
                <p class="mt-1 text-xs text-muted-foreground">Drag fields to position them. Measurements are saved in millimetres.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium">{{ Math.round(zoom * 100) }}%</span>
                <Button type="button" size="icon" variant="outline" title="Zoom out" @click="changeZoom(-0.1)"><Minus class="size-4" /></Button>
                <Button type="button" size="icon" variant="outline" title="Reset zoom" @click="zoom = 1"><RotateCcw class="size-4" /></Button>
                <Button type="button" size="icon" variant="outline" title="Zoom in" @click="changeZoom(0.1)"><Plus class="size-4" /></Button>
                <Button type="button" size="icon" variant="outline" disabled title="Full-screen preview can be added later"
                    ><Maximize2 class="size-4"
                /></Button>
            </div>
        </div>

        <div class="overflow-auto bg-muted/30 p-4 sm:p-6">
            <div
                class="cheque-canvas relative overflow-hidden border-2 border-dashed border-slate-400 bg-white text-black shadow-sm"
                :style="canvasStyle"
                role="application"
                aria-label="Draggable cheque field preview"
            >
                <img
                    :src="previewBackgroundImageUrl"
                    :alt="backgroundImageUrl ? 'Uploaded cheque preview template' : 'Generic UAE-style cheque preview guide'"
                    class="pointer-events-none absolute inset-0 size-full select-none object-fill"
                    draggable="false"
                />
                <div
                    v-for="(field, index) in fields"
                    v-show="field.is_visible"
                    :key="field.field_key"
                    class="cheque-field-item absolute cursor-move touch-none select-none overflow-hidden rounded-sm border border-dashed border-blue-500/60 bg-blue-50/50 text-black hover:bg-blue-100/70"
                    :class="{ 'px-1': field.field_key !== 'company_logo' }"
                    :style="fieldStyle(field)"
                    :title="`${labelFor(field.field_key)} - drag to move`"
                    @pointerdown.prevent="startDrag($event, index)"
                    @pointermove.prevent="moveDrag"
                    @pointerup="endDrag"
                    @pointercancel="endDrag"
                >
                    <img
                        v-if="field.field_key === 'company_logo' && logoImageUrl"
                        :src="logoImageUrl"
                        alt="Printable company logo"
                        class="pointer-events-none size-full object-contain"
                        draggable="false"
                    />
                    <span v-else>{{ previewText[field.field_key] || labelFor(field.field_key) }}</span>
                </div>
            </div>
        </div>

        <p class="border-t px-4 py-3 text-xs text-muted-foreground">
            The canvas outline and field guides are design aids only. They will never be included in cheque printing.
        </p>
    </section>
</template>
