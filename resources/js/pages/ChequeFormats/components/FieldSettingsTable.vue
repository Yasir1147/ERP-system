<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUp, Bold, Italic, Underline } from 'lucide-vue-next';
import { clamp, roundMeasurement } from '../designer';
import type { ChequeFieldForm, FieldDefinition } from '../types';

const props = defineProps<{
    fields: ChequeFieldForm[];
    definitions: FieldDefinition[];
    fontFamilies: string[];
    widthMm: number;
    heightMm: number;
}>();

const emit = defineEmits<{
    updateField: [index: number, patch: Partial<ChequeFieldForm>];
}>();

const labelFor = (key: string) => props.definitions.find((definition) => definition.key === key)?.label ?? key;
const numberValue = (event: Event) => Number((event.target as HTMLInputElement).value);
const nullableNumberValue = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    return value === '' ? null : Number(value);
};
const stringValue = (event: Event) => (event.target as HTMLInputElement | HTMLSelectElement).value;
const checkedValue = (event: Event) => (event.target as HTMLInputElement).checked;

const update = (index: number, patch: Partial<ChequeFieldForm>) => emit('updateField', index, patch);

const move = (index: number, xDirection: number, yDirection: number, event: MouseEvent) => {
    const field = props.fields[index];
    const step = event.shiftKey ? 2 : 0.5;
    const maxX = props.widthMm - (field.width_mm ?? 0);
    const maxY = props.heightMm - (field.height_mm ?? 0);

    update(index, {
        x_position_mm: roundMeasurement(clamp(field.x_position_mm + xDirection * step, 0, maxX)),
        y_position_mm: roundMeasurement(clamp(field.y_position_mm + yDirection * step, 0, maxY)),
    });
};
</script>

<template>
    <section class="rounded-lg border bg-card shadow-sm">
        <div class="border-b p-4">
            <h2 class="font-medium">Field Position and Formatting Settings</h2>
            <p class="mt-1 text-xs text-muted-foreground">Arrow buttons move 0.5 mm. Hold Shift while clicking to move 2 mm.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1680px] text-sm">
                <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                    <tr>
                        <th class="sticky left-0 z-10 min-w-[210px] bg-muted px-3 py-3 font-medium">Field Name</th>
                        <th class="w-[105px] px-2 py-3 font-medium">X (mm)</th>
                        <th class="w-[105px] px-2 py-3 font-medium">Y (mm)</th>
                        <th class="w-[190px] px-2 py-3 text-center font-medium">Movement</th>
                        <th class="w-[160px] px-2 py-3 font-medium">Font</th>
                        <th class="w-[95px] px-2 py-3 font-medium">Size (pt)</th>
                        <th class="w-[150px] px-2 py-3 text-center font-medium">Style</th>
                        <th class="w-[120px] px-2 py-3 font-medium">Alignment</th>
                        <th class="w-[95px] px-2 py-3 text-center font-medium">Visible</th>
                        <th class="w-[105px] px-2 py-3 font-medium">Width</th>
                        <th class="w-[105px] px-2 py-3 font-medium">Height</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(field, index) in fields" :key="field.field_key" class="hover:bg-muted/20">
                        <td class="sticky left-0 z-10 bg-card px-3 py-2 font-medium">{{ labelFor(field.field_key) }}</td>
                        <td class="px-2 py-2">
                            <input
                                :value="field.x_position_mm"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-9 w-full rounded-md border border-input bg-background px-2"
                                @input="update(index, { x_position_mm: numberValue($event) })"
                            />
                        </td>
                        <td class="px-2 py-2">
                            <input
                                :value="field.y_position_mm"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-9 w-full rounded-md border border-input bg-background px-2"
                                @input="update(index, { y_position_mm: numberValue($event) })"
                            />
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex justify-center gap-1">
                                <Button type="button" size="icon" variant="outline" title="Move left" @click="move(index, -1, 0, $event)"
                                    ><ArrowLeft class="size-3.5"
                                /></Button>
                                <Button type="button" size="icon" variant="outline" title="Move right" @click="move(index, 1, 0, $event)"
                                    ><ArrowRight class="size-3.5"
                                /></Button>
                                <Button type="button" size="icon" variant="outline" title="Move up" @click="move(index, 0, -1, $event)"
                                    ><ArrowUp class="size-3.5"
                                /></Button>
                                <Button type="button" size="icon" variant="outline" title="Move down" @click="move(index, 0, 1, $event)"
                                    ><ArrowDown class="size-3.5"
                                /></Button>
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            <select
                                :value="field.font_family"
                                class="h-9 w-full rounded-md border border-input bg-background px-2"
                                @change="update(index, { font_family: stringValue($event) })"
                            >
                                <option v-for="family in fontFamilies" :key="family" :value="family">{{ family }}</option>
                            </select>
                        </td>
                        <td class="px-2 py-2">
                            <input
                                :value="field.font_size_pt"
                                type="number"
                                min="6"
                                max="72"
                                step="0.5"
                                class="h-9 w-full rounded-md border border-input bg-background px-2"
                                @input="update(index, { font_size_pt: numberValue($event) })"
                            />
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex justify-center gap-1">
                                <Button
                                    type="button"
                                    size="icon"
                                    :variant="field.font_weight === 700 ? 'default' : 'outline'"
                                    title="Bold"
                                    @click="update(index, { font_weight: field.font_weight === 700 ? 400 : 700 })"
                                    ><Bold class="size-3.5"
                                /></Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    :variant="field.is_italic ? 'default' : 'outline'"
                                    title="Italic"
                                    @click="update(index, { is_italic: !field.is_italic })"
                                    ><Italic class="size-3.5"
                                /></Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    :variant="field.is_underline ? 'default' : 'outline'"
                                    title="Underline"
                                    @click="update(index, { is_underline: !field.is_underline })"
                                    ><Underline class="size-3.5"
                                /></Button>
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            <select
                                :value="field.text_align"
                                class="h-9 w-full rounded-md border border-input bg-background px-2 capitalize"
                                @change="update(index, { text_align: stringValue($event) as ChequeFieldForm['text_align'] })"
                            >
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                            </select>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <input
                                :checked="field.is_visible"
                                type="checkbox"
                                class="size-4 rounded border-input"
                                @change="update(index, { is_visible: checkedValue($event) })"
                            />
                        </td>
                        <td class="px-2 py-2">
                            <input
                                :value="field.width_mm ?? ''"
                                type="number"
                                min="0.1"
                                step="0.01"
                                class="h-9 w-full rounded-md border border-input bg-background px-2"
                                @input="update(index, { width_mm: nullableNumberValue($event) })"
                            />
                        </td>
                        <td class="px-2 py-2">
                            <input
                                :value="field.height_mm ?? ''"
                                type="number"
                                min="0.1"
                                step="0.01"
                                class="h-9 w-full rounded-md border border-input bg-background px-2"
                                @input="update(index, { height_mm: nullableNumberValue($event) })"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
