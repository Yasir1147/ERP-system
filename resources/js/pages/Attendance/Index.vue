<script setup lang="ts">
import SortableHeader from '@/components/SortableHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { BriefcaseBusiness, CalendarCheck, Clock3, ClipboardX, Pencil, Plane, Search, X } from 'lucide-vue-next';
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
    projectId: number | null;
    projectName: string | null;
    overtimeProjectId: number | null;
    overtimeProjectName: string | null;
    hasOvertime: boolean;
    status: string;
    dateRaw: string;
    date: string;
    reason: string | null;
    overtimeHours: number | null;
    submittedBy: string | null;
    submittedByRole: string | null;
}

interface Project {
    id: number;
    name: string;
    status: string;
    type: string;
    label: string;
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
    projects: Project[];
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
type SortKey = 'date' | 'employee' | 'type' | 'project' | 'status' | 'overtime' | 'submitted_by';
const sortKey = ref<SortKey>('date');
const sortDirection = ref<'asc' | 'desc'>('desc');
const editingRecord = ref<AttendanceRecord | null>(null);

const employeeOptions = computed(() => props.employees.filter((employee) => filterType.value === 'all' || employee.type === filterType.value));
const editEmployee = computed(() => props.employees.find((employee) => String(employee.id) === editForm.employee_id));
const editProjectOptions = computed(() => props.projects.filter((project) => !editEmployee.value || project.type === editEmployee.value.type));
const maxProjectDays = computed(() => Math.max(1, ...props.projectSummary.map((project) => project.days)));
const maxStatusCount = computed(() => Math.max(1, props.summary.present, props.summary.absent, props.summary.leave));

const filteredRecords = computed(() => {
    const query = search.value.trim().toLowerCase();

    const records = query
        ? props.records.filter((record) =>
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
          )
        : props.records;

    return [...records].sort((first, second) => {
        const valueFor = (record: AttendanceRecord) => {
            if (sortKey.value === 'date') return record.dateRaw;
            if (sortKey.value === 'employee') return record.employeeName;
            if (sortKey.value === 'type') return props.employeeTypes[record.employeeType];
            if (sortKey.value === 'project') return record.reason || record.projectName || '';
            if (sortKey.value === 'status') return record.status;
            if (sortKey.value === 'overtime') return record.overtimeHours || 0;
            return submittedByLabel(record);
        };

        const firstValue = valueFor(first);
        const secondValue = valueFor(second);
        const comparison =
            typeof firstValue === 'number' && typeof secondValue === 'number'
                ? firstValue - secondValue
                : String(firstValue).localeCompare(String(secondValue), undefined, { numeric: true, sensitivity: 'base' });

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });
});

const sortRecords = (key: string) => {
    const nextKey = key as SortKey;

    if (sortKey.value === nextKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextKey;
    sortDirection.value = nextKey === 'date' ? 'desc' : 'asc';
};

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

const actualAttendanceId = (record: AttendanceRecord) => {
    const match = record.id.match(/^attendance-(\d+)$/);

    return match ? match[1] : null;
};

const editForm = useForm({
    employee_id: '',
    attendance_date: '',
    status: '',
    project_id: '',
    has_overtime: false,
    overtime_project_id: '',
    overtime_hours: '',
    leave_reason: '',
});

const startEditing = (record: AttendanceRecord) => {
    const id = actualAttendanceId(record);

    if (!id) {
        return;
    }

    editingRecord.value = record;
    editForm.clearErrors();
    editForm.employee_id = String(record.employeeId);
    editForm.attendance_date = record.dateRaw;
    editForm.status = record.status;
    editForm.project_id = record.projectId ? String(record.projectId) : '';
    editForm.has_overtime = Boolean(record.hasOvertime || record.overtimeHours);
    editForm.overtime_project_id = record.overtimeProjectId ? String(record.overtimeProjectId) : '';
    editForm.overtime_hours = record.overtimeHours ? String(record.overtimeHours) : '';
    editForm.leave_reason = record.reason || '';
};

const closeEdit = () => {
    editingRecord.value = null;
    editForm.reset();
    editForm.clearErrors();
};

watch(
    () => editForm.status,
    (status) => {
        if (status !== 'present') {
            editForm.project_id = '';
            editForm.has_overtime = false;
            editForm.overtime_project_id = '';
            editForm.overtime_hours = '';
        }

        if (status !== 'leave') {
            editForm.leave_reason = '';
        }
    },
);

watch(
    () => editForm.has_overtime,
    (hasOvertime) => {
        if (!hasOvertime) {
            editForm.overtime_project_id = '';
            editForm.overtime_hours = '';
        }
    },
);

watch(
    () => editForm.employee_id,
    () => {
        if (!editProjectOptions.value.some((project) => String(project.id) === editForm.project_id)) {
            editForm.project_id = '';
        }

        if (!editProjectOptions.value.some((project) => String(project.id) === editForm.overtime_project_id)) {
            editForm.overtime_project_id = '';
        }
    },
);

const updateAttendance = () => {
    if (!editingRecord.value) {
        return;
    }

    const id = actualAttendanceId(editingRecord.value);

    if (!id) {
        return;
    }

    const query = new URLSearchParams({
        filter_type: filterType.value,
        filter_employee_id: filterEmployeeId.value,
        filter_start_date: startDate.value,
        filter_end_date: endDate.value,
    });

    editForm.put(`/attendance/${id}?${query.toString()}`, {
        preserveScroll: true,
        onSuccess: closeEdit,
    });
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
                    <div class="grid min-w-[1080px] grid-cols-[0.7fr_1fr_0.75fr_0.75fr_0.55fr_0.6fr_0.85fr_90px] border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                        <SortableHeader label="Date" column="date" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <SortableHeader label="Employee" column="employee" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <SortableHeader label="Type" column="type" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <SortableHeader label="Project / Reason" column="project" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <SortableHeader label="Status" column="status" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <SortableHeader label="Overtime" column="overtime" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <SortableHeader label="Submitted By" column="submitted_by" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortRecords" />
                        <span class="text-right">Action</span>
                    </div>
                    <div class="max-h-[520px] overflow-auto">
                        <div
                            v-for="record in filteredRecords"
                            :key="record.id"
                            class="grid min-w-[1080px] grid-cols-[0.7fr_1fr_0.75fr_0.75fr_0.55fr_0.6fr_0.85fr_90px] items-center gap-3 border-b px-3 py-3 text-sm last:border-b-0"
                        >
                            <span class="text-muted-foreground">{{ record.date }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ record.employeeName }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ record.employeeProfession }}</p>
                            </div>
                            <span class="truncate text-muted-foreground">{{ employeeTypes[record.employeeType] }}</span>
                            <span class="truncate text-muted-foreground">{{ record.reason || record.projectName || '-' }}</span>
                            <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">{{ statusLabel(record.status) }}</span>
                            <span class="truncate text-muted-foreground">
                                {{
                                    record.overtimeHours
                                        ? `${record.overtimeHours} hrs${record.overtimeProjectName && record.overtimeProjectName !== record.projectName ? ` - ${record.overtimeProjectName}` : ''}`
                                        : '-'
                                }}
                            </span>
                            <span class="truncate text-muted-foreground">{{ submittedByLabel(record) }}</span>
                            <span class="text-right">
                                <button
                                    v-if="actualAttendanceId(record)"
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded-md border hover:bg-accent"
                                    title="Edit attendance"
                                    @click="startEditing(record)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-4 flex min-h-56 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                    No attendance records match the selected filters.
                </div>
            </div>
        </div>

        <div v-if="editingRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-2xl rounded-lg border bg-background shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold">Edit Attendance</h2>
                        <p class="text-sm text-muted-foreground">{{ editingRecord.employeeName }} - {{ editingRecord.date }}</p>
                    </div>
                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-md border hover:bg-accent" @click="closeEdit">
                        <X class="size-4" />
                    </button>
                </div>

                <form class="grid gap-4 p-5" @submit.prevent="updateAttendance">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Employee</label>
                            <select v-model="editForm.employee_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                <option v-for="employee in props.employees" :key="employee.id" :value="String(employee.id)">
                                    {{ employee.label }}
                                </option>
                            </select>
                            <p v-if="editForm.errors.employee_id" class="text-sm text-red-600">{{ editForm.errors.employee_id }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Date</label>
                            <input v-model="editForm.attendance_date" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm" />
                            <p v-if="editForm.errors.attendance_date" class="text-sm text-red-600">{{ editForm.errors.attendance_date }}</p>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Status</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label
                                v-for="status in ['present', 'absent', 'leave']"
                                :key="status"
                                class="flex h-10 cursor-pointer items-center justify-center rounded-md border text-sm font-medium capitalize"
                                :class="editForm.status === status ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background'"
                            >
                                <input v-model="editForm.status" type="radio" name="edit-status" :value="status" class="sr-only" />
                                {{ status }}
                            </label>
                        </div>
                        <p v-if="editForm.errors.status" class="text-sm text-red-600">{{ editForm.errors.status }}</p>
                    </div>

                    <template v-if="editForm.status === 'present'">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Project</label>
                                <select v-model="editForm.project_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                    <option value="">Select project</option>
                                    <option v-for="project in editProjectOptions" :key="project.id" :value="String(project.id)">
                                        {{ project.label }}
                                    </option>
                                </select>
                                <p v-if="editForm.errors.project_id" class="text-sm text-red-600">{{ editForm.errors.project_id }}</p>
                            </div>

                            <label class="mt-7 flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium">
                                <input v-model="editForm.has_overtime" type="checkbox" class="size-4" />
                                Overtime applied
                            </label>
                        </div>

                        <div v-if="editForm.has_overtime" class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Overtime Project</label>
                                <select v-model="editForm.overtime_project_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                    <option value="">Same as main project</option>
                                    <option v-for="project in editProjectOptions" :key="project.id" :value="String(project.id)">
                                        {{ project.label }}
                                    </option>
                                </select>
                                <p v-if="editForm.errors.overtime_project_id" class="text-sm text-red-600">{{ editForm.errors.overtime_project_id }}</p>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Overtime Hours</label>
                                <select v-model="editForm.overtime_hours" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                    <option value="">Select hours</option>
                                    <option v-for="hour in 10" :key="hour" :value="String(hour)">{{ hour }}</option>
                                </select>
                                <p v-if="editForm.errors.overtime_hours" class="text-sm text-red-600">{{ editForm.errors.overtime_hours }}</p>
                            </div>
                        </div>
                    </template>

                    <div v-if="editForm.status === 'leave'" class="grid gap-2">
                        <label class="text-sm font-medium">Leave Reason</label>
                        <textarea v-model="editForm.leave_reason" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="Leave reason" />
                        <p v-if="editForm.errors.leave_reason" class="text-sm text-red-600">{{ editForm.errors.leave_reason }}</p>
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button type="button" class="h-10 rounded-md border px-4 text-sm font-medium hover:bg-accent" @click="closeEdit">Cancel</button>
                        <button type="submit" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" :disabled="editForm.processing">
                            Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
