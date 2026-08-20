<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CalendarCheck, ClipboardList, ClipboardX, Plane, Search, UserCog, Users } from 'lucide-vue-next';
import { matchesEmployeeSearch } from '@/lib/employee-search';
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
    attendanceFraction: number | null;
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

interface DutyPerson {
    id: number;
    employeeCode: string | null;
    employeeName: string | null;
    employeeProfession: string | null;
    projectName: string | null;
    status: string;
    overtimeHours: number | null;
}

interface DutyProject {
    name: string;
    employeeCount: number;
}

interface DutyPlan {
    id: number;
    date: string;
    dateLabel: string;
    status: string;
    submitted: boolean;
    createdBy: string | null;
    createdById: number | null;
    employeeCount: number;
    projectCount: number;
    projects: DutyProject[];
    people: DutyPerson[];
}

interface Planner {
    id: number;
    name: string;
}

interface ContractingDuty {
    plans: DutyPlan[];
    planners: Planner[];
    summary: {
        open: number;
        submitted: number;
        employees: number;
        planners: number;
    };
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
    contractingDuty: ContractingDuty;
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
const dutySearch = ref('');
const dutyStatusFilter = ref<'all' | 'open' | 'submitted'>('all');
const dutyPlannerFilter = ref('all');
/// Duties open on the date the dashboard is already showing, because that is
/// the day the admin came to review. Widening to the recent window stays one
/// click away, which is what the person search needs.
const dutyDateScope = ref<'date' | 'all'>('date');

/// A duty is worth showing when the searched person is on it. The same search
/// also accepts a project or a planner's name, because an admin scanning the
/// board asks both "where is Bilal?" and "what went to PR 264?".
const personFields = (person: DutyPerson) => [person.employeeName, person.employeeCode, person.employeeProfession, person.projectName];

const matchedPeople = (plan: DutyPlan) => {
    if (!dutySearch.value.trim()) {
        return [];
    }

    return plan.people.filter((person) => matchesEmployeeSearch(personFields(person), dutySearch.value));
};

const dutyPlanMatches = (plan: DutyPlan) => {
    const query = dutySearch.value.trim();

    if (!query) {
        return true;
    }

    return (
        matchedPeople(plan).length > 0 ||
        matchesEmployeeSearch([plan.createdBy, plan.dateLabel, ...plan.projects.map((project) => project.name)], query)
    );
};

const filteredDutyPlans = computed(() =>
    props.contractingDuty.plans
        .filter((plan) => dutyDateScope.value === 'all' || plan.date === props.selectedDate)
        .filter((plan) => dutyPlannerFilter.value === 'all' || String(plan.createdById) === dutyPlannerFilter.value)
        .filter((plan) => (dutyStatusFilter.value === 'all' ? true : dutyStatusFilter.value === 'submitted' ? plan.submitted : !plan.submitted))
        .filter(dutyPlanMatches),
);

const dutiesInScope = computed(() => props.contractingDuty.plans.filter((plan) => dutyDateScope.value === 'all' || plan.date === props.selectedDate).length);

const matchedPeopleTotal = computed(() => filteredDutyPlans.value.reduce((total, plan) => total + matchedPeople(plan).length, 0));

const dutyStatusOptions = [
    { value: 'all', label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'submitted', label: 'Submitted' },
] as const;

const personStatusClass = (status: string) => {
    if (status === 'present') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'absent') return 'border-red-600/30 bg-red-600/10 text-red-700';
    if (status === 'leave') return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
    return 'border-border bg-muted text-muted-foreground';
};

const personDisplayName = (person: DutyPerson) => (person.employeeCode ? `${person.employeeCode} - ${person.employeeName}` : person.employeeName);

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

            <section v-if="showContracting" class="border-b pb-5">
                <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold tracking-normal">Contracting Duty Plans</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Duties from every planner, newest first. Search a person to see which duties they are on.
                        </p>
                    </div>
                    <div class="grid w-full gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(160px,200px)] xl:w-[34rem]">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="dutySearch"
                                type="search"
                                class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm"
                                placeholder="Search person, project or planner"
                            />
                        </div>
                        <select v-model="dutyPlannerFilter" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="all">All planners</option>
                            <option v-for="planner in contractingDuty.planners" :key="planner.id" :value="String(planner.id)">{{ planner.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted-foreground">Open Duties</p>
                                <p class="mt-2 text-3xl font-semibold">{{ contractingDuty.summary.open }}</p>
                            </div>
                            <ClipboardList class="size-6 text-amber-600" />
                        </div>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted-foreground">Submitted Duties</p>
                                <p class="mt-2 text-3xl font-semibold">{{ contractingDuty.summary.submitted }}</p>
                            </div>
                            <CalendarCheck class="size-6 text-green-600" />
                        </div>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted-foreground">Employees Scheduled</p>
                                <p class="mt-2 text-3xl font-semibold">{{ contractingDuty.summary.employees }}</p>
                            </div>
                            <Users class="size-6 text-muted-foreground" />
                        </div>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted-foreground">Planners</p>
                                <p class="mt-2 text-3xl font-semibold">{{ contractingDuty.summary.planners }}</p>
                            </div>
                            <UserCog class="size-6 text-muted-foreground" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-md border p-1">
                        <button
                            v-for="option in dutyStatusOptions"
                            :key="option.value"
                            type="button"
                            class="rounded px-3 py-1.5 text-sm"
                            :class="dutyStatusFilter === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="dutyStatusFilter = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <div class="inline-flex items-center gap-1 rounded-md border p-1">
                        <input
                            v-model="filterDate"
                            type="date"
                            class="h-8 rounded bg-background px-2 text-sm"
                            :class="dutyDateScope === 'date' ? 'text-foreground' : 'text-muted-foreground'"
                            @click="dutyDateScope = 'date'"
                            @change="applyFilters"
                        />
                        <button
                            type="button"
                            class="rounded px-3 py-1.5 text-sm"
                            :class="dutyDateScope === 'all' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="dutyDateScope = 'all'"
                        >
                            All recent
                        </button>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Showing {{ filteredDutyPlans.length }} of {{ dutiesInScope }} duties<template v-if="dutySearch.trim()">
                            - {{ matchedPeopleTotal }} matching {{ matchedPeopleTotal === 1 ? 'person' : 'people' }}</template
                        >.
                    </p>
                    <button v-if="dutySearch" type="button" class="text-sm text-muted-foreground underline" @click="dutySearch = ''">Clear search</button>
                </div>

                <div v-if="filteredDutyPlans.length" class="mt-4 grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
                    <article
                        v-for="plan in filteredDutyPlans"
                        :key="plan.id"
                        class="flex flex-col rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ plan.dateLabel }}</p>
                                <p class="truncate text-xs text-muted-foreground">Created by {{ plan.createdBy ?? 'Unknown' }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full border px-2 py-1 text-xs font-medium"
                                :class="plan.submitted ? 'border-green-600/30 bg-green-600/10 text-green-700' : 'border-amber-600/30 bg-amber-600/10 text-amber-700'"
                            >
                                {{ plan.submitted ? 'Submitted' : 'Open' }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div class="group relative rounded-md border p-3" tabindex="0">
                                <p class="text-xs text-muted-foreground">Projects</p>
                                <p class="mt-1 font-semibold">{{ plan.projectCount }}</p>
                                <!-- The gap sits inside the popup as padding, not as a margin: a real
                                     gap drops the hover the moment the cursor leaves the tile. -->
                                <div
                                    v-if="plan.projects.length"
                                    class="invisible absolute left-0 top-full z-30 w-72 max-w-[calc(100vw-3rem)] pt-2 opacity-0 transition-all delay-200 duration-150 group-hover:visible group-hover:opacity-100 group-hover:delay-0 group-focus-within:visible group-focus-within:opacity-100 group-focus-within:delay-0"
                                >
                                    <div class="rounded-md border bg-card p-2 shadow-lg">
                                        <p class="px-2 pb-1 text-xs font-medium text-muted-foreground">Projects on {{ plan.dateLabel }}</p>
                                        <div class="max-h-56 overflow-auto">
                                            <div v-for="project in plan.projects" :key="project.name" class="flex items-center justify-between gap-3 rounded px-2 py-1">
                                                <span class="truncate">{{ project.name }}</span>
                                                <span class="shrink-0 text-xs text-muted-foreground">{{ project.employeeCount }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="group relative rounded-md border p-3" tabindex="0">
                                <p class="text-xs text-muted-foreground">Employees</p>
                                <p class="mt-1 font-semibold">{{ plan.employeeCount }}</p>
                                <div
                                    v-if="plan.people.length"
                                    class="invisible absolute right-0 top-full z-30 w-80 max-w-[calc(100vw-3rem)] pt-2 opacity-0 transition-all delay-200 duration-150 group-hover:visible group-hover:opacity-100 group-hover:delay-0 group-focus-within:visible group-focus-within:opacity-100 group-focus-within:delay-0"
                                >
                                    <div class="rounded-md border bg-card p-2 shadow-lg">
                                        <p class="px-2 pb-1 text-xs font-medium text-muted-foreground">Employees on {{ plan.dateLabel }}</p>
                                        <div class="max-h-56 overflow-auto">
                                            <div v-for="person in plan.people" :key="person.id" class="flex items-center justify-between gap-3 rounded px-2 py-1">
                                                <div class="min-w-0">
                                                    <p class="truncate">{{ personDisplayName(person) }}</p>
                                                    <p class="truncate text-xs text-muted-foreground">{{ person.projectName ?? 'No project' }}</p>
                                                </div>
                                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs capitalize" :class="personStatusClass(person.status)">
                                                    {{ person.status }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="matchedPeople(plan).length" class="mt-3 grid gap-2">
                            <div
                                v-for="person in matchedPeople(plan)"
                                :key="person.id"
                                class="flex items-center justify-between gap-3 rounded-md border bg-background/60 px-3 py-2 text-sm"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ personDisplayName(person) }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ person.projectName ?? 'No project' }}<template v-if="person.overtimeHours"> - {{ person.overtimeHours }} hrs OT</template>
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium capitalize" :class="personStatusClass(person.status)">
                                    {{ person.status }}
                                </span>
                            </div>
                        </div>

                        <p v-else-if="plan.projects.length" class="mt-3 truncate text-xs text-muted-foreground">
                            {{ plan.projects.map((project) => project.name).join(', ') }}
                        </p>

                        <a
                            :href="`/contracting-duty-plans/${plan.id}/edit`"
                            class="mt-3 inline-flex h-9 w-fit items-center rounded-md border px-3 text-sm font-medium hover:bg-muted"
                        >
                            Open duty
                        </a>
                    </article>
                </div>

                <div v-else class="mt-4 flex min-h-40 flex-col items-center justify-center gap-3 rounded-md border border-dashed text-sm text-muted-foreground">
                    <p v-if="dutySearch.trim()">No duty matches this search.</p>
                    <p v-else-if="dutyDateScope === 'date'">No contracting duty for {{ selectedDateLabel }}.</p>
                    <p v-else>No contracting duty plans yet.</p>
                    <button
                        v-if="dutyDateScope === 'date'"
                        type="button"
                        class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium text-foreground hover:bg-muted"
                        @click="dutyDateScope = 'all'"
                    >
                        Show all recent duties
                    </button>
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
                                <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">
                                    {{ statusLabel(record.status) }}<template v-if="record.status === 'present' && Number(record.attendanceFraction) === 0.5"> · Half Day</template>
                                </span>
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
                                <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">
                                    {{ statusLabel(record.status) }}<template v-if="record.status === 'present' && Number(record.attendanceFraction) === 0.5"> · Half Day</template>
                                </span>
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
