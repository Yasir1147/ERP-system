<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CalendarCheck, ClipboardX, Plane, Search, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Summary {
    presentToday: number;
    absentToday: number;
    leaveToday: number;
    totalEmployees: number;
}

interface ProjectAttendance {
    id: number;
    name: string;
    type: string;
    employeeCount: number;
}

interface AttendanceRecord {
    id: number | string;
    employeeCode: string | null;
    employeeName: string;
    employeeProfession: string;
    employeeType: string;
    projectName: string | null;
    overtimeProjectName: string | null;
    status: string;
    date: string;
    leaveReason: string | null;
    overtimeHours: number | null;
    submittedBy: string | null;
    submittedByRole: string | null;
}

interface MonthlySummary {
    type: string;
    label: string;
    totalEmployees: number;
    present: number;
    absent: number;
    leave: number;
}

interface CompletedLongLeave {
    id: number;
    employeeCode: string | null;
    employeeName: string;
    employeeProfession: string;
    employeeType: string;
    employeeStatus: string;
    startDateLabel: string;
    endDateLabel: string;
    durationDays: number;
    reason: string | null;
}

interface TypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    summary: Summary;
    projectAttendance: ProjectAttendance[];
    attendanceRecords: {
        rope_access: AttendanceRecord[];
        contracting: AttendanceRecord[];
    };
    monthlySummary: MonthlySummary[];
    completedLongLeaves: CompletedLongLeave[];
    selectedDate: string;
    selectedDateLabel: string;
    selectedMonthLabel: string;
    selectedType: string;
    typeOptions: TypeOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const filterDate = ref(props.selectedDate);
const filterType = ref(props.selectedType);
const ropeSearch = ref('');
const contractingSearch = ref('');

const maxProjectCount = computed(() => Math.max(1, ...props.projectAttendance.map((project) => project.employeeCount)));
const showRopeAccess = computed(() => filterType.value === 'all' || filterType.value === 'rope_access');
const showContracting = computed(() => filterType.value === 'all' || filterType.value === 'contracting');

const applyFilters = () => {
    router.get('/dashboard', { date: filterDate.value, type: filterType.value }, { preserveScroll: true, preserveState: false });
};

const statusLabel = (status: string) => status.charAt(0).toUpperCase() + status.slice(1);

const statusClass = (status: string) => {
    if (status === 'present') return 'border-green-600/30 bg-green-600/10 text-green-600';
    if (status === 'absent') return 'border-red-600/30 bg-red-600/10 text-red-600';
    return 'border-amber-600/30 bg-amber-600/10 text-amber-600';
};

const matchesSearch = (record: AttendanceRecord, query: string) => {
    const normalized = query.trim().toLowerCase();

    if (!normalized) {
        return true;
    }

    return [record.employeeName, record.employeeCode, record.employeeProfession, record.projectName, record.leaveReason, record.status, record.submittedBy]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalized));
};

const employeeDisplayName = (record: Pick<AttendanceRecord | CompletedLongLeave, 'employeeCode' | 'employeeName'>) =>
    record.employeeCode ? `${record.employeeCode} - ${record.employeeName}` : record.employeeName;

const submittedByLabel = (record: AttendanceRecord) => {
    if (!record.submittedBy) {
        return '-';
    }

    return record.submittedByRole === 'admin' ? `${record.submittedBy} (Admin)` : record.submittedBy;
};
const filteredRopeRecords = computed(() => props.attendanceRecords.rope_access.filter((record) => matchesSearch(record, ropeSearch.value)));
const filteredContractingRecords = computed(() => props.attendanceRecords.contracting.filter((record) => matchesSearch(record, contractingSearch.value)));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <section class="border-b pb-5">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold tracking-normal">Monthly Report</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ selectedMonthLabel }} attendance summary separated from daily dashboard records.</p>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-base font-medium">Monthly Summary</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ selectedMonthLabel }} summary for the selected employee type.</p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div v-for="summaryItem in monthlySummary" :key="summaryItem.type" class="rounded-md border p-4">
                            <div>
                                <h4 class="font-medium">{{ summaryItem.label }}</h4>
                                <p class="text-sm text-muted-foreground">Employees: {{ summaryItem.totalEmployees }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                                <div class="rounded-md border border-green-600/20 bg-green-600/10 p-3 text-green-700">
                                    <p class="text-xs">Present</p>
                                    <p class="mt-1 text-2xl font-semibold">{{ summaryItem.present }}</p>
                                </div>
                                <div class="rounded-md border border-red-600/20 bg-red-600/10 p-3 text-red-700">
                                    <p class="text-xs">Absent</p>
                                    <p class="mt-1 text-2xl font-semibold">{{ summaryItem.absent }}</p>
                                </div>
                                <div class="rounded-md border border-amber-600/20 bg-amber-600/10 p-3 text-amber-700">
                                    <p class="text-xs">Leave Records</p>
                                    <p class="mt-1 text-2xl font-semibold">{{ summaryItem.leave }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Attendance Dashboard</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Selected date: {{ selectedDateLabel }}</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-[minmax(160px,1fr)_minmax(190px,1fr)_auto]">
                    <input
                        v-model="filterDate"
                        type="date"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        @change="applyFilters"
                    />
                    <select
                        v-model="filterType"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        @change="applyFilters"
                    >
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" @click="applyFilters">Filter</button>
                </div>
            </div>

            <div v-if="completedLongLeaves.length" class="rounded-lg border border-amber-600/30 bg-amber-600/10 p-4 text-amber-900">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-3">
                        <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-700" />
                        <div>
                            <h2 class="text-base font-medium">Long leave completed</h2>
                            <p class="mt-1 text-sm text-amber-800">These employees completed leave longer than 3 days. Please review and update their employee status if needed.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="leave in completedLongLeaves" :key="leave.id" class="rounded-md border border-amber-600/20 bg-background/70 p-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-foreground">{{ employeeDisplayName(leave) }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ leave.employeeProfession }} - {{ leave.durationDays }} days</p>
                            </div>
                            <span class="shrink-0 rounded-full border border-amber-600/30 px-2 py-1 text-xs text-amber-700">Ended {{ leave.endDateLabel }}</span>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">{{ leave.startDateLabel }} to {{ leave.endDateLabel }}<template v-if="leave.reason"> - {{ leave.reason }}</template></p>
                    </div>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Present</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.presentToday }}</p>
                        </div>
                        <CalendarCheck class="size-6 text-green-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Absent</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.absentToday }}</p>
                        </div>
                        <ClipboardX class="size-6 text-red-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Leave</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.leaveToday }}</p>
                        </div>
                        <Plane class="size-6 text-amber-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Employees</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.totalEmployees }}</p>
                        </div>
                        <Users class="size-6 text-muted-foreground" />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <h2 class="text-base font-medium">Daily Summary</h2>
                    <p class="mt-1 text-sm text-muted-foreground">For {{ selectedDateLabel }}.</p>
                    <div class="mt-5 grid gap-3">
                        <div class="flex items-center justify-between rounded-md border p-3 text-sm">
                            <span class="text-muted-foreground">Marked</span>
                            <span class="font-semibold">{{ summary.presentToday + summary.absentToday + summary.leaveToday }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-md border p-3 text-sm">
                            <span class="text-muted-foreground">Not marked</span>
                            <span class="font-semibold">{{ Math.max(summary.totalEmployees - (summary.presentToday + summary.absentToday + summary.leaveToday), 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-md border p-3 text-sm">
                            <span class="text-muted-foreground">Active projects</span>
                            <span class="font-semibold">{{ projectAttendance.length }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div>
                        <h2 class="text-base font-medium">Project Attendance</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Present employees grouped by project for {{ selectedDateLabel }}.</p>
                    </div>

                    <div v-if="projectAttendance.length" class="mt-5 grid max-h-72 gap-4 overflow-y-auto pr-2">
                        <div v-for="project in projectAttendance" :key="project.id" class="grid gap-2">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ project.name }}</p>
                                    <p class="text-xs capitalize text-muted-foreground">{{ project.type }}</p>
                                </div>
                                <span class="shrink-0 font-semibold">{{ project.employeeCount }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${(project.employeeCount / maxProjectCount) * 100}%` }" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-4 flex min-h-44 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                        No present employees assigned to projects on this date.
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div v-if="showRopeAccess" class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-base font-medium">Rope Access Attendance</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ filteredRopeRecords.length }} of {{ attendanceRecords.rope_access.length }} records for {{ selectedDateLabel }}.</p>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="ropeSearch" type="search" class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm" placeholder="Search rope access" />
                        </div>
                    </div>
                    <div v-if="filteredRopeRecords.length" class="mt-4 overflow-hidden rounded-md border">
                        <div class="grid min-w-[760px] grid-cols-[1fr_0.75fr_0.6fr_0.55fr_0.8fr] border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span>Employee</span>
                            <span>Project / Reason</span>
                            <span>Status</span>
                            <span>Overtime</span>
                            <span>Submitted By</span>
                        </div>
                        <div class="max-h-96 overflow-auto">
                            <div v-for="record in filteredRopeRecords" :key="record.id" class="grid min-w-[760px] grid-cols-[1fr_0.75fr_0.6fr_0.55fr_0.8fr] items-center gap-3 border-b px-3 py-3 text-sm last:border-b-0">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ employeeDisplayName(record) }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ record.employeeProfession }}</p>
                                </div>
                                <span class="truncate text-muted-foreground">{{ record.leaveReason || record.projectName || '-' }}</span>
                                <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">{{ statusLabel(record.status) }}</span>
                                <span class="truncate text-muted-foreground">
                                    {{
                                        record.overtimeHours
                                            ? `${record.overtimeHours} hrs${record.overtimeProjectName && record.overtimeProjectName !== record.projectName ? ` - ${record.overtimeProjectName}` : ''}`
                                            : '-'
                                    }}
                                </span>
                                <span class="truncate text-muted-foreground">{{ submittedByLabel(record) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 flex min-h-40 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">No rope access records found.</div>
                </div>

                <div v-if="showContracting" class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-base font-medium">Contracting Attendance</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ filteredContractingRecords.length }} of {{ attendanceRecords.contracting.length }} records for {{ selectedDateLabel }}.</p>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="contractingSearch" type="search" class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm" placeholder="Search contracting" />
                        </div>
                    </div>
                    <div v-if="filteredContractingRecords.length" class="mt-4 overflow-hidden rounded-md border">
                        <div class="grid min-w-[760px] grid-cols-[1fr_0.75fr_0.6fr_0.55fr_0.8fr] border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span>Employee</span>
                            <span>Project / Reason</span>
                            <span>Status</span>
                            <span>Overtime</span>
                            <span>Submitted By</span>
                        </div>
                        <div class="max-h-96 overflow-auto">
                            <div v-for="record in filteredContractingRecords" :key="record.id" class="grid min-w-[760px] grid-cols-[1fr_0.75fr_0.6fr_0.55fr_0.8fr] items-center gap-3 border-b px-3 py-3 text-sm last:border-b-0">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ employeeDisplayName(record) }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ record.employeeProfession }}</p>
                                </div>
                                <span class="truncate text-muted-foreground">{{ record.leaveReason || record.projectName || '-' }}</span>
                                <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">{{ statusLabel(record.status) }}</span>
                                <span class="truncate text-muted-foreground">
                                    {{
                                        record.overtimeHours
                                            ? `${record.overtimeHours} hrs${record.overtimeProjectName && record.overtimeProjectName !== record.projectName ? ` - ${record.overtimeProjectName}` : ''}`
                                            : '-'
                                    }}
                                </span>
                                <span class="truncate text-muted-foreground">{{ submittedByLabel(record) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 flex min-h-40 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">No contracting records found.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
