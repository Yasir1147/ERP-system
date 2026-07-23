<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileText, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TimesheetDate {
    date: string;
    day: string;
    weekday: string;
    isToday: boolean;
    isWeekend: boolean;
}

interface TimesheetDay {
    date: string;
    status: string | null;
    projectName: string | null;
    overtimeProjectName: string | null;
    overtimeHours: number | null;
    leaveReason: string | null;
}

interface TimesheetEmployee {
    id: number;
    code: string;
    name: string;
    profession: string;
    status: string;
    presentDays: number;
    days: TimesheetDay[];
}

interface TypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    dates: TimesheetDate[];
    employees: TimesheetEmployee[];
    filters: {
        type: string;
        month: string;
    };
    typeOptions: TypeOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Attendance',
        href: '/attendance',
    },
    {
        title: 'Timesheet',
        href: '/attendance/timesheet',
    },
];

const filterType = ref(props.filters.type);
const filterMonth = ref(props.filters.month);
const pageSize = ref('a3');
const search = ref('');
const selectedEmployeeId = ref<number | null>(null);

const filteredEmployees = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.employees;
    }

    return props.employees.filter((employee) =>
        [employee.code, employee.name, employee.profession, employee.status].some((value) => value.toLowerCase().includes(query)),
    );
});

const applyFilters = () => {
    router.get(
        '/attendance/timesheet',
        {
            type: filterType.value,
            month: filterMonth.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const printUrl = computed(() => {
    const params = new URLSearchParams({
        type: filterType.value,
        month: filterMonth.value,
        page_size: pageSize.value,
    });

    return `/attendance/timesheet-print?${params.toString()}`;
});

const statusLabel = (status: string | null) => {
    if (!status) return '';
    if (status === 'present') return 'Present';
    if (status === 'absent') return 'Absent';
    return 'Leave';
};

const cellClass = (day: TimesheetDay) => {
    if (day.status === 'present') return 'bg-green-50 text-green-950 dark:bg-green-950/20 dark:text-green-100';
    if (day.status === 'absent') return 'bg-red-50 text-red-950 dark:bg-red-950/20 dark:text-red-100';
    if (day.status === 'leave') return 'bg-amber-50 text-amber-950 dark:bg-amber-950/20 dark:text-amber-100';
    return 'bg-background';
};

const selectEmployeeRow = (employeeId: number) => {
    selectedEmployeeId.value = selectedEmployeeId.value === employeeId ? null : employeeId;
};
</script>

<template>
    <Head title="Attendance Timesheet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Attendance Timesheet</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Monthly employee attendance with project and overtime details.</p>
                </div>

                <div class="grid gap-2 sm:grid-cols-[220px_180px_auto_150px_auto]">
                    <select
                        v-model="filterType"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <input
                        v-model="filterMonth"
                        type="month"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                    <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" @click="applyFilters">
                        Filter
                    </button>
                    <select
                        v-model="pageSize"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="a3">A3 Landscape</option>
                        <option value="a4">A4 Landscape</option>
                    </select>
                    <a
                        :href="printUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-accent"
                    >
                        <FileText class="size-4" />
                        Report PDF
                    </a>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Monthly Sheet</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredEmployees.length }} of {{ employees.length }} employees</p>
                    </div>
                    <div class="relative w-full sm:w-80">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="search"
                            class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm"
                            placeholder="Search by code, name, or profession"
                        />
                    </div>
                </div>

                <div v-if="filteredEmployees.length === 0" class="flex min-h-56 items-center justify-center text-sm text-muted-foreground">
                    No employees found for this timesheet.
                </div>

                <div v-else class="max-h-[calc(100vh-260px)] overflow-auto">
                    <table class="border-collapse text-sm" :style="{ minWidth: `${356 + dates.length * 92}px` }">
                        <thead class="sticky top-0 z-20 bg-card">
                            <tr>
                                <th class="sticky left-0 z-30 w-[260px] min-w-[260px] border-b border-r bg-card px-3 py-3 text-left font-medium">
                                    Employee
                                </th>
                                <th
                                    v-for="date in dates"
                                    :key="date.date"
                                    class="w-[92px] min-w-[92px] border-b border-r px-2 py-2 text-center font-medium"
                                    :class="date.isToday ? 'bg-primary/10' : date.isWeekend ? 'bg-muted/60' : 'bg-card'"
                                >
                                    <div>{{ date.day }}</div>
                                    <div class="mt-0.5 text-[11px] font-normal text-muted-foreground">{{ date.weekday }}</div>
                                </th>
                                <th class="sticky right-0 z-30 w-24 min-w-24 border-b border-l bg-indigo-50 px-2 py-2 text-center font-semibold text-indigo-950 dark:bg-indigo-950 dark:text-indigo-100">
                                    Present<br />Days
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="employee in filteredEmployees"
                                :key="employee.id"
                                class="cursor-pointer"
                                :class="selectedEmployeeId === employee.id ? 'outline outline-2 -outline-offset-2 outline-primary/50' : ''"
                                @click="selectEmployeeRow(employee.id)"
                            >
                                <th
                                    class="sticky left-0 z-10 w-[260px] min-w-[260px] border-b border-r px-3 py-3 text-left align-middle font-medium"
                                    :class="selectedEmployeeId === employee.id ? 'bg-primary/15' : 'bg-card'"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate">{{ employee.code }} - {{ employee.name }}</p>
                                        <p class="truncate text-xs font-normal text-muted-foreground">{{ employee.profession }}</p>
                                    </div>
                                </th>
                                <td
                                    v-for="day in employee.days"
                                    :key="`${employee.id}-${day.date}`"
                                    class="h-[70px] w-[92px] min-w-[92px] border-b border-r p-1 align-top"
                                    :class="[cellClass(day), selectedEmployeeId === employee.id ? 'ring-1 ring-inset ring-primary/30 brightness-[0.98]' : '']"
                                >
                                    <div v-if="day.status" class="flex h-full flex-col justify-between gap-1 overflow-hidden rounded-sm px-1 py-1">
                                        <p class="truncate text-[11px] font-medium leading-tight">
                                            {{ day.projectName || statusLabel(day.status) }}
                                        </p>
                                        <p v-if="day.status === 'present' && day.overtimeHours" class="truncate text-[11px] leading-tight text-muted-foreground">
                                            OT {{ day.overtimeHours }}H<template v-if="day.overtimeProjectName && day.overtimeProjectName !== day.projectName"> - {{ day.overtimeProjectName }}</template>
                                        </p>
                                        <p v-else-if="day.status === 'leave' && day.leaveReason" class="truncate text-[11px] leading-tight text-muted-foreground">
                                            {{ day.leaveReason }}
                                        </p>
                                    </div>
                                </td>
                                <td class="sticky right-0 z-10 w-24 min-w-24 border-b border-l bg-indigo-50 px-2 text-center align-middle text-base font-semibold text-indigo-950 dark:bg-indigo-950 dark:text-indigo-100">
                                    {{ employee.presentDays }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
