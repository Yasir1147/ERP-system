<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, CheckCircle2, ChevronDown, Clipboard, ClipboardList, Clock3, Save, Search, Trash2, Users, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

interface Employee {
    id: number;
    code: string | null;
    name: string;
    profession: string | null;
    status: string;
    onLeave: boolean;
    leaveReason: string | null;
}

interface Project {
    id: number;
    name: string;
    status: string;
}

interface Assignment {
    id: number;
    employeeId: number;
    employeeCode: string | null;
    employeeName: string;
    profession: string | null;
    projectId: number;
    projectName: string;
    status: string;
    hasOvertime: boolean;
    overtimeHours: number | null;
    overtimeProjectId: number | null;
    overtimeProjectName: string | null;
    note: string | null;
    attendanceRecordId: number | null;
}

interface DutyPlan {
    id: number;
    date: string;
    status: string;
    createdBy: string | null;
    publishedBy: string | null;
    publishedAt: string | null;
    finalizedBy: string | null;
    finalizedAt: string | null;
    assignments: Assignment[];
}

interface RecentPlan {
    id: number;
    date: string;
    status: string;
    assignmentCount: number;
}

interface DutyGroup {
    projectId: number;
    projectName: string;
    assignments: Assignment[];
}

const props = defineProps<{
    selectedDate: string;
    dateMin: string | null;
    dateMax: string;
    plan: DutyPlan | null;
    pendingOlderPlan: { id: number; date: string; status: string } | null;
    employees: Employee[];
    projects: Project[];
    recentPlans: RecentPlan[];
}>();

const page = usePage();
const selectedDate = ref(props.selectedDate);
const employeeSearch = ref('');
const employeeOpen = ref(false);
const employeeDropdownRef = ref<HTMLElement | null>(null);
const openDutyGroups = reactive<Record<string, boolean>>({});
const dutiesCopied = ref(false);
const assignmentForms = reactive<Record<number, {
    project_id: string;
    status: string;
    has_overtime: boolean;
    overtime_hours: string;
    overtime_project_id: string;
    note: string;
}>>({});

const addForm = useForm({
    duty_date: props.selectedDate,
    project_id: '',
    employee_ids: [] as string[],
});

const statusOptions = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'leave', label: 'Leave' },
    { value: 'removed', label: 'Removed' },
];

const isFinalized = computed(() => props.plan?.status === 'finalized');
const selectedEmployeeCount = computed(() => addForm.employee_ids.length);
const dutyGroups = computed<DutyGroup[]>(() => {
    const groups = new Map<number, DutyGroup>();

    for (const assignment of props.plan?.assignments ?? []) {
        const group = groups.get(assignment.projectId) ?? {
            projectId: assignment.projectId,
            projectName: assignment.projectName,
            assignments: [],
        };

        group.assignments.push(assignment);
        groups.set(assignment.projectId, group);
    }

    return Array.from(groups.values()).sort((first, second) => first.projectName.localeCompare(second.projectName));
});
const availableEmployees = computed(() => {
    const assignedIds = new Set(props.plan?.assignments.map((assignment) => assignment.employeeId) ?? []);
    const query = employeeSearch.value.trim().toLowerCase();

    return props.employees.filter((employee) => {
        if (assignedIds.has(employee.id)) return false;
        if (!query) return true;

        return [employee.code, employee.name, employee.profession]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query));
    });
});

const globalPlanError = computed(() => (page.props.errors as Record<string, string>).plan);

const initializeAssignmentForms = () => {
    Object.keys(assignmentForms).forEach((key) => delete assignmentForms[Number(key)]);

    props.plan?.assignments.forEach((assignment) => {
        assignmentForms[assignment.id] = {
            project_id: String(assignment.projectId),
            status: assignment.status === 'planned' ? 'present' : assignment.status,
            has_overtime: assignment.hasOvertime,
            overtime_hours: assignment.overtimeHours ? String(assignment.overtimeHours) : '',
            overtime_project_id: assignment.overtimeProjectId ? String(assignment.overtimeProjectId) : '',
            note: assignment.note || '',
        };
    });
};

watch(() => props.plan, initializeAssignmentForms, { immediate: true, deep: true });

watch(dutyGroups, (groups) => {
    const activeKeys = new Set(groups.map((group) => String(group.projectId)));

    Object.keys(openDutyGroups).forEach((key) => {
        if (!activeKeys.has(key)) delete openDutyGroups[key];
    });

    groups.forEach((group, index) => {
        const key = String(group.projectId);
        if (openDutyGroups[key] === undefined) openDutyGroups[key] = index === 0;
    });
}, { immediate: true });

const openDate = () => {
    router.get('/contracting-duty-plans', { date: selectedDate.value }, { preserveState: false });
};

const formatDate = (date: string) => {
    const [year, month, day] = date.split('-');
    return `${day}/${month}/${year}`;
};

const statusLabel = (status: string) => {
    if (status === 'draft' || status === 'published') return 'Open';
    if (status === 'finalized') return 'Submitted';
    if (status === 'planned') return 'Present';
    return statusOptions.find((option) => option.value === status)?.label || status;
};

const statusClass = (status: string) => {
    if (status === 'finalized' || status === 'present') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'published') return 'border-blue-600/30 bg-blue-600/10 text-blue-700';
    if (status === 'absent') return 'border-red-600/30 bg-red-600/10 text-red-700';
    if (status === 'leave') return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
    if (status === 'removed') return 'border-slate-500/30 bg-slate-500/10 text-slate-600';
    return 'border-border bg-muted text-muted-foreground';
};

const toggleDutyGroup = (projectId: number) => {
    const key = String(projectId);
    openDutyGroups[key] = !openDutyGroups[key];
};

const copyDuties = async () => {
    const text = dutyGroups.value
        .map((group) => [
            group.projectName,
            ...group.assignments.map((assignment) => assignment.employeeCode
                ? `${assignment.employeeCode} - ${assignment.employeeName}`
                : assignment.employeeName),
        ].join('\n'))
        .join('\n============\n');

    if (!text) return;

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    }

    dutiesCopied.value = true;
    window.setTimeout(() => {
        dutiesCopied.value = false;
    }, 1800);
};

const toggleEmployee = (employee: Employee) => {
    if (employee.onLeave) return;
    const id = String(employee.id);
    addForm.employee_ids = addForm.employee_ids.includes(id)
        ? addForm.employee_ids.filter((employeeId) => employeeId !== id)
        : [...addForm.employee_ids, id];
};

const closeEmployeeDropdown = () => {
    employeeOpen.value = false;
    employeeSearch.value = '';
};

const handleOutsideClick = (event: MouseEvent) => {
    if (employeeDropdownRef.value && !employeeDropdownRef.value.contains(event.target as Node)) {
        closeEmployeeDropdown();
    }
};

onMounted(() => document.addEventListener('click', handleOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', handleOutsideClick));

const addAssignments = () => {
    addForm.duty_date = props.selectedDate;
    addForm.post('/contracting-duty-plans/assignments', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.employee_ids = [];
            employeeSearch.value = '';
            employeeOpen.value = false;
        },
    });
};

const updateAssignment = (assignment: Assignment) => {
    const values = assignmentForms[assignment.id];
    if (!values) return;

    router.put(`/contracting-duty-assignments/${assignment.id}`, values, { preserveScroll: true });
};

const removeAssignment = (assignment: Assignment) => {
    if (!window.confirm(`Remove ${assignment.employeeName} from this duty plan?`)) return;
    router.delete(`/contracting-duty-assignments/${assignment.id}`, { preserveScroll: true });
};

const deleteRecentPlan = (recent: RecentPlan) => {
    if (recent.status === 'finalized' || !window.confirm(`Delete the duty plan for ${formatDate(recent.date)}? This will remove all of its assignments.`)) return;
    router.delete(`/contracting-duty-plans/${recent.id}`, { preserveScroll: true });
};

const finalizePlan = () => {
    if (!props.plan || !window.confirm('Submit this duty plan as attendance? Please confirm employee status and overtime before continuing.')) return;
    router.post(`/contracting-duty-plans/${props.plan.id}/finalize`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Contracting Duty Plans" />

    <main class="min-h-svh bg-background px-4 py-5 text-foreground sm:px-6">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-5">
            <header class="grid gap-4 rounded-lg border bg-card p-4 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center sm:p-5">
                <div class="flex items-center gap-4">
                    <AppLogoIcon class="size-16 shrink-0 sm:size-20" />
                    <div>
                        <p class="text-xs font-semibold uppercase text-primary">Contracting workforce</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-normal">Duty Planning</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Plan tomorrow's projects, review changes, then submit final attendance.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link href="/mark-attendance/contracting"><ArrowLeft class="size-4" />Direct Attendance</Link>
                    </Button>
                </div>
            </header>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="globalPlanError" class="rounded-md border border-red-600/30 bg-red-600/10 px-4 py-3 text-sm text-red-700">
                {{ globalPlanError }}
            </div>
            <div v-if="pendingOlderPlan" class="rounded-md border border-amber-600/30 bg-amber-600/10 px-4 py-3 text-sm text-amber-800">
                The {{ formatDate(pendingOlderPlan.date) }} duty plan is still {{ pendingOlderPlan.status }}. You can prepare this draft, but publish it only after completing the older plan.
            </div>

            <section class="grid gap-4 rounded-lg border bg-card p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-5">
                <div class="grid gap-2">
                    <Label for="duty-date">Duty Date</Label>
                    <Input id="duty-date" v-model="selectedDate" type="date" :min="dateMin || undefined" :max="dateMax" />
                    <p class="text-xs text-muted-foreground">Select the date employees are expected to work, not the date this plan is created.</p>
                </div>
                <Button type="button" class="h-10" @click="openDate"><CalendarDays class="size-4" />Open Duty</Button>
            </section>

            <section v-if="!isFinalized" class="rounded-lg border bg-card p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-medium">Add Employees</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Employees are Present by default. Change only Absence, Leave, Overtime, or removal when needed.</p>
                    </div>
                </div>

                <form class="grid gap-4 lg:grid-cols-[minmax(220px,0.8fr)_minmax(320px,1.2fr)_auto] lg:items-end" @submit.prevent="addAssignments">
                    <div class="grid gap-2">
                        <Label for="main-project">Project</Label>
                        <select id="main-project" v-model="addForm.project_id" class="h-11 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Select project</option>
                            <option v-for="project in projects" :key="project.id" :value="String(project.id)">{{ project.name }} - {{ project.status }}</option>
                        </select>
                        <InputError :message="addForm.errors.project_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Employees</Label>
                        <div ref="employeeDropdownRef" class="relative">
                            <button type="button" class="flex h-11 w-full items-center justify-between rounded-md border border-input bg-background px-3 text-left text-sm" @click="employeeOpen = !employeeOpen">
                                <span>{{ selectedEmployeeCount ? `${selectedEmployeeCount} employees selected` : 'Select employees' }}</span>
                                <ChevronDown class="size-4 text-muted-foreground" />
                            </button>
                            <div v-if="employeeOpen" class="absolute z-30 mt-2 w-full rounded-md border bg-popover p-2 shadow-lg">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input v-model="employeeSearch" type="search" class="pl-9" placeholder="Search code, name, or profession" />
                                </div>
                                <div v-if="selectedEmployeeCount" class="mt-2 flex items-center justify-between rounded-md bg-muted px-3 py-2 text-xs">
                                    <span>{{ selectedEmployeeCount }} selected</span>
                                    <button type="button" class="flex items-center gap-1 font-medium text-primary" @click="addForm.employee_ids = []"><X class="size-3" />Clear</button>
                                </div>
                                <div class="mt-2 max-h-64 overflow-y-auto">
                                    <button
                                        v-for="employee in availableEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-start gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="employee.onLeave"
                                        @click="toggleEmployee(employee)"
                                    >
                                        <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border" :class="addForm.employee_ids.includes(String(employee.id)) ? 'border-primary bg-primary text-primary-foreground' : 'border-input'">
                                            <CheckCircle2 v-if="addForm.employee_ids.includes(String(employee.id))" class="size-3" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ employee.code ? `${employee.code} - ` : '' }}{{ employee.name }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ employee.profession || '-' }}<template v-if="employee.onLeave"> - On leave</template></span>
                                        </span>
                                    </button>
                                    <p v-if="!availableEmployees.length" class="px-3 py-6 text-center text-sm text-muted-foreground">No employees found.</p>
                                </div>
                            </div>
                        </div>
                        <InputError :message="addForm.errors.employee_ids" />
                    </div>

                    <Button type="submit" class="h-11" :disabled="addForm.processing"><Users class="size-4" />Add to Duty</Button>
                </form>
            </section>

            <section class="rounded-lg border bg-card shadow-sm">
                <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div>
                        <h2 class="font-medium">Duty Plans - {{ formatDate(selectedDate) }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ dutyGroups.length }} duties, {{ plan?.assignments.length || 0 }} employees.</p>
                    </div>
                    <div v-if="plan && !isFinalized" class="flex flex-wrap gap-2">
                        <Button v-if="dutyGroups.length" type="button" variant="outline" @click="copyDuties"><Clipboard class="size-4" />{{ dutiesCopied ? 'Copied' : 'Copy Duties' }}</Button>
                        <Button v-if="dutyGroups.length" type="button" @click="finalizePlan"><ClipboardList class="size-4" />Submit Attendance</Button>
                    </div>
                </div>

                <div v-if="dutyGroups.length" class="grid gap-3 p-3 sm:p-4">
                    <article v-for="group in dutyGroups" :key="group.projectId" class="overflow-hidden rounded-md border bg-background">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition-colors hover:bg-accent"
                            :aria-expanded="openDutyGroups[String(group.projectId)]"
                            @click="toggleDutyGroup(group.projectId)"
                        >
                            <span class="min-w-0">
                                <span class="block font-medium">Duty Plan - {{ group.projectName }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">{{ group.assignments.length }} employees</span>
                            </span>
                            <ChevronDown class="size-5 shrink-0 text-muted-foreground transition-transform" :class="openDutyGroups[String(group.projectId)] ? 'rotate-180' : ''" />
                        </button>

                        <div v-if="openDutyGroups[String(group.projectId)]" class="grid gap-3 border-t bg-muted/10 p-3 sm:p-4">
                    <div v-for="assignment in group.assignments" :key="assignment.id" class="rounded-md border bg-card p-3 sm:p-4">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,0.75fr)_minmax(0,1.25fr)_minmax(0,1fr)_auto] lg:items-end lg:gap-2">
                            <div class="min-w-0 lg:self-center">
                                <p class="truncate font-medium">{{ assignment.employeeCode ? `${assignment.employeeCode} - ` : '' }}{{ assignment.employeeName }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ assignment.profession || '-' }}</p>
                                <span v-if="isFinalized" class="mt-2 inline-flex rounded-full border px-2 py-1 text-xs" :class="statusClass(assignment.status)">{{ statusLabel(assignment.status) }}</span>
                            </div>

                            <div class="grid gap-1.5">
                                <Label :for="`project-${assignment.id}`">Project</Label>
                                <select :id="`project-${assignment.id}`" v-model="assignmentForms[assignment.id].project_id" :disabled="isFinalized" class="h-10 rounded-md border border-input bg-background px-2 text-sm disabled:opacity-60">
                                    <option v-for="project in projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                                </select>
                            </div>

                            <div class="grid gap-1.5">
                                <Label :for="`status-${assignment.id}`">Final Status</Label>
                                <select :id="`status-${assignment.id}`" v-model="assignmentForms[assignment.id].status" :disabled="isFinalized" class="h-10 rounded-md border border-input bg-background px-2 text-sm disabled:opacity-60">
                                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>

                            <div class="grid gap-2 rounded-md border bg-muted/30 p-2.5">
                                <label class="flex items-center gap-2 text-sm font-medium" :class="assignmentForms[assignment.id].status !== 'present' ? 'opacity-50' : ''">
                                    <input v-model="assignmentForms[assignment.id].has_overtime" type="checkbox" :disabled="isFinalized || assignmentForms[assignment.id].status !== 'present'" />
                                    Overtime applied
                                </label>
                                <div v-if="assignmentForms[assignment.id].has_overtime && assignmentForms[assignment.id].status === 'present'" class="grid grid-cols-[90px_1fr] gap-2 lg:grid-cols-2">
                                    <select v-model="assignmentForms[assignment.id].overtime_hours" :disabled="isFinalized" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                        <option value="">Hours</option>
                                        <option v-for="hour in 10" :key="hour" :value="String(hour)">{{ hour }}h</option>
                                    </select>
                                    <select v-model="assignmentForms[assignment.id].overtime_project_id" :disabled="isFinalized" class="h-9 min-w-0 rounded-md border border-input bg-background px-2 text-sm">
                                        <option value="">Same project</option>
                                        <option v-for="project in projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label :for="`note-${assignment.id}`">Note</Label>
                                <Input :id="`note-${assignment.id}`" v-model="assignmentForms[assignment.id].note" :disabled="isFinalized" placeholder="Optional note" />
                            </div>

                            <div v-if="!isFinalized" class="flex gap-2">
                                <Button type="button" size="icon" title="Save assignment" @click="updateAssignment(assignment)"><Save class="size-4" /></Button>
                                <Button type="button" size="icon" variant="destructive" title="Remove assignment" @click="removeAssignment(assignment)"><Trash2 class="size-4" /></Button>
                            </div>
                            <div v-else class="flex items-center gap-2 text-xs text-muted-foreground"><CheckCircle2 class="size-4 text-green-600" />Submitted</div>
                        </div>
                    </div>
                        </div>
                    </article>
                </div>

                <div v-else class="p-8 text-center text-sm text-muted-foreground">
                    <ClipboardList class="mx-auto mb-3 size-8" />No duty plan has been created for this date.
                </div>
            </section>

            <section v-if="recentPlans.length" class="rounded-lg border bg-card p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex items-center gap-2"><Clock3 class="size-4" /><h2 class="font-medium">Recent Duty Plans</h2></div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <div v-for="recent in recentPlans" :key="recent.id" class="flex items-center rounded-md border transition-colors hover:bg-accent">
                        <Link :href="`/contracting-duty-plans?date=${recent.date}`" class="min-w-0 flex-1 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium">{{ formatDate(recent.date) }}</span>
                                <span class="rounded-full border px-2 py-0.5 text-[11px]" :class="statusClass(recent.status)">{{ statusLabel(recent.status) }}</span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">{{ recent.assignmentCount }} employees</p>
                        </Link>
                        <Button
                            v-if="recent.status !== 'finalized'"
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="mr-2 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            title="Delete duty plan"
                            @click="deleteRecentPlan(recent)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
