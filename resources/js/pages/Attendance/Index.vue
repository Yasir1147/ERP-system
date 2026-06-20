<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BriefcaseBusiness, CalendarCheck, Clock3, ClipboardX, Plane, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    name: string;
    profession: string;
    type: string;
    status: string;
    label: string;
}

interface AttendanceRecord {
    id: string;
    employeeId: number;
    employeeName: string;
    employeeProfession: string;
    employeeType: string;
    projectName: string | null;
    status: string;
    dateRaw: string;
    date: string;
    reason: string | null;
    overtimeHours: number | null;
    submittedBy: string | null;
    submittedByRole: string | null;
}

interface Summary {
    present: number;
    absent: number;
    leave: number;
    overtimeDays: number;
    overtimeHours: number;
    totalRecords: number;
}

interface ProjectSummary {
    projectName: string;
    days: number;
    overtimeHours: number;
}

interface TypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    employees: Employee[];
    records: AttendanceRecord[];
    summary: Summary;
    projectSummary: ProjectSummary[];
    filters: {
        type: string;
        employeeId: string;
        startDate: string;
        endDate: string;
    };
    typeOptions: TypeOption[];
    employeeTypes: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Attendance',
        href: '/attendance',
    },
];

const filterType = ref(props.filters.type);
const filterEmployeeId = ref(props.filters.employeeId);
const startDate = ref(props.filters.startDate);
const endDate = ref(props.filters.endDate);
const search = ref('');

const employeeOptions = computed(() => props.employees.filter((employee) => filterType.value === 'all' || employee.type === filterType.value));
const maxProjectDays = computed(() => Math.max(1, ...props.projectSummary.map((project) => project.days)));
const maxStatusCount = computed(() => Math.max(1, props.summary.present, props.summary.absent, props.summary.leave));

const filteredRecords = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.records;
    }

    return props.records.filter((record) =>
        [
            record.employeeName,
            record.employeeProfession,
            props.employeeTypes[record.employeeType],
            record.projectName,
            record.status,
            record.reason,
            record.submittedBy,
            record.date,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)),
    );
});

watch(filterType, () => {
    if (!employeeOptions.value.some((employee) => String(employee.id) === filterEmployeeId.value)) {
        filterEmployeeId.value = 'all';
    }
});

const applyFilters = () => {
    router.get(
        '/attendance',
        {
            type: filterType.value,
            employee_id: filterEmployeeId.value,
            start_date: startDate.value,
            end_date: endDate.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const statusLabel = (status: string) => status.charAt(0).toUpperCase() + status.slice(1);

const statusClass = (status: string) => {
    if (status === 'present') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'absent') return 'border-red-600/30 bg-red-600/10 text-red-700';
    return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
};

const submittedByLabel = (record: AttendanceRecord) => {
    if (!record.submittedBy) {
        return '-';
    }

    return record.submittedByRole === 'admin' ? `${record.submittedBy} (Admin)` : record.submittedBy;
};
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Attendance Report</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Check employee attendance by type, employee, and date range.</p>
                </div>
                <div class="grid gap-2 lg:grid-cols-[180px_240px_160px_160px_auto]">
                    <select
                        v-model="filterType"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select
                        v-model="filterEmployeeId"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="all">All Employees</option>
                        <option v-for="employee in employeeOptions" :key="employee.id" :value="String(employee.id)">
                            {{ employee.label }}
                        </option>
                    </select>
                    <input
                        v-model="startDate"
                        type="date"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                    <input
                        v-model="endDate"
                        type="date"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                    <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" @click="applyFilters">
                        Filter
                    </button>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-5">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Present</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.present }}</p>
                        </div>
                        <CalendarCheck class="size-6 text-green-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Absent</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.absent }}</p>
                        </div>
                        <ClipboardX class="size-6 text-red-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Leave</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.leave }}</p>
                        </div>
                        <Plane class="size-6 text-amber-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Overtime</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.overtimeHours }}</p>
                            <p class="text-xs text-muted-foreground">{{ summary.overtimeDays }} days</p>
                        </div>
                        <Clock3 class="size-6 text-sky-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Projects</p>
                            <p class="mt-2 text-3xl font-semibold">{{ projectSummary.length }}</p>
                        </div>
                        <BriefcaseBusiness class="size-6 text-muted-foreground" />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <h2 class="text-base font-medium">Status Overview</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ summary.totalRecords }} total records in selected range.</p>
                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between text-sm">
                                <span>Present</span>
                                <span class="font-semibold">{{ summary.present }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-green-600" :style="{ width: `${(summary.present / maxStatusCount) * 100}%` }" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between text-sm">
                                <span>Absent</span>
                                <span class="font-semibold">{{ summary.absent }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-red-600" :style="{ width: `${(summary.absent / maxStatusCount) * 100}%` }" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between text-sm">
                                <span>Leave</span>
                                <span class="font-semibold">{{ summary.leave }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-amber-500" :style="{ width: `${(summary.leave / maxStatusCount) * 100}%` }" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <h2 class="text-base font-medium">Project Summary</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Present days grouped by project.</p>
                    <div v-if="projectSummary.length" class="mt-5 grid max-h-72 gap-4 overflow-y-auto pr-2">
                        <div v-for="project in projectSummary" :key="project.projectName" class="grid gap-2">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ project.projectName }}</p>
                                    <p class="text-xs text-muted-foreground">Overtime: {{ project.overtimeHours }} hrs</p>
                                </div>
                                <span class="shrink-0 font-semibold">{{ project.days }} days</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${(project.days / maxProjectDays) * 100}%` }" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 flex min-h-44 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                        No project attendance found for this range.
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Attendance Detail</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ filteredRecords.length }} of {{ records.length }} records.</p>
                    </div>
                    <div class="relative w-full sm:w-72">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" type="search" class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm" placeholder="Search table" />
                    </div>
                </div>

                <div v-if="filteredRecords.length" class="mt-4 overflow-hidden rounded-md border">
                    <div class="grid min-w-[980px] grid-cols-[0.7fr_1fr_0.75fr_0.75fr_0.55fr_0.6fr_0.85fr] border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                        <span>Date</span>
                        <span>Employee</span>
                        <span>Type</span>
                        <span>Project / Reason</span>
                        <span>Status</span>
                        <span>Overtime</span>
                        <span>Submitted By</span>
                    </div>
                    <div class="max-h-[520px] overflow-auto">
                        <div
                            v-for="record in filteredRecords"
                            :key="record.id"
                            class="grid min-w-[980px] grid-cols-[0.7fr_1fr_0.75fr_0.75fr_0.55fr_0.6fr_0.85fr] items-center gap-3 border-b px-3 py-3 text-sm last:border-b-0"
                        >
                            <span class="text-muted-foreground">{{ record.date }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ record.employeeName }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ record.employeeProfession }}</p>
                            </div>
                            <span class="truncate text-muted-foreground">{{ employeeTypes[record.employeeType] }}</span>
                            <span class="truncate text-muted-foreground">{{ record.reason || record.projectName || '-' }}</span>
                            <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">{{ statusLabel(record.status) }}</span>
                            <span class="text-muted-foreground">{{ record.overtimeHours ? `${record.overtimeHours} hrs` : '-' }}</span>
                            <span class="truncate text-muted-foreground">{{ submittedByLabel(record) }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-4 flex min-h-56 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                    No attendance records match the selected filters.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
