<script setup lang="ts">
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Banknote, BookOpen, BriefcaseBusiness, Clock3, LoaderCircle, Search, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
    status: string;
    type: string;
    label: string;
}

interface OverviewRow {
    id: number;
    name: string;
    status: string;
    type: string;
    typeLabel: string;
    firstWorkDate: string | null;
    lastWorkDate: string | null;
    daysSinceStart: number;
    workedDays: number;
    labourCount: number;
    labourEntries: number;
    overtimeHours: number;
    basicCost: number;
    overtimeCost: number;
    totalCost: number;
    missingPayrollSettings: string[];
}

interface TypeOption {
    value: string;
    label: string;
}

interface ProjectHistoryEmployeeSummary {
    employeeId: number | null;
    employeeName: string;
    profession: string;
    entries: number;
    workedDays: number;
    overtimeHours: number;
    basicCost: number;
    overtimeCost: number;
    totalCost: number;
    submittedBy: string;
}

interface ProjectHistoryTotals {
    uniqueEmployees: number;
    entries: number;
    workedDays: number;
    overtimeHours: number;
    basicCost: number;
    overtimeCost: number;
    totalCost: number;
}

const props = defineProps<{
    projects: ProjectOption[];
    overviewRows: OverviewRow[];
    summary: {
        projectCount: number;
        activeProjects: number;
        labourCount: number;
        workedDays: number;
        overtimeHours: number;
        totalCost: number;
    };
    filters: {
        type: string;
        projectId: string;
    };
    typeOptions: TypeOption[];
    projectTypes: Record<string, string>;
    statuses: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects/overview',
    },
    {
        title: 'Overview',
        href: '/projects/overview',
    },
];

const filterType = ref(props.filters.type);
const filterProjectId = ref(props.filters.projectId);
const search = ref('');
const historyOpen = ref(false);
const historyLoading = ref(false);
const historyError = ref('');
const historyProject = ref<OverviewRow | null>(null);
const historyFrom = ref('');
const historyTo = ref('');
const historyEmployeeSummary = ref<ProjectHistoryEmployeeSummary[]>([]);
const historyTotals = ref<ProjectHistoryTotals>({
    uniqueEmployees: 0,
    entries: 0,
    workedDays: 0,
    overtimeHours: 0,
    basicCost: 0,
    overtimeCost: 0,
    totalCost: 0,
});

const filteredProjectOptions = computed(() => props.projects.filter((project) => filterType.value === 'all' || project.type === filterType.value));

watch(filterType, () => {
    if (!filteredProjectOptions.value.some((project) => String(project.id) === filterProjectId.value)) {
        filterProjectId.value = 'all';
    }
});

const statusLabels = computed(() =>
    props.statuses.reduce<Record<string, string>>((labels, status) => {
        labels[status] = status
            .split('-')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' ');

        return labels;
    }, {}),
);

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.overviewRows;
    }

    return props.overviewRows.filter((row) =>
        [row.name, row.typeLabel, row.status, statusLabels.value[row.status], row.firstWorkDate, row.lastWorkDate]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)),
    );
});

const money = (value: number) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const applyFilters = () => {
    router.get(
        '/projects/overview',
        {
            type: filterType.value,
            project_id: filterProjectId.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const statusClass = (status: string) => {
    if (status === 'ongoing') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'completed') return 'border-sky-600/30 bg-sky-600/10 text-sky-700';
    return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
};

const loadProjectHistory = async (row?: OverviewRow) => {
    if (row) {
        historyProject.value = row;
        historyFrom.value = '';
        historyTo.value = '';
        historyOpen.value = true;
    }

    if (!historyProject.value) {
        return;
    }

    historyLoading.value = true;
    historyError.value = '';

    try {
        const params = new URLSearchParams();

        if (historyFrom.value) {
            params.set('from', historyFrom.value);
        }

        if (historyTo.value) {
            params.set('to', historyTo.value);
        }

        const query = params.toString();
        const response = await fetch(`/projects/${historyProject.value.id}/employee-history${query ? `?${query}` : ''}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Project employee history could not be loaded.');
        }

        const payload = await response.json();
        historyEmployeeSummary.value = payload.employeeSummary || [];
        historyTotals.value = payload.totals || historyTotals.value;
    } catch (error) {
        historyError.value = error instanceof Error ? error.message : 'Project employee history could not be loaded.';
    } finally {
        historyLoading.value = false;
    }
};

const closeProjectHistory = () => {
    historyOpen.value = false;
    historyProject.value = null;
    historyEmployeeSummary.value = [];
    historyError.value = '';
};
</script>

<template>
    <Head title="Projects Overview" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Projects Overview</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Project labour, overtime, and salary cost from attendance records.</p>
                </div>
                <div class="grid gap-2 lg:grid-cols-[220px_260px_auto]">
                    <select
                        v-model="filterType"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select
                        v-model="filterProjectId"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="all">All Projects</option>
                        <option v-for="project in filteredProjectOptions" :key="project.id" :value="String(project.id)">
                            {{ project.label }}
                        </option>
                    </select>
                    <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" @click="applyFilters">
                        Filter
                    </button>
                </div>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-5">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Projects</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.projectCount }}</p>
                        </div>
                        <BriefcaseBusiness class="size-6 text-muted-foreground" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Active Projects</p>
                    <p class="mt-2 text-3xl font-semibold">{{ summary.activeProjects }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Labour Count</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.labourCount }}</p>
                        </div>
                        <Users class="size-6 text-muted-foreground" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">OT Hours</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.overtimeHours }}</p>
                        </div>
                        <Clock3 class="size-6 text-sky-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Labour Cost</p>
                            <p class="mt-2 text-2xl font-semibold">{{ money(summary.totalCost) }}</p>
                        </div>
                        <Banknote class="size-6 text-green-700" />
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Project Cost Report</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredRows.length }} of {{ overviewRows.length }} projects</p>
                    </div>
                    <div class="relative w-full md:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search project, category, or status" />
                    </div>
                </div>

                <div v-if="overviewRows.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No projects found for the selected filters.
                </div>

                <div v-else-if="filteredRows.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No project records match your search.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1600px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[210px] px-4 py-3 font-medium">Project</th>
                                <th class="w-[150px] px-4 py-3 font-medium">Category</th>
                                <th class="w-[110px] px-4 py-3 font-medium">Status</th>
                                <th class="w-[120px] px-4 py-3 font-medium">First Work</th>
                                <th class="w-[120px] px-4 py-3 font-medium">Last Work</th>
                                <th class="w-[90px] px-4 py-3 text-right font-medium">Days</th>
                                <th class="w-[100px] px-4 py-3 text-right font-medium">Worked</th>
                                <th class="w-[100px] px-4 py-3 text-right font-medium">Labour</th>
                                <th class="w-[110px] px-4 py-3 text-right font-medium">Entries</th>
                                <th class="w-[100px] px-4 py-3 text-right font-medium">OT Hrs</th>
                                <th class="w-[130px] px-4 py-3 text-right font-medium">Basic Cost</th>
                                <th class="w-[130px] px-4 py-3 text-right font-medium">OT Cost</th>
                                <th class="w-[140px] px-4 py-3 text-right font-medium">Total Cost</th>
                                <th class="w-[90px] px-4 py-3 text-center font-medium">History</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredRows" :key="row.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <p class="truncate font-medium">{{ row.name }}</p>
                                    <p v-if="row.missingPayrollSettings.length" class="mt-1 truncate text-xs text-amber-700">
                                        Missing salary: {{ row.missingPayrollSettings.join(', ') }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.typeLabel }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">
                                        {{ statusLabels[row.status] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.firstWorkDate || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.lastWorkDate || '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ row.daysSinceStart }}</td>
                                <td class="px-4 py-3 text-right">{{ row.workedDays }}</td>
                                <td class="px-4 py-3 text-right">{{ row.labourCount }}</td>
                                <td class="px-4 py-3 text-right">{{ row.labourEntries }}</td>
                                <td class="px-4 py-3 text-right">{{ row.overtimeHours }}</td>
                                <td class="px-4 py-3 text-right">{{ money(row.basicCost) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(row.overtimeCost) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ money(row.totalCost) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md border text-sm hover:bg-accent"
                                        title="View employee history"
                                        @click="loadProjectHistory(row)"
                                    >
                                        <BookOpen class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="historyOpen" class="fixed inset-0 z-50 bg-black/50 p-2 sm:p-4">
                <div class="mx-auto flex max-h-[94vh] w-[calc(100vw-1rem)] max-w-none flex-col overflow-hidden rounded-lg border bg-background shadow-xl sm:w-[calc(100vw-2rem)]">
                    <div class="flex flex-col gap-3 border-b p-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Project Employee History</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ historyProject?.name }}
                                <template v-if="historyProject"> - {{ historyProject.typeLabel }} - {{ statusLabels[historyProject.status] }}</template>
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[160px_160px_auto_auto]">
                            <input
                                v-model="historyFrom"
                                type="date"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                aria-label="From date"
                            />
                            <input
                                v-model="historyTo"
                                type="date"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                aria-label="To date"
                            />
                            <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-60" :disabled="historyLoading" @click="loadProjectHistory()">
                                Filter
                            </button>
                            <button type="button" class="inline-flex h-10 items-center justify-center rounded-md border px-3" @click="closeProjectHistory">
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4 overflow-auto p-4">
                        <div v-if="historyError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            {{ historyError }}
                        </div>

                        <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-7">
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Employees</p>
                                <p class="mt-1 text-xl font-semibold">{{ historyTotals.uniqueEmployees }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Entries</p>
                                <p class="mt-1 text-xl font-semibold">{{ historyTotals.entries }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Worked Days</p>
                                <p class="mt-1 text-xl font-semibold">{{ historyTotals.workedDays }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">OT Hours</p>
                                <p class="mt-1 text-xl font-semibold">{{ historyTotals.overtimeHours }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Basic Cost</p>
                                <p class="mt-1 text-xl font-semibold">{{ money(historyTotals.basicCost) }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">OT Cost</p>
                                <p class="mt-1 text-xl font-semibold">{{ money(historyTotals.overtimeCost) }}</p>
                            </div>
                            <div class="rounded-md border p-3">
                                <p class="text-xs text-muted-foreground">Total Cost</p>
                                <p class="mt-1 text-xl font-semibold">{{ money(historyTotals.totalCost) }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border">
                            <div class="border-b p-3">
                                <h3 class="font-medium">Employee Summary</h3>
                            </div>
                            <div v-if="historyLoading" class="flex min-h-28 items-center justify-center gap-2 text-sm text-muted-foreground">
                                <LoaderCircle class="size-4 animate-spin" />
                                Loading project history...
                            </div>
                            <div v-else-if="historyEmployeeSummary.length === 0" class="flex min-h-28 items-center justify-center text-sm text-muted-foreground">
                                No employee summary available.
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full min-w-[1080px] table-fixed text-sm">
                                    <thead class="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th class="w-[220px] px-3 py-2 font-medium">Employee</th>
                                            <th class="w-[180px] px-3 py-2 font-medium">Profession</th>
                                            <th class="w-[90px] px-3 py-2 text-right font-medium">Entries</th>
                                            <th class="w-[110px] px-3 py-2 text-right font-medium">Worked Days</th>
                                            <th class="w-[90px] px-3 py-2 text-right font-medium">OT Hrs</th>
                                            <th class="w-[120px] px-3 py-2 text-right font-medium">Basic Cost</th>
                                            <th class="w-[120px] px-3 py-2 text-right font-medium">OT Cost</th>
                                            <th class="w-[120px] px-3 py-2 text-right font-medium">Total Cost</th>
                                            <th class="w-[180px] px-3 py-2 font-medium">Submitted By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="summaryRow in historyEmployeeSummary" :key="summaryRow.employeeId || summaryRow.employeeName" class="border-b last:border-b-0">
                                            <td class="px-3 py-3 font-medium">{{ summaryRow.employeeName }}</td>
                                            <td class="px-3 py-3 text-muted-foreground">{{ summaryRow.profession }}</td>
                                            <td class="px-3 py-3 text-right">{{ summaryRow.entries }}</td>
                                            <td class="px-3 py-3 text-right">{{ summaryRow.workedDays }}</td>
                                            <td class="px-3 py-3 text-right">{{ summaryRow.overtimeHours }}</td>
                                            <td class="px-3 py-3 text-right">{{ money(summaryRow.basicCost) }}</td>
                                            <td class="px-3 py-3 text-right">{{ money(summaryRow.overtimeCost) }}</td>
                                            <td class="px-3 py-3 text-right font-semibold">{{ money(summaryRow.totalCost) }}</td>
                                            <td class="px-3 py-3 text-muted-foreground">{{ summaryRow.submittedBy }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
