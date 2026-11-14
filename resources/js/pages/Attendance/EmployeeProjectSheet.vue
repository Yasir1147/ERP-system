<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { matchesEmployeeSearch } from '@/lib/employee-search';
import { Head, Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';
type Metrics = { labourDays: number; overtimeHours: number; basicCost: number; overtimeCost: number };
type Row = Metrics & {
    id: number;
    code: string | null;
    name: string;
    profession: string | null;
    dailySalary: number | null;
    missingPayroll: boolean;
    bonus: number;
    totalCost: number;
    projects: Metrics[];
};
const props = defineProps<{
    report: {
        filters: { employeeIds: number[]; from: string; to: string };
        projects: { id: number; code: string | null; name: string }[];
        rows: Row[];
        totals: Metrics & { bonus: number; totalCost: number; projects: Metrics[] };
        missingPayroll: number;
    };
    employees: { id: number; code: string | null; name: string; profession: string | null; type: string; status: string }[];
    employeeTypes: Record<string, string>;
    errors?: Record<string, string>;
}>();
const from = ref(props.report.filters.from);
const to = ref(props.report.filters.to);
const ids = ref([...props.report.filters.employeeIds]);
const search = ref('');
const type = ref('');
const open = ref(false);
const picker = ref<HTMLElement | null>(null);
onClickOutside(picker, () => {
    open.value = false;
});
const options = computed(() =>
    props.employees.filter((e) => (!type.value || e.type === type.value) && matchesEmployeeSearch([e.code, e.name, e.profession], search.value)),
);
const allVisible = computed(() => options.value.length > 0 && options.value.every((e) => ids.value.includes(e.id)));
function selectAll() {
    const visible = options.value.map((e) => e.id);
    ids.value = allVisible.value ? ids.value.filter((id) => !visible.includes(id)) : [...new Set([...ids.value, ...visible])];
}
function apply() {
    open.value = false;
    router.get('/attendance/statement', { mode: 'employee', from: from.value, to: to.value, employee_ids: ids.value }, { preserveState: false });
}
function shiftMonth(step: number) {
    const date = new Date(`${from.value}T12:00:00`);
    date.setMonth(date.getMonth() + step, 1);
    const month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    from.value = `${month}-01`;
    to.value = `${month}-${new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()}`;
    apply();
}
const query = computed(() => {
    const q = new URLSearchParams({ mode: 'employee', from: props.report.filters.from, to: props.report.filters.to });
    props.report.filters.employeeIds.forEach((id) => q.append('employee_ids[]', String(id)));
    return q.toString();
});
const money = (n: number | null) => (n === null ? 'Missing' : n.toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
const metricKeys = ['labourDays', 'overtimeHours', 'basicCost', 'overtimeCost'] as const;
const headings = ['Days', 'OT Hours', 'Salary Cost (AED)', 'OT Cost (AED)'];
const breadcrumbs = [
    { title: 'Attendance', href: '/attendance' },
    { title: 'By Employee', href: '/attendance/statement?mode=employee' },
];
</script>

<template>
    <Head title="Employee Project Salary Sheet" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Employee Project Salary Sheet</h1>
                <p class="mt-1 text-sm text-muted-foreground">Compare each selected employee's days, overtime and salary costs across projects.</p>
            </div>
            <form class="rounded-xl border bg-card p-4" @submit.prevent="apply">
                <div class="mb-4 flex flex-wrap gap-1 text-sm">
                    <Link :href="`/attendance/statement?mode=type&from=${from}&to=${to}`" class="rounded border px-3 py-2">By Employee Type</Link>
                    <span class="rounded bg-primary px-3 py-2 text-primary-foreground">By Employee</span>
                    <Link :href="`/attendance/statement?mode=project&from=${from}&to=${to}`" class="rounded border px-3 py-2">By Project</Link>
                    <Link :href="`/attendance/project-summary?from=${from}&to=${to}`" class="rounded border px-3 py-2">Project Summary</Link>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div ref="picker" class="relative w-full sm:w-96" @keydown.esc="open = false">
                        <label class="mb-1 block text-sm" for="employee-picker">Employees</label>
                        <button
                            id="employee-picker"
                            type="button"
                            :aria-expanded="open"
                            aria-controls="employee-options"
                            class="flex h-10 w-full items-center justify-between rounded border bg-background px-3 text-sm"
                            @click="open = !open"
                        >
                            {{ ids.length ? `${ids.length} employees selected` : 'Select employees' }}<span aria-hidden="true">⌄</span>
                        </button>
                        <div
                            v-if="open"
                            id="employee-options"
                            class="absolute z-40 mt-1 w-full rounded-lg border bg-popover text-popover-foreground shadow-lg"
                        >
                            <div class="grid gap-2 border-b p-3">
                                <input
                                    v-model="search"
                                    type="search"
                                    placeholder="Search code, name or profession"
                                    aria-label="Search employees"
                                    class="rounded border bg-background p-2"
                                /><select v-model="type" aria-label="Employee type" class="rounded border bg-background p-2">
                                    <option value="">All Employee Types</option>
                                    <option v-for="(label, value) in employeeTypes" :key="value" :value="value">{{ label }}</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 border-b p-3"
                                ><input
                                    type="checkbox"
                                    :checked="allVisible"
                                    :indeterminate="!allVisible && options.some((e) => ids.includes(e.id))"
                                    @change="selectAll"
                                />Select All Matching ({{ options.length }})</label
                            >
                            <div class="max-h-64 overflow-auto">
                                <label
                                    v-for="employee in options"
                                    :key="employee.id"
                                    class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-muted"
                                    ><input v-model="ids" type="checkbox" :value="employee.id" /><span
                                        >{{ employee.code }} - {{ employee.name
                                        }}<small v-if="employee.status === 'left'" class="ml-1 text-muted-foreground">(Left)</small></span
                                    ></label
                                >
                                <p v-if="!options.length" class="p-3 text-sm">No employees found.</p>
                            </div>
                            <button type="button" class="m-3 rounded border px-3 py-1 text-sm" @click="ids = []">Clear Selection</button>
                        </div>
                    </div>
                    <label class="grid gap-1 text-sm"
                        >From<input v-model="from" type="date" required class="h-10 rounded border bg-background px-3"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >To<input v-model="to" type="date" required :min="from" class="h-10 rounded border bg-background px-3"
                    /></label>
                    <button class="h-10 rounded bg-primary px-5 text-primary-foreground">Show</button>
                </div>
                <p v-for="(error, key) in errors" :key="key" class="mt-2 text-sm text-red-600">{{ error }}</p>
                <div class="mt-3 flex gap-2 text-xs">
                    <button type="button" class="rounded border px-2 py-1" @click="shiftMonth(-1)">Previous month</button
                    ><button type="button" class="rounded border px-2 py-1" @click="shiftMonth(1)">Next month</button>
                </div>
            </form>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4">
                <div>
                    <h2 class="font-semibold">{{ report.filters.from }} to {{ report.filters.to }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ report.rows.length }} employees · {{ report.projects.length }} projects · Total {{ money(report.totals.totalCost) }} AED
                    </p>
                </div>
                <div class="flex gap-2">
                    <a :href="`/attendance/statement/export?${query}`" class="rounded border px-3 py-2 text-sm">Excel</a
                    ><a :href="`/attendance/statement/print?${query}`" target="_blank" rel="noopener" class="rounded border px-3 py-2 text-sm"
                        >Print / PDF</a
                    >
                </div>
            </div>
            <p v-if="report.missingPayroll" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ report.missingPayroll }} employee(s) have missing payroll settings. Their salary/OT costs are incomplete.
            </p>
            <div class="max-h-[70vh] overflow-auto rounded-xl border bg-card" tabindex="0" role="region" aria-label="Employee project salary sheet">
                <table class="w-full border-separate border-spacing-0 whitespace-nowrap text-xs">
                    <thead class="sticky top-0 z-20 bg-muted">
                        <tr>
                            <th rowspan="2">S.No</th>
                            <th rowspan="2" class="sticky left-0 z-10 min-w-60 bg-muted text-left">Name / Code</th>
                            <th rowspan="2">Salary (AED)</th>
                            <th rowspan="2">OT Cost (AED)</th>
                            <th rowspan="2">Per Day (AED)</th>
                            <th rowspan="2">Bonus (AED)</th>
                            <th
                                v-for="project in report.projects"
                                :key="project.id"
                                colspan="4"
                                :title="project.name"
                                class="project-start project-end"
                            >
                                {{ project.code ? `${project.code} - ` : '' }}{{ project.name }}
                            </th>
                            <th rowspan="2">Total Days</th>
                            <th rowspan="2">Total OT Hours</th>
                            <th rowspan="2">Total (AED)</th>
                        </tr>
                        <tr>
                            <template v-for="project in report.projects" :key="project.id"
                                ><th
                                    v-for="(heading, index) in headings"
                                    :key="heading"
                                    :class="{ 'project-start': index === 0, 'project-end': index === headings.length - 1 }"
                                >
                                    {{ heading }}
                                </th></template
                            >
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in report.rows" :key="row.id" class="even:bg-muted/30">
                            <td>{{ index + 1 }}</td>
                            <td class="sticky left-0 bg-card text-left">
                                <strong>{{ row.name }} {{ row.code }}</strong
                                ><span v-if="row.missingPayroll" class="block text-red-600">Missing payroll</span>
                            </td>
                            <td>{{ money(row.basicCost) }}</td>
                            <td>{{ money(row.overtimeCost) }}</td>
                            <td>{{ money(row.dailySalary) }}</td>
                            <td>{{ money(row.bonus) }}</td>
                            <template v-for="(cell, p) in row.projects" :key="p"
                                ><td
                                    v-for="metric in metricKeys"
                                    :key="metric"
                                    :class="{ 'project-start': metric === 'labourDays', 'project-end': metric === 'overtimeCost' }"
                                >
                                    {{ metric === 'labourDays' ? cell[metric] : money(cell[metric]) }}
                                </td></template
                            >
                            <td>{{ row.labourDays }}</td>
                            <td>{{ money(row.overtimeHours) }}</td>
                            <td class="font-semibold">{{ money(row.totalCost) }}</td>
                        </tr>
                        <tr v-if="!report.rows.length">
                            <td :colspan="9 + report.projects.length * 4" class="p-8 text-center text-muted-foreground">
                                Select employees and choose Show to generate the sheet.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.rows.length" class="bg-muted font-semibold">
                        <tr>
                            <td></td>
                            <td class="sticky left-0 bg-muted text-left">Totals</td>
                            <td>{{ money(report.totals.basicCost) }}</td>
                            <td>{{ money(report.totals.overtimeCost) }}</td>
                            <td>—</td>
                            <td>{{ money(report.totals.bonus) }}</td>
                            <template v-for="(cell, p) in report.totals.projects" :key="p"
                                ><td
                                    v-for="metric in metricKeys"
                                    :key="metric"
                                    :class="{ 'project-start': metric === 'labourDays', 'project-end': metric === 'overtimeCost' }"
                                >
                                    {{ metric === 'labourDays' ? cell[metric] : money(cell[metric]) }}
                                </td></template
                            >
                            <td>{{ report.totals.labourDays }}</td>
                            <td>{{ money(report.totals.overtimeHours) }}</td>
                            <td>{{ money(report.totals.totalCost) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-xs leading-5 text-muted-foreground">
                Days are full/half labour days. Overtime is decimal hours (1.50 = 1 hr 30 min), allocated to the OT project when specified. Salary
                uses current payroll daily rates; Fixed 30 Days uses monthly salary ÷ 30. Bonus includes saved payroll bonuses for the months touched
                by the range and is not allocated to projects. Total = Salary + OT Cost + Bonus. Overhead and other payroll adjustments are excluded.
            </p>
        </div>
    </AppLayout>
</template>

<style scoped>
th,
td {
    border: 1px solid hsl(var(--border));
    padding: 0.65rem;
    text-align: right;
}
th {
    text-align: center;
}
thead th {
    background: hsl(var(--muted));
}
.project-start {
    border-left: 2px solid #111;
}
.project-end {
    border-right: 2px solid #111;
}
:global(.dark) .project-start {
    border-left-color: #a3a3a3;
}
:global(.dark) .project-end {
    border-right-color: #a3a3a3;
}
</style>
