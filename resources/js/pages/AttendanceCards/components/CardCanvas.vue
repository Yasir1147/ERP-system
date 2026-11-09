<script setup lang="ts">
import { computed, ref } from 'vue';
import type { AttendanceCard, CardDay, CardSection, CardTemplate, SectionKey } from '../types';

const props = defineProps<{ template: CardTemplate; card: AttendanceCard; editable?: boolean; selected?: SectionKey }>();
const emit = defineEmits<{ select: [key: SectionKey]; move: [key: SectionKey, x: number, y: number] }>();
const canvas = ref<HTMLElement | null>(null);
const keys: SectionKey[] = ['title', 'logo', 'identity', 'grid', 'footer', 'signature'];
const bands = computed(() => [
    Array.from({ length: 16 }, (_, i) => props.card.days[i] ?? null),
    Array.from({ length: 16 }, (_, i) => props.card.days[i + 16] ?? null),
]);
const rowKeys = computed(() => (props.template.layout.show_ot_site ? ['mark', 'site', 'overtime', 'otSite'] : ['mark', 'site', 'overtime']));
const style = (section: CardSection) => ({
    left: `${section.x}mm`,
    top: `${section.y}mm`,
    width: `${section.width}mm`,
    height: `${section.height}mm`,
    fontSize: `${section.font_size}pt`,
});
const time = (minutes: number) => (minutes ? `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}` : '0:00');
function bandTotal(band: (CardDay | null)[], key: string) {
    if (props.card.blank) return '';
    if (key === 'mark') return band.reduce((sum, day) => sum + (day?.dayValue ?? 0), 0);
    if (key === 'overtime') return time(band.reduce((sum, day) => sum + (day?.overtimeMinutes ?? 0), 0));
    return '–';
}
function label(key: string) {
    return key === 'mark'
        ? props.template.layout.attendance_label
        : key === 'site'
          ? props.template.layout.site_label
          : key === 'overtime'
            ? props.template.layout.overtime_label
            : 'OT Site';
}
function value(day: CardDay, key: string) {
    return key === 'mark' ? day.mark : key === 'site' ? day.site : key === 'overtime' ? day.overtime : day.otSite;
}
let drag: { key: SectionKey; pointer: number; x: number; y: number; startX: number; startY: number; pixelsPerMm: number } | null = null;
function start(event: PointerEvent, key: SectionKey) {
    if (!props.editable || !canvas.value) return;
    event.preventDefault();
    emit('select', key);
    const section = props.template.layout.sections[key];
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    drag = {
        key,
        pointer: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        startX: section.x,
        startY: section.y,
        pixelsPerMm: canvas.value.getBoundingClientRect().width / props.template.layout.width,
    };
}
function move(event: PointerEvent) {
    if (!drag || event.pointerId !== drag.pointer) return;
    const section = props.template.layout.sections[drag.key];
    const clamp = (v: number, max: number) => Math.round(Math.max(0, Math.min(v, max)) * 100) / 100;
    emit(
        'move',
        drag.key,
        clamp(drag.startX + (event.clientX - drag.x) / drag.pixelsPerMm, props.template.layout.width - section.width),
        clamp(drag.startY + (event.clientY - drag.y) / drag.pixelsPerMm, props.template.layout.height - section.height),
    );
}
function stop() {
    drag = null;
}
</script>

<template>
    <article
        ref="canvas"
        class="attendance-card-canvas"
        :style="{
            width: `${template.layout.width}mm`,
            height: `${template.layout.height}mm`,
            color: template.layout.text_color,
            fontFamily: template.layout.font,
            fontSize: `${template.layout.font_size}pt`,
            '--card-border': template.layout.border_color,
            '--card-header': template.layout.header_color,
            '--card-rest': template.layout.rest_color,
        }"
        :aria-label="`Attendance card: ${card.code} - ${card.name}, ${card.monthLabel}`"
    >
        <section
            v-for="key in keys"
            v-show="
                template.layout.sections[key].visible &&
                (key !== 'signature' || (template.layout.show_signature && (template.signatureUrl || editable)))
            "
            :key="key"
            class="card-section"
            :class="{ 'card-editable': editable, 'card-selected': editable && selected === key }"
            :style="style(template.layout.sections[key])"
            @pointerdown="start($event, key)"
            @pointermove="move"
            @pointerup="stop"
            @pointercancel="stop"
        >
            <div v-if="key === 'title'" class="card-title">
                {{ template.layout.title.replaceAll('{year}', String(card.year)).replaceAll('{month}', card.monthLabel) }}
            </div>
            <img v-else-if="key === 'logo'" :src="template.logoUrl" alt="Company logo" class="card-logo" draggable="false" />
            <template v-else-if="key === 'signature'"
                ><img v-if="template.signatureUrl" :src="template.signatureUrl" alt="Admin signature" class="card-logo" draggable="false" /><span
                    v-else-if="editable"
                    class="card-signature-placeholder"
                    >Signature Image</span
                ></template
            >
            <table v-else-if="key === 'identity'" class="card-identity">
                <tbody>
                    <tr>
                        <th>NAME:</th>
                        <td colspan="2">{{ card.name }}</td>
                        <th>PROFESSION:</th>
                        <td colspan="2">{{ card.profession }}</td>
                    </tr>
                    <tr>
                        <th>CODE:</th>
                        <td colspan="2">{{ card.code }}</td>
                        <th>MONTH</th>
                        <td>{{ card.month }}</td>
                        <td>{{ card.year }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else-if="key === 'grid'" class="card-bands">
                <table v-for="(band, bandIndex) in bands" :key="bandIndex" class="card-grid">
                    <colgroup>
                        <col style="width: 13%" />
                        <col v-for="i in 16" :key="i" style="width: 4.9375%" />
                        <col style="width: 8%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th></th>
                            <th v-for="(day, index) in band" :key="index" :class="{ 'card-unused': !day }">
                                {{ day ? String(day.day).padStart(2, '0') : '' }}
                            </th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(rowKey, rowIndex) in rowKeys" :key="rowKey">
                            <th class="card-row-label">{{ label(rowKey) }}</th>
                            <template v-for="(day, index) in band" :key="index"
                                ><td v-if="!day" class="card-unused"></td>
                                <td v-else-if="day.restLabel && rowIndex === 0" :rowspan="rowKeys.length" class="card-rest">
                                    <span>{{ day.restLabel }}</span>
                                </td>
                                <td
                                    v-else-if="!day.restLabel"
                                    :title="
                                        [day.date, day.projectName, day.otProjectName ? `OT: ${day.otProjectName}` : '', day.note]
                                            .filter(Boolean)
                                            .join(' · ')
                                    "
                                >
                                    {{ value(day, rowKey) }}
                                </td></template
                            >
                            <td>{{ bandTotal(band, rowKey) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <table v-else-if="key === 'footer'" class="card-footer">
                <colgroup>
                    <col style="width: 27%" />
                    <col style="width: 15%" />
                    <col style="width: 17%" />
                    <col style="width: 14%" />
                    <col style="width: 27%" />
                </colgroup>
                <tbody>
                    <tr class="card-signatures">
                        <td></td>
                        <td>{{ card.blank ? '' : card.presentDays }}</td>
                        <td>{{ card.blank ? '' : time(card.overtimeMinutes) }}</td>
                        <td>{{ card.blank ? '' : card.leaveDays }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>{{ template.layout.admin_label }}</td>
                        <td>Total Days</td>
                        <td>{{ template.layout.overtime_label }}</td>
                        <td>Leave</td>
                        <td>{{ template.layout.employee_label }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </article>
</template>

<style>
.attendance-card-canvas {
    position: relative;
    flex: none;
    box-sizing: border-box;
    border: 0.3mm solid var(--card-border);
    background: #fff;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    overflow: hidden;
    line-height: 1.15;
}
.attendance-card-canvas .card-section {
    position: absolute;
    box-sizing: border-box;
    overflow: hidden;
}
.attendance-card-canvas .card-editable {
    outline: 1px dashed #3984eb;
    cursor: move;
    touch-action: none;
    user-select: none;
}
.attendance-card-canvas .card-selected {
    outline: 2px solid #2563eb;
    background-color: rgba(37, 99, 235, 0.04);
}
.attendance-card-canvas .card-title {
    height: 100%;
    display: flex;
    align-items: center;
    font-weight: 700;
    letter-spacing: 0.4mm;
}
.attendance-card-canvas .card-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.attendance-card-canvas table {
    width: 100%;
    height: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    color: inherit;
    font: inherit;
}
.attendance-card-canvas th,
.attendance-card-canvas td {
    border: 0.25mm solid var(--card-border);
    padding: 0.4mm;
    vertical-align: middle;
    overflow-wrap: anywhere;
}
.attendance-card-canvas .card-identity th {
    width: 16%;
    text-align: left;
}
.attendance-card-canvas .card-identity td {
    font-weight: 700;
}
.attendance-card-canvas .card-bands {
    height: 100%;
    display: flex;
    flex-direction: column;
}
.attendance-card-canvas .card-grid {
    height: 50%;
    text-align: center;
}
.attendance-card-canvas .card-grid thead {
    height: 6mm;
    background: var(--card-header);
}
.attendance-card-canvas .card-grid thead th {
    font-weight: 400;
}
.attendance-card-canvas .card-row-label {
    text-align: left;
    font-weight: 400;
}
.attendance-card-canvas .card-rest {
    color: var(--card-rest);
    font-weight: 800;
}
.attendance-card-canvas .card-rest span {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-size: 1.3em;
}
.attendance-card-canvas .card-unused {
    background: #eee;
}
.attendance-card-canvas .card-footer {
    text-align: center;
}
.attendance-card-canvas .card-footer tr:last-child {
    height: 5mm;
}
.attendance-card-canvas .card-signatures td {
    padding: 0;
}
.attendance-card-canvas .card-signatures img {
    max-height: 7mm;
    max-width: 95%;
    object-fit: contain;
}
@media print {
    .attendance-card-canvas .card-editable,
    .attendance-card-canvas .card-selected {
        outline: none;
        background-color: transparent;
    }
}
</style>
