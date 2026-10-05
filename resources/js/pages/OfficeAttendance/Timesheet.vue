<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

type Detail = {
    checkInDisplay: string | null;
    checkOutDisplay: string | null;
    workModeLabel: string;
    workHoursLabel: string;
    sessionDisplaySegments: string[];
    note: string | null;
};
type Cell = { date: string; status: string; detail: Detail | null };
type Row = {
    id: number;
    code: string;
    name: string;
    designation: string | null;
    cells: Cell[];
    present: number;
    leave: number;
    pending: number;
    workLabel: string;
};
const props = defineProps<{
    filters: { month: string; staffIds: number[]; allStaff: boolean; search: string };
    monthLabel: string;
    staff: { id: number; label: string }[];
    days: { date: string; number: number; weekday: string }[];
    rows: Row[];
    dailyPresent: number[];
    totals: { staff: number; present: number; leave: number; pending: number; workLabel: string };
    errors?: Record<string, string>;
}>();
const month = ref(props.filters.month);
const staffIds = ref<number[]>(props.filters.allStaff ? props.staff.map((person) => person.id) : [...props.filters.staffIds]);
const staffPicker = ref<HTMLElement | null>(null);
const pickerOpen = ref(false);
const staffSearch = ref('');
const allSelected = computed(() => props.staff.length > 0 && staffIds.value.length === props.staff.length);
const visibleStaff = computed(() => props.staff.filter((person) => person.label.toLowerCase().includes(staffSearch.value.trim().toLowerCase())));
const selectionLabel = computed(() =>
    allSelected.value ? 'All Staff' : staffIds.value.length ? `${staffIds.value.length} staff selected` : 'No staff selected',
);
onClickOutside(staffPicker, () => {
    pickerOpen.value = false;
});
function toggleAll() {
    staffIds.value = allSelected.value ? [] : props.staff.map((person) => person.id);
}
const search = ref(props.filters.search);
const selected = ref<{ row: Row; cell: Cell } | null>(null);
const open = ref(false);
const breadcrumbs = [
    { title: 'Office Staff', href: '/office-staff' },
    { title: 'Monthly Timesheet', href: '/office-attendance/timesheet' },
];
const exportQuery = computed(() => {
    const params = new URLSearchParams({
        month: props.filters.month,
        selection: props.filters.allStaff ? 'all' : 'selected',
        search: props.filters.search,
    });
    props.filters.staffIds.forEach((id) => params.append('staff_ids[]', String(id)));
    return params.toString();
});
const labels: Record<string, string> = { P: 'Present', L: 'Approved leave', LP: 'Pending leave', '-': 'No attendance record' };
function apply() {
    pickerOpen.value = false;
    router.get('/office-attendance/timesheet', {
        month: month.value,
        selection: allSelected.value ? 'all' : 'selected',
        staff_ids: allSelected.value ? [] : staffIds.value,
        search: search.value,
    });
}
function moveMonth(offset: number) {
    const [year, currentMonth] = props.filters.month.split('-').map(Number);
    const date = new Date(year, currentMonth - 1 + offset, 1);
    month.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    apply();
}
function showDetail(row: Row, cell: Cell) {
    selected.value = { row, cell };
    open.value = true;
}
</script>

<template>
    <Head title="Office Staff Monthly Timesheet" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Office Staff Monthly Timesheet</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ monthLabel }} · Daily attendance at a glance. Select P to see timings.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline"><Link href="/office-attendance/report">Attendance Report</Link></Button>
                    <Button as-child variant="outline"><a :href="`/office-attendance/timesheet-export?${exportQuery}`">Download Excel</a></Button>
                    <Button as-child
                        ><a :href="`/office-attendance/timesheet-print?${exportQuery}`" target="_blank" rel="noreferrer">Print / PDF</a></Button
                    >
                </div>
            </div>

            <form class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" @submit.prevent="apply">
                <label class="grid gap-1.5 text-sm font-medium">Month<Input v-model="month" type="month" required /></label>
                <div ref="staffPicker" class="relative grid w-full gap-1.5 text-sm font-medium sm:w-64" @keydown.esc="pickerOpen = false">
                    <span id="staff-picker-label">Staff</span>
                    <button
                        type="button"
                        class="flex h-10 items-center justify-between rounded-md border bg-background px-3 text-left"
                        aria-labelledby="staff-picker-label staff-picker-value"
                        :aria-expanded="pickerOpen"
                        aria-controls="staff-picker-options"
                        @click="pickerOpen = !pickerOpen"
                    >
                        <span id="staff-picker-value">{{ selectionLabel }}</span
                        ><span aria-hidden="true">⌄</span>
                    </button>
                    <div
                        v-if="pickerOpen"
                        id="staff-picker-options"
                        class="absolute left-0 top-full z-40 mt-1 w-full rounded-lg border bg-popover text-popover-foreground shadow-lg"
                    >
                        <div class="border-b p-3">
                            <Input v-model="staffSearch" aria-label="Search staff options" placeholder="Search staff..." />
                        </div>
                        <label class="flex cursor-pointer items-center gap-3 border-b px-3 py-3">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :indeterminate="staffIds.length > 0 && !allSelected"
                                class="h-4 w-4 rounded border-input accent-primary"
                                @change="toggleAll"
                            />
                            Select All ({{ staff.length }})
                        </label>
                        <div class="max-h-60 overflow-y-auto">
                            <label
                                v-for="person in visibleStaff"
                                :key="person.id"
                                class="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-muted"
                            >
                                <input
                                    v-model="staffIds"
                                    type="checkbox"
                                    :value="person.id"
                                    class="h-4 w-4 shrink-0 rounded border-input accent-primary"
                                />{{ person.label }}
                            </label>
                            <p v-if="!visibleStaff.length" class="p-3 text-muted-foreground">No staff found.</p>
                        </div>
                        <div class="border-t p-2"><Button type="button" class="w-full" @click="apply">Apply Selection</Button></div>
                    </div>
                </div>
                <label class="grid gap-1.5 text-sm font-medium">Search<Input v-model="search" placeholder="Code, name or designation" /></label>
                <Button type="submit">Apply</Button>
                <div class="flex gap-2 sm:ml-auto">
                    <Button type="button" variant="outline" aria-label="Previous month" @click="moveMonth(-1)">Previous</Button>
                    <Button type="button" variant="outline" aria-label="Next month" @click="moveMonth(1)">Next</Button>
                </div>
                <p v-for="(error, key) in errors" :key="key" class="w-full text-sm text-red-600" role="alert">{{ error }}</p>
            </form>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div
                    v-for="stat in [
                        { label: 'Staff', value: totals.staff },
                        { label: 'Present Days', value: totals.present },
                        { label: 'Approved Leave', value: totals.leave },
                        { label: 'Pending Leave', value: totals.pending },
                        { label: 'Work Hours', value: totals.workLabel },
                    ]"
                    :key="stat.label"
                    class="rounded-xl border bg-card p-4"
                >
                    <p class="text-sm text-muted-foreground">{{ stat.label }}</p>
                    <p class="mt-1 text-2xl font-semibold">{{ stat.value }}</p>
                </div>
            </div>

            <div class="rounded-xl border bg-card">
                <div class="flex flex-wrap gap-x-5 gap-y-2 border-b p-4 text-sm">
                    <span class="font-medium text-emerald-700 dark:text-emerald-400">P · Present</span>
                    <span class="font-medium text-amber-700 dark:text-amber-400">L · Approved leave</span>
                    <span class="font-medium text-blue-700 dark:text-blue-400">LP · Pending leave</span>
                    <span class="text-muted-foreground">– · No record (not marked absent)</span>
                </div>
                <div class="max-h-[65vh] overflow-auto rounded-b-xl">
                    <table class="w-full border-collapse text-center text-xs">
                        <caption class="sr-only">
                            Office staff attendance for
                            {{
                                monthLabel
                            }}
                        </caption>
                        <thead class="sticky top-0 z-20 bg-slate-800 text-white">
                            <tr>
                                <th scope="col" class="sticky left-0 z-30 min-w-52 bg-slate-800 px-3 py-3 text-left">Staff</th>
                                <th v-for="day in days" :key="day.date" scope="col" class="min-w-10 border-l border-slate-600 px-1 py-3">
                                    <div class="text-sm">{{ day.number }}</div>
                                    <div class="mt-1 text-[10px] font-normal text-slate-300">{{ day.weekday }}</div>
                                </th>
                                <th scope="col" class="px-3">Present</th>
                                <th scope="col" class="px-3">Leave</th>
                                <th scope="col" class="px-3">Pending</th>
                                <th scope="col" class="min-w-24 px-3">Work Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-b">
                                <th scope="row" class="sticky left-0 z-10 border-r bg-card px-3 py-3 text-left font-medium">
                                    <div>{{ row.code }} - {{ row.name }}</div>
                                    <div class="mt-1 text-[11px] font-normal text-muted-foreground">{{ row.designation }}</div>
                                </th>
                                <td
                                    v-for="cell in row.cells"
                                    :key="cell.date"
                                    class="border-r"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': cell.status === 'P',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': cell.status === 'L',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300': cell.status === 'LP',
                                        'text-muted-foreground': cell.status === '-',
                                    }"
                                >
                                    <button
                                        v-if="cell.detail"
                                        type="button"
                                        class="min-h-11 w-full font-semibold underline decoration-dotted underline-offset-4 focus-visible:outline focus-visible:outline-2"
                                        :aria-label="`${row.name}, ${cell.date}: Present. View timings`"
                                        @click="showDetail(row, cell)"
                                    >
                                        P
                                    </button>
                                    <span v-else :title="`${cell.date}: ${labels[cell.status]}`">{{ cell.status === '-' ? '–' : cell.status }}</span>
                                </td>
                                <td class="p-2 font-semibold">{{ row.present }}</td>
                                <td class="p-2">{{ row.leave }}</td>
                                <td class="p-2">{{ row.pending }}</td>
                                <td class="whitespace-nowrap p-2">{{ row.workLabel }}</td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="days.length + 5" class="p-10 text-muted-foreground">No staff match these filters.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length" class="bg-muted font-semibold">
                            <tr>
                                <th scope="row" class="sticky left-0 z-10 bg-muted px-3 py-3 text-left">Daily present / Totals</th>
                                <td v-for="(count, index) in dailyPresent" :key="index" class="border-l p-1">{{ count }}</td>
                                <td>{{ totals.present }}</td>
                                <td>{{ totals.leave }}</td>
                                <td>{{ totals.pending }}</td>
                                <td class="whitespace-nowrap px-2">{{ totals.workLabel }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <p class="text-xs text-muted-foreground">
                All calendar dates are shown. Weekly off-days are not assumed. Work hours follow the Attendance Report rules; an open session today
                shows elapsed hours.
            </p>
        </div>
        <Dialog v-model:open="open">
            <DialogScrollContent v-if="selected" class="sm:max-w-lg">
                <DialogHeader
                    ><DialogTitle>{{ selected.row.code }} - {{ selected.row.name }}</DialogTitle
                    ><DialogDescription>{{ selected.cell.date }} · {{ selected.cell.detail?.workModeLabel }}</DialogDescription></DialogHeader
                >
                <div class="grid grid-cols-3 gap-3 rounded-lg bg-muted p-4 text-sm">
                    <div>
                        <p class="text-muted-foreground">Check In</p>
                        <p class="mt-1 font-semibold">{{ selected.cell.detail?.checkInDisplay || 'Not recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Check Out</p>
                        <p class="mt-1 font-semibold">
                            {{ selected.cell.detail?.checkOutDisplay || (selected.cell.detail?.checkInDisplay ? 'Open' : 'Not recorded') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Work Hours</p>
                        <p class="mt-1 font-semibold">{{ selected.cell.detail?.workHoursLabel }}</p>
                    </div>
                </div>
                <div v-if="selected.cell.detail?.sessionDisplaySegments.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="(session, index) in selected.cell.detail.sessionDisplaySegments"
                        :key="index"
                        class="rounded-md border px-3 py-2 text-sm"
                        >{{ session }}</span
                    >
                </div>
                <p v-if="selected.cell.detail?.note" class="whitespace-pre-wrap text-sm">{{ selected.cell.detail.note }}</p>
                <Button as-child variant="outline"
                    ><Link :href="`/office-attendance/report?staff_id=${selected.row.id}&from=${selected.cell.date}&to=${selected.cell.date}`"
                        >Open Attendance Report</Link
                    ></Button
                >
            </DialogScrollContent>
        </Dialog>
    </AppLayout>
</template>
