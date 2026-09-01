<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { matchesEmployeeSearch, sortByEmployeeSearch } from '@/lib/employee-search';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarRange, FileSpreadsheet, Printer, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface EmployeeOption {
    id: number;
    code: string | null;
    name: string;
    profession: string | null;
    type: string;
}

interface ProjectOption {
    id: number;
    code: string | null;
    name: string;
    type: string;
}

interface StatementRow {
    id: string;
    date: string;
    weekday: string;
    employeeCode: string | null;
    employeeName: string | null;
    profession: string | null;
    projectName: string | null;
    status: string;
    dayValue: number;
    overtimeHours: number;
    note: string | null;
    dailySalary: number | null;
    basicCost: number | null;
    overtimeCost: number | null;
    totalCost: number | null;
    missingSalary: boolean;
}

interface MatrixCell {
    code: string;
    status: string;
    note: string | null;
}

interface MatrixPerson {
    employeeCode: string | null;
    employeeName: string;
    profession: string | null;
    cells: MatrixCell[];
    presentDays: number;
    absentDays: number;
    leaveDays: number;
    notListed: number;
}

interface Matrix {
    dates: Array<{ value: string; label: string; weekday: string; isSunday: boolean }>;
    people: MatrixPerson[];
    footer: Array<{ present: number; absent: number }>;
    footerTotals: { present: number; absent: number };
}

interface Statement {
    layout: 'list' | 'grid';
    matrix: Matrix;
    mode: 'employee' | 'project' | 'type';
    subject: {
        id: number;
        code: string | null;
        name: string;
        profession: string | null;
        typeLabel: string;
        status: string;
        missingSalary: boolean;
    };
    rows: StatementRow[];
    totals: {
        entries: number;
        presentDays: number;
        present: number;
        absent: number;
        leave: number;
        overtimeHours: number;
        uniqueEmployees: number;
        projects: number;
        basicCost: number | null;
        overtimeCost: number | null;
        totalCost: number | null;
    };
    withSalary: boolean;
    rangeLabel: string;
}

const props = defineProps<{
    statement: Statement | null;
    filters: {
        mode: 'employee' | 'project' | 'type';
        layout: 'list' | 'grid';
        employeeType: string;
        employeeId: number | null;
        projectId: number | null;
        from: string;
        to: string;
        withSalary: boolean;
    };
    employees: EmployeeOption[];
    projects: ProjectOption[];
    employeeTypes: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Attendance', href: '/attendance' },
    { title: 'Statement', href: '/attendance/statement' },
];

const mode = ref(props.filters.mode);
const layout = ref(props.filters.layout);
const employeeType = ref(props.filters.employeeType);
const employeeId = ref(props.filters.employeeId ? String(props.filters.employeeId) : '');
const projectId = ref(props.filters.projectId ? String(props.filters.projectId) : '');
const from = ref(props.filters.from);
const to = ref(props.filters.to);
const withSalary = ref(props.filters.withSalary);
const employeeSearch = ref('');
const rowSearch = ref('');

/// Worker names reach this system through inconsistent transliteration, so the
/// picker uses the same forgiving, ranked matcher as the rest of the app.
const employeeOptions = computed(() =>
    sortByEmployeeSearch(props.employees, employeeSearch.value, (employee) => [employee.code, employee.name, employee.profession]),
);

const query = computed(() => {
    const params = new URLSearchParams({ mode: mode.value, layout: layout.value, from: from.value, to: to.value });

    if (mode.value === 'type') params.set('employee_type', employeeType.value);
    if (mode.value === 'employee' && employeeId.value) params.set('employee_id', employeeId.value);
    if (mode.value === 'project' && projectId.value) params.set('project_id', projectId.value);
    if (withSalary.value) params.set('with_salary', '1');

    return params.toString();
});

const exportUrl = computed(() => `/attendance/statement/export?${query.value}`);
const printUrl = computed(() => `/attendance/statement/print?${query.value}`);
const hasSubject = computed(() => {
    if (mode.value === 'type') return true;
    return mode.value === 'employee' ? Boolean(employeeId.value) : Boolean(projectId.value);
});

const applyFilters = () => {
    router.get(`/attendance/statement?${query.value}`, {}, { preserveState: false });
};

/// A month is the unit these statements are asked for, so moving between them
/// should not mean editing two dates by hand.
const shiftMonth = (step: number) => {
    const start = new Date(`${from.value}T00:00:00`);
    start.setMonth(start.getMonth() + step, 1);
    const year = start.getFullYear();
    const month = String(start.getMonth() + 1).padStart(2, '0');
    const lastDay = new Date(year, start.getMonth() + 1, 0).getDate();

    from.value = `${year}-${month}-01`;
    to.value = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;
    applyFilters();
};

const visibleRows = computed(() => {
    const statement = props.statement;

    if (!statement) return [];

    const search = rowSearch.value.trim();

    if (!search) return statement.rows;

    return statement.rows.filter((row) =>
        matchesEmployeeSearch([row.employeeName, row.employeeCode, row.projectName, row.status, row.date, row.note], search),
    );
});

const money = (value: number | null) => (value === null ? '-' : new Intl.NumberFormat('en-AE', { minimumFractionDigits: 2 }).format(value));

const cellClass = (code: string) => {
    if (code === 'P') return 'bg-green-100 text-green-800';
    if (code === 'H' || code === 'L') return 'bg-amber-100 text-amber-800';
    if (code === 'A') return 'bg-red-100 text-red-800';
    if (code === 'S') return 'bg-neutral-300 text-red-700 dark:bg-neutral-700';
    return 'bg-muted/60 font-normal text-muted-foreground';
};

const statusClass = (status: string) => {
    if (status === 'present') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'absent') return 'border-red-600/30 bg-red-600/10 text-red-700';
    return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
};
</script>

<template>
    <Head title="Attendance Statement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Attendance Statement</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Day-by-day attendance for one employee or one project, over any date range. Export it as Excel or save it as PDF.
                </p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <div class="inline-flex rounded-md border p-1">
                    <button
                        v-for="option in [
                            { value: 'type', label: 'By Employee Type' },
                            { value: 'employee', label: 'By Employee' },
                            { value: 'project', label: 'By Project' },
                        ]"
                        :key="option.value"
                        type="button"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="mode === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="mode = option.value as 'employee' | 'project' | 'type'"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_auto_auto_auto]">
                    <div v-if="mode === 'type'" class="grid gap-1">
                        <label class="text-xs text-muted-foreground" for="statement-type">Employee Type</label>
                        <select id="statement-type" v-model="employeeType" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="option in employeeTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Every employee of this type, worked or not, in one sheet.</p>
                    </div>

                    <div v-else-if="mode === 'employee'" class="grid gap-1">
                        <label class="text-xs text-muted-foreground" for="statement-employee">Employee</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="employeeSearch"
                                type="search"
                                class="mb-2 h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm"
                                placeholder="Search name, code or profession"
                            />
                        </div>
                        <select id="statement-employee" v-model="employeeId" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Select an employee</option>
                            <option v-for="employee in employeeOptions" :key="employee.id" :value="String(employee.id)">
                                {{ employee.code ? `${employee.code} - ` : '' }}{{ employee.name
                                }}{{ employee.profession ? ` (${employee.profession})` : '' }}
                            </option>
                        </select>
                    </div>

                    <div v-else class="grid gap-1">
                        <label class="text-xs text-muted-foreground" for="statement-project">Project</label>
                        <select id="statement-project" v-model="projectId" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Select a project</option>
                            <option v-for="project in projects" :key="project.id" :value="String(project.id)">
                                {{ project.code ? `${project.code} - ` : '' }}{{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-1">
                        <label class="text-xs text-muted-foreground" for="statement-from">From</label>
                        <input id="statement-from" v-model="from" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <label class="text-xs text-muted-foreground" for="statement-to">To</label>
                        <input id="statement-to" v-model="to" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="h-10 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground" @click="applyFilters">
                            Show
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <div class="inline-flex items-center gap-1 text-sm">
                        <CalendarRange class="size-4 text-muted-foreground" />
                        <button type="button" class="rounded border px-2 py-1 text-xs hover:bg-muted" @click="shiftMonth(-1)">Previous month</button>
                        <button type="button" class="rounded border px-2 py-1 text-xs hover:bg-muted" @click="shiftMonth(1)">Next month</button>
                    </div>
                    <div class="inline-flex rounded-md border p-1">
                        <button
                            v-for="option in [
                                { value: 'grid', label: 'Grid by person' },
                                { value: 'list', label: 'Day list' },
                            ]"
                            :key="option.value"
                            type="button"
                            class="rounded px-3 py-1.5 text-sm"
                            :class="layout === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="
                                layout = option.value as 'list' | 'grid';
                                applyFilters();
                            "
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="withSalary" type="checkbox" class="size-4 rounded border-input" @change="applyFilters" />
                        Include salary and cost
                    </label>
                    <p v-if="withSalary" class="text-xs text-muted-foreground">This copy shows pay. Keep it internal.</p>
                </div>
            </section>

            <div v-if="!statement" class="flex min-h-52 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                Choose {{ mode === 'employee' ? 'an employee' : 'a project' }} and press Show.
            </div>

            <template v-else>
                <section class="rounded-lg border bg-card p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold">
                                {{ statement.subject.code ? `${statement.subject.code} - ` : '' }}{{ statement.subject.name }}
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ statement.subject.profession || statement.subject.typeLabel }} · {{ statement.rangeLabel }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a
                                :href="exportUrl"
                                class="inline-flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                            >
                                <FileSpreadsheet class="size-4" />Excel
                            </a>
                            <a
                                :href="printUrl"
                                target="_blank"
                                class="inline-flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                            >
                                <Printer class="size-4" />Print / PDF
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Present Days</p>
                            <p class="mt-1 text-2xl font-semibold text-green-700">{{ statement.totals.presentDays }}</p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Absent</p>
                            <p class="mt-1 text-2xl font-semibold text-red-700">{{ statement.totals.absent }}</p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Leave</p>
                            <p class="mt-1 text-2xl font-semibold text-amber-700">{{ statement.totals.leave }}</p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Overtime Hours</p>
                            <p class="mt-1 text-2xl font-semibold">{{ statement.totals.overtimeHours }}</p>
                        </div>
                        <div v-if="statement.withSalary" class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Basic Cost</p>
                            <p class="mt-1 text-xl font-semibold">{{ money(statement.totals.basicCost) }}</p>
                        </div>
                        <div v-if="statement.withSalary" class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Overtime Cost</p>
                            <p class="mt-1 text-xl font-semibold">{{ money(statement.totals.overtimeCost) }}</p>
                        </div>
                        <div v-if="statement.withSalary" class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Total Cost</p>
                            <p class="mt-1 text-xl font-semibold text-amber-700">{{ money(statement.totals.totalCost) }}</p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">{{ statement.mode === 'project' ? 'Employees' : 'Projects' }}</p>
                            <p class="mt-1 text-2xl font-semibold">
                                {{ statement.mode === 'project' ? statement.totals.uniqueEmployees : statement.totals.projects }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="statement.withSalary && statement.subject.missingSalary"
                        class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                    >
                        <b>Cost is incomplete.</b> This employee has no salary setting, so every day is costed at zero.
                    </div>
                </section>

                <section v-if="statement.layout === 'grid'" class="overflow-hidden rounded-lg border bg-card">
                    <div class="border-b p-4">
                        <h2 class="font-medium">Attendance by person</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ statement.matrix.people.length }} people across {{ statement.matrix.dates.length }} worked days. Only days with a
                            record become columns.
                        </p>
                    </div>

                    <div v-if="statement.matrix.people.length" class="max-h-[34rem] overflow-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-[#c0504d] text-white">
                                    <th class="sticky left-0 z-20 min-w-[190px] bg-[#c0504d] px-3 py-2 text-left font-medium">Name</th>
                                    <th
                                        v-for="date in statement.matrix.dates"
                                        :key="date.value"
                                        class="min-w-[42px] px-1 py-2 text-center align-bottom font-medium"
                                        :class="date.isSunday ? 'bg-neutral-500' : ''"
                                    >
                                        <span class="inline-block whitespace-nowrap [writing-mode:vertical-rl] [transform:rotate(180deg)]">
                                            {{ date.label }}
                                        </span>
                                    </th>
                                    <th class="min-w-[70px] px-2 py-2 text-center font-medium">Present</th>
                                    <th class="min-w-[70px] px-2 py-2 text-center font-medium">Absent</th>
                                    <th class="min-w-[80px] px-2 py-2 text-center font-medium">Not listed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="person in statement.matrix.people" :key="person.employeeName" class="border-b last:border-b-0">
                                    <td class="sticky left-0 z-10 whitespace-nowrap border-r bg-card px-3 py-1.5">
                                        {{ person.employeeCode ? `${person.employeeCode} - ` : '' }}{{ person.employeeName }}
                                    </td>
                                    <td
                                        v-for="(cell, index) in person.cells"
                                        :key="index"
                                        class="border-r px-1 py-1.5 text-center font-semibold"
                                        :class="cellClass(cell.code)"
                                        :title="cell.note || ''"
                                    >
                                        {{ cell.code === '-' ? '–' : cell.code }}
                                    </td>
                                    <td class="border-r px-2 py-1.5 text-center font-semibold">{{ person.presentDays }}</td>
                                    <td class="border-r px-2 py-1.5 text-center">{{ person.absentDays }}</td>
                                    <td class="px-2 py-1.5 text-center text-muted-foreground">{{ person.notListed }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 font-semibold text-[#9b2c2c]">
                                <tr>
                                    <td class="sticky left-0 z-10 whitespace-nowrap border-r bg-card px-3 py-1.5">Headcount present that day</td>
                                    <td v-for="(day, index) in statement.matrix.footer" :key="index" class="border-r px-1 py-1.5 text-center">
                                        {{ day.present }}
                                    </td>
                                    <td class="border-r px-2 py-1.5 text-center">{{ statement.matrix.footerTotals.present }}</td>
                                    <td class="border-r"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="sticky left-0 z-10 whitespace-nowrap border-r bg-card px-3 py-1.5">Marked absent that day</td>
                                    <td v-for="(day, index) in statement.matrix.footer" :key="index" class="border-r px-1 py-1.5 text-center">
                                        {{ day.absent }}
                                    </td>
                                    <td class="border-r px-2 py-1.5 text-center">{{ statement.matrix.footerTotals.absent }}</td>
                                    <td class="border-r"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="flex min-h-40 items-center justify-center text-sm text-muted-foreground">
                        No attendance recorded in this date range.
                    </div>

                    <p class="border-t p-3 text-xs text-muted-foreground">
                        <b>P</b> Present · <b>H</b> Half day · <b>A</b> Absent · <b>L</b> Leave · <b>S</b> Sunday · <b>–</b> Not listed that day
                    </p>
                </section>

                <section v-else class="overflow-hidden rounded-lg border bg-card">
                    <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="font-medium">Daily Record</h2>
                            <p class="text-sm text-muted-foreground">{{ visibleRows.length }} of {{ statement.rows.length }} days.</p>
                        </div>
                        <div class="relative w-full sm:w-72">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="rowSearch"
                                type="search"
                                class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm"
                                placeholder="Search a day, project or person"
                            />
                        </div>
                    </div>

                    <div v-if="visibleRows.length" class="max-h-[32rem] overflow-auto">
                        <table class="w-full text-sm" :class="statement.mode === 'project' ? 'min-w-[1000px]' : 'min-w-[860px]'">
                            <thead class="sticky top-0 border-b bg-muted/60 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th class="w-[110px] px-3 py-2 font-medium">Date</th>
                                    <th class="w-[60px] px-3 py-2 font-medium">Day</th>
                                    <th v-if="statement.mode === 'project'" class="w-[170px] px-3 py-2 font-medium">Employee</th>
                                    <th class="px-3 py-2 font-medium">Project</th>
                                    <th class="w-[110px] px-3 py-2 font-medium">Status</th>
                                    <th class="w-[70px] px-3 py-2 text-right font-medium">Day</th>
                                    <th class="w-[70px] px-3 py-2 text-right font-medium">OT</th>
                                    <th v-if="statement.mode === 'employee'" class="w-[170px] px-3 py-2 font-medium">Note</th>
                                    <th v-if="statement.withSalary" class="w-[110px] px-3 py-2 text-right font-medium">Basic</th>
                                    <th v-if="statement.withSalary" class="w-[100px] px-3 py-2 text-right font-medium">OT Cost</th>
                                    <th v-if="statement.withSalary" class="w-[110px] px-3 py-2 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in visibleRows" :key="row.id" class="border-b last:border-b-0">
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2 text-xs text-muted-foreground">{{ row.weekday }}</td>
                                    <td v-if="statement.mode === 'project'" class="px-3 py-2">
                                        <p class="truncate font-medium">{{ row.employeeCode ? `${row.employeeCode} - ` : '' }}{{ row.employeeName }}</p>
                                        <p class="truncate text-xs text-muted-foreground">{{ row.profession || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-muted-foreground">{{ row.projectName || '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(row.status)">
                                            {{ row.status }}<template v-if="row.status === 'present' && row.dayValue === 0.5"> · Half</template>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ row.dayValue }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ row.overtimeHours || '-' }}</td>
                                    <td v-if="statement.mode === 'employee'" class="px-3 py-2 text-xs text-muted-foreground">{{ row.note || '-' }}</td>
                                    <td v-if="statement.withSalary" class="px-3 py-2 text-right tabular-nums">{{ money(row.basicCost) }}</td>
                                    <td v-if="statement.withSalary" class="px-3 py-2 text-right tabular-nums">{{ money(row.overtimeCost) }}</td>
                                    <td v-if="statement.withSalary" class="px-3 py-2 text-right font-medium tabular-nums">
                                        {{ money(row.totalCost) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="flex min-h-40 items-center justify-center text-sm text-muted-foreground">
                        <template v-if="rowSearch.trim()">Nothing matches this search.</template>
                        <template v-else>No attendance recorded in this date range.</template>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
