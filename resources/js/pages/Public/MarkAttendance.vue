<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Project {
    id: number;
    name: string;
    status: string;
}

interface Employee {
    id: number;
    code: string;
    name: string;
    profession: string;
    type: string;
    status: string;
}

interface EmployeeLeave {
    employeeId: number;
    startDate: string;
    endDate: string;
    reason: string | null;
}

interface TodayRecord {
    id: number;
    employeeCode: string | null;
    employeeName: string;
    employeeProfession: string | null;
    status: string;
    projectName: string | null;
    overtimeProjectName: string | null;
    reason: string | null;
    overtimeHours: number | null;
}

const props = defineProps<{
    projects: Project[];
    employees: Employee[];
    employeeLeaves: EmployeeLeave[];
    employeeType: string;
    employeeTypeLabel: string;
    submitUrl: string;
    dutyPlanUrl: string | null;
    expenseCreateUrl: string | null;
    attendanceDateMin: string | null;
    attendanceDateMax: string;
    attendanceDateHelp: string;
    todayRecords: TodayRecord[];
}>();

const today = props.attendanceDateMax;
const overtimeHours = Array.from({ length: 10 }, (_, index) => index + 1);
const statusOptions = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'leave', label: 'Leave' },
];
const projectSearch = ref('');
const overtimeProjectSearch = ref('');
const employeeSearch = ref('');
const projectOpen = ref(false);
const overtimeProjectOpen = ref(false);
const employeeOpen = ref(false);
const employeeDropdownRef = ref<HTMLElement | null>(null);
const projectDropdownRef = ref<HTMLElement | null>(null);
const overtimeProjectDropdownRef = ref<HTMLElement | null>(null);

const form = useForm({
    project_id: '',
    overtime_project_id: '',
    employee_ids: [] as string[],
    status: '',
    leave_reason: '',
    attendance_date: today,
    attendance_end_date: today,
    has_overtime: false,
    overtime_hours: '',
});

const isPresent = computed(() => form.status === 'present');
const isLeave = computed(() => form.status === 'leave');
const attendanceDateMax = computed(() => (isLeave.value ? undefined : props.attendanceDateMax));
const attendanceDateHelp = computed(() =>
    isLeave.value ? 'Future leave date ranges are allowed. Admin will review payroll deduction.' : props.attendanceDateHelp,
);
const attendanceEndDateMin = computed(() => (isLeave.value ? form.attendance_date || props.attendanceDateMin : props.attendanceDateMin));

const filteredProjects = computed(() => {
    const query = projectSearch.value.trim().toLowerCase();

    if (!query) {
        return props.projects;
    }

    return props.projects.filter((project) => [project.name, project.status].some((value) => value.toLowerCase().includes(query)));
});

const selectedProject = computed(() => props.projects.find((project) => String(project.id) === form.project_id));
const selectedOvertimeProject = computed(() => props.projects.find((project) => String(project.id) === form.overtime_project_id));

const filteredEmployees = computed(() => {
    const query = employeeSearch.value.trim().toLowerCase();

    if (!query) {
        return props.employees;
    }

    return props.employees.filter((employee) =>
        [employee.code, employee.name, employee.profession, employee.type.replace('_', ' ')].some((value) => value.toLowerCase().includes(query)),
    );
});

const selectedEmployees = computed(() => props.employees.filter((employee) => form.employee_ids.includes(String(employee.id))));
const selectedEmployeeCount = computed(() => form.employee_ids.length);
const employeeButtonLabel = computed(() => {
    if (selectedEmployeeCount.value === 0) {
        return 'Select employees';
    }

    if (selectedEmployeeCount.value === 1) {
        const employee = selectedEmployees.value[0];

        return employee ? `${employee.code} - ${employee.name} - ${employee.profession}` : '1 employee selected';
    }

    return `${selectedEmployeeCount.value} employees selected`;
});

const leaveForEmployee = (employee: Employee) => {
    if (employee.status === 'on_leave') {
        return 'On leave';
    }

    return props.employeeLeaves.find(
        (leave) => leave.employeeId === employee.id && leave.startDate <= form.attendance_date && leave.endDate >= form.attendance_date,
    );
};

const employeeLeaveLabel = (employee: Employee) => {
    const leave = leaveForEmployee(employee);

    if (!leave) {
        return '';
    }

    if (typeof leave === 'string') {
        return leave;
    }

    return leave.reason ? `On leave - ${leave.reason}` : `On leave until ${leave.endDate}`;
};

const isEmployeeDisabled = (employee: Employee) => Boolean(leaveForEmployee(employee));

const statusLabel = (status: string) => status.charAt(0).toUpperCase() + status.slice(1);

const statusClass = (status: string) => {
    if (status === 'present') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'absent') return 'border-red-600/30 bg-red-600/10 text-red-700';

    return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
};

const todayRecordDetail = (record: TodayRecord) => {
    if (record.status === 'present') {
        const overtime =
            record.overtimeHours && Number(record.overtimeHours) > 0
                ? `, OT ${record.overtimeHours}h${record.overtimeProjectName && record.overtimeProjectName !== record.projectName ? ` - ${record.overtimeProjectName}` : ''}`
                : '';

        return `${record.projectName || 'Project not selected'}${overtime}`;
    }

    return record.reason || statusLabel(record.status);
};

const selectProject = (project: Project) => {
    form.project_id = String(project.id);
    projectSearch.value = '';
    projectOpen.value = false;
};

const selectOvertimeProject = (project: Project) => {
    form.overtime_project_id = String(project.id);
    overtimeProjectSearch.value = '';
    overtimeProjectOpen.value = false;
};

const toggleEmployee = (employee: Employee) => {
    if (isEmployeeDisabled(employee)) {
        return;
    }

    const employeeId = String(employee.id);

    if (form.employee_ids.includes(employeeId)) {
        form.employee_ids = form.employee_ids.filter((id) => id !== employeeId);
        return;
    }

    form.employee_ids = [...form.employee_ids, employeeId];
};

const clearSelectedEmployees = () => {
    form.employee_ids = [];
};

const closeEmployeeDropdown = () => {
    employeeOpen.value = false;
    employeeSearch.value = '';
};

const toggleEmployeeDropdown = () => {
    if (employeeOpen.value) {
        closeEmployeeDropdown();
        return;
    }

    employeeOpen.value = true;
};

const openNativePicker = (event: Event) => {
    const input = event.currentTarget as HTMLInputElement;
    input.showPicker?.();
};

const closeDropdownsOnOutsideClick = (event: MouseEvent) => {
    const target = event.target as Node;

    if (employeeDropdownRef.value && !employeeDropdownRef.value.contains(target)) {
        closeEmployeeDropdown();
    }

    if (projectDropdownRef.value && !projectDropdownRef.value.contains(target)) {
        projectOpen.value = false;
    }

    if (overtimeProjectDropdownRef.value && !overtimeProjectDropdownRef.value.contains(target)) {
        overtimeProjectOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdownsOnOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdownsOnOutsideClick);
});

watch(
    () => form.status,
    (status) => {
        if (status !== 'present') {
            form.project_id = '';
            form.overtime_project_id = '';
            form.has_overtime = false;
            form.overtime_hours = '';
            projectSearch.value = '';
            overtimeProjectSearch.value = '';
            projectOpen.value = false;
            overtimeProjectOpen.value = false;
        }

        if (status !== 'leave') {
            form.leave_reason = '';
            form.attendance_end_date = form.attendance_date;
        }

        if (status === 'leave' && (!form.attendance_end_date || form.attendance_end_date < form.attendance_date)) {
            form.attendance_end_date = form.attendance_date;
        }
    },
);

watch(
    () => form.attendance_date,
    () => {
        if (!isLeave.value || !form.attendance_end_date || form.attendance_end_date < form.attendance_date) {
            form.attendance_end_date = form.attendance_date;
        }

        form.employee_ids = form.employee_ids.filter((employeeId) => {
            const employee = props.employees.find((item) => String(item.id) === employeeId);

            return employee && !isEmployeeDisabled(employee);
        });
    },
);

watch(
    () => form.has_overtime,
    (hasOvertime) => {
        if (!hasOvertime) {
            form.overtime_hours = '';
            form.overtime_project_id = '';
            overtimeProjectSearch.value = '';
            overtimeProjectOpen.value = false;
        }
    },
);

const submit = () => {
    form.attendance_date = form.attendance_date || today;
    form.attendance_end_date = isLeave.value ? form.attendance_end_date || form.attendance_date : form.attendance_date;

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.employee_ids = [];
            form.overtime_project_id = '';
            form.leave_reason = '';
            form.attendance_end_date = form.attendance_date;
            form.has_overtime = false;
            form.overtime_hours = '';
            projectSearch.value = '';
            overtimeProjectSearch.value = '';
            employeeSearch.value = '';
            projectOpen.value = false;
            overtimeProjectOpen.value = false;
            employeeOpen.value = false;
        },
    });
};
</script>

<template>
    <Head title="Mark Attendance" />

    <main class="min-h-svh bg-background px-4 py-5 text-foreground sm:px-6">
        <div class="mx-auto flex w-full max-w-xl flex-col gap-5">
            <header class="flex flex-col items-center gap-3 py-2 text-center">
                <AppLogoIcon class="size-24" />
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Mark Attendance</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ employeeTypeLabel }} attendance form.</p>
                </div>
                <Link :href="`/fines/create?type=${encodeURIComponent(employeeType)}`" class="text-sm font-medium text-primary underline underline-offset-4">
                    Create Fine Ticket
                </Link>
                <Link v-if="dutyPlanUrl" :href="dutyPlanUrl" class="text-sm font-medium text-primary underline underline-offset-4">
                    Contracting Duty Planning
                </Link>
                <Link v-if="expenseCreateUrl" :href="expenseCreateUrl" class="text-sm font-medium text-primary underline underline-offset-4">
                    Create Expense Bill
                </Link>
            </header>

            <form class="rounded-lg border bg-card p-4 shadow-sm sm:p-5" @submit.prevent="submit">
                <div class="grid gap-5">
                    <div class="grid gap-2">
                        <Label for="employee-search">Employees</Label>
                        <div ref="employeeDropdownRef" class="relative">
                            <button
                                type="button"
                                class="flex h-11 w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 text-left text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @click="toggleEmployeeDropdown"
                            >
                                <span class="min-w-0 truncate">
                                    {{ employeeButtonLabel }}
                                </span>
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                            </button>

                            <div v-if="employeeOpen" class="absolute z-20 mt-2 w-full rounded-md border bg-popover p-2 shadow-lg">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input id="employee-search" v-model="employeeSearch" type="search" class="pl-9" placeholder="Search employees" />
                                </div>
                                <div v-if="selectedEmployeeCount" class="mt-2 flex items-center justify-between rounded-md bg-muted px-3 py-2 text-xs">
                                    <span>{{ selectedEmployeeCount }} selected</span>
                                    <button type="button" class="font-medium text-primary" @click="clearSelectedEmployees">Clear</button>
                                </div>
                                <div class="mt-2 max-h-56 overflow-y-auto">
                                    <button
                                        v-for="employee in filteredEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-start gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-55 disabled:hover:bg-transparent"
                                        :disabled="isEmployeeDisabled(employee)"
                                        @click="toggleEmployee(employee)"
                                    >
                                        <span
                                            class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border border-input"
                                            :class="form.employee_ids.includes(String(employee.id)) ? 'border-primary bg-primary text-primary-foreground' : 'bg-background'"
                                        >
                                            <CheckCircle2 v-if="form.employee_ids.includes(String(employee.id))" class="size-3" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ employee.code }} - {{ employee.name }}</span>
                                            <span class="block text-xs text-muted-foreground">
                                                {{ employee.profession }}<template v-if="employeeLeaveLabel(employee)"> - {{ employeeLeaveLabel(employee) }}</template>
                                            </span>
                                        </span>
                                    </button>
                                    <div v-if="filteredEmployees.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No employees found.</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedEmployees.length" class="flex flex-wrap gap-2">
                            <button
                                v-for="employee in selectedEmployees"
                                :key="employee.id"
                                type="button"
                                class="inline-flex max-w-full items-center gap-1 rounded-md border bg-muted px-2 py-1 text-xs"
                                @click="toggleEmployee(employee)"
                            >
                                <span class="truncate">{{ employee.code }} - {{ employee.name }}</span>
                                <X class="size-3 shrink-0" />
                            </button>
                        </div>
                        <InputError :message="form.errors.employee_ids" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Attendance Status</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <label
                                v-for="option in statusOptions"
                                :key="option.value"
                                class="flex h-11 cursor-pointer items-center justify-center rounded-md border px-3 text-sm font-medium transition hover:bg-accent"
                                :class="form.status === option.value ? 'border-primary bg-primary text-primary-foreground hover:bg-primary' : 'border-input bg-background'"
                            >
                                <input v-model="form.status" type="radio" name="status" :value="option.value" class="sr-only" />
                                {{ option.label }}
                            </label>
                        </div>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div v-if="form.status" class="grid gap-2">
                        <Label for="attendance-date">{{ isLeave ? 'Leave Date Range' : 'Attendance Date' }}</Label>
                        <div class="grid gap-2" :class="isLeave ? 'sm:grid-cols-2' : ''">
                            <div class="relative">
                                <CalendarDays class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="attendance-date"
                                    v-model="form.attendance_date"
                                    type="date"
                                    :min="props.attendanceDateMin"
                                    :max="attendanceDateMax"
                                    class="pl-9"
                                    @click="openNativePicker"
                                    @focus="openNativePicker"
                                />
                            </div>
                            <div v-if="isLeave" class="relative">
                                <CalendarDays class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="attendance-end-date"
                                    v-model="form.attendance_end_date"
                                    type="date"
                                    :min="attendanceEndDateMin"
                                    class="pl-9"
                                    @click="openNativePicker"
                                    @focus="openNativePicker"
                                />
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ attendanceDateHelp }}</p>
                        <InputError :message="form.errors.attendance_date" />
                        <InputError :message="form.errors.attendance_end_date" />
                    </div>

                    <template v-if="isPresent">
                        <div class="grid gap-2">
                            <Label for="project-search">Project</Label>
                            <div ref="projectDropdownRef" class="relative">
                                <button
                                    type="button"
                                    class="flex h-11 w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 text-left text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    @click="projectOpen = !projectOpen"
                                >
                                    <span class="min-w-0 truncate">
                                        {{ selectedProject ? `${selectedProject.name} - ${selectedProject.status}` : 'Select project' }}
                                    </span>
                                    <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                                </button>

                                <div v-if="projectOpen" class="absolute z-20 mt-2 w-full rounded-md border bg-popover p-2 shadow-lg">
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input id="project-search" v-model="projectSearch" type="search" class="pl-9" placeholder="Search project" />
                                    </div>
                                    <div class="mt-2 max-h-56 overflow-y-auto">
                                        <button
                                            v-for="project in filteredProjects"
                                            :key="project.id"
                                            type="button"
                                            class="flex w-full flex-col rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                            @click="selectProject(project)"
                                        >
                                            <span class="font-medium">{{ project.name }}</span>
                                            <span class="text-xs capitalize text-muted-foreground">{{ project.status }}</span>
                                        </button>
                                        <div v-if="filteredProjects.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No projects found.</div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.project_id" />
                        </div>

                        <label class="flex h-12 cursor-pointer items-center gap-3 rounded-md border p-3 text-sm font-medium">
                            <input v-model="form.has_overtime" type="checkbox" class="size-4 rounded border-input" />
                            <span>Overtime applied</span>
                        </label>

                        <div v-if="form.has_overtime" class="grid gap-2">
                            <Label for="overtime-project-search">Overtime Project</Label>
                            <div ref="overtimeProjectDropdownRef" class="relative">
                                <button
                                    type="button"
                                    class="flex h-11 w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 text-left text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    @click="overtimeProjectOpen = !overtimeProjectOpen"
                                >
                                    <span class="min-w-0 truncate">
                                        {{
                                            selectedOvertimeProject
                                                ? `${selectedOvertimeProject.name} - ${selectedOvertimeProject.status}`
                                                : selectedProject
                                                  ? `Same as main project - ${selectedProject.name}`
                                                  : 'Same as main project'
                                        }}
                                    </span>
                                    <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                                </button>

                                <div v-if="overtimeProjectOpen" class="absolute z-20 mt-2 w-full rounded-md border bg-popover p-2 shadow-lg">
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input id="overtime-project-search" v-model="overtimeProjectSearch" type="search" class="pl-9" placeholder="Search overtime project" />
                                    </div>
                                    <div class="mt-2 max-h-56 overflow-y-auto">
                                        <button
                                            type="button"
                                            class="flex w-full flex-col rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                            @click="
                                                form.overtime_project_id = '';
                                                overtimeProjectSearch = '';
                                                overtimeProjectOpen = false;
                                            "
                                        >
                                            <span class="font-medium">Same as main project</span>
                                            <span class="text-xs text-muted-foreground">{{ selectedProject ? selectedProject.name : 'Select main project first' }}</span>
                                        </button>
                                        <button
                                            v-for="project in filteredProjects"
                                            :key="project.id"
                                            type="button"
                                            class="flex w-full flex-col rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                            @click="selectOvertimeProject(project)"
                                        >
                                            <span class="font-medium">{{ project.name }}</span>
                                            <span class="text-xs capitalize text-muted-foreground">{{ project.status }}</span>
                                        </button>
                                        <div v-if="filteredProjects.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No projects found.</div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.overtime_project_id" />
                        </div>

                        <div v-if="form.has_overtime" class="grid gap-2">
                            <Label for="overtime-hours">Overtime Hours</Label>
                            <select
                                id="overtime-hours"
                                v-model="form.overtime_hours"
                                class="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Select hours</option>
                                <option v-for="hour in overtimeHours" :key="hour" :value="String(hour)">{{ hour }}</option>
                            </select>
                            <InputError :message="form.errors.overtime_hours" />
                        </div>
                    </template>

                    <div v-if="isLeave" class="grid gap-2">
                        <Label for="leave-reason">Leave Reason</Label>
                        <textarea
                            id="leave-reason"
                            v-model="form.leave_reason"
                            rows="4"
                            class="min-h-28 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Enter leave reason"
                        />
                        <InputError :message="form.errors.leave_reason" />
                    </div>

                    <InputError :message="form.errors.attendance_date" />

                    <Button type="submit" class="h-11 w-full" :disabled="form.processing">
                        Submit Attendance<template v-if="selectedEmployeeCount"> for {{ selectedEmployeeCount }} {{ selectedEmployeeCount === 1 ? 'Employee' : 'Employees' }}</template>
                    </Button>

                    <div v-if="form.recentlySuccessful" class="flex items-center justify-center gap-2 rounded-md border border-green-600/30 bg-green-600/10 px-3 py-2 text-sm text-green-600">
                        <CheckCircle2 class="size-4" />
                        Attendance submitted.
                    </div>
                </div>
            </form>

            <section class="rounded-lg border bg-card p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-medium">Today's Submitted Attendance</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ todayRecords.length }} records submitted by you today.</p>
                    </div>
                </div>

                <div v-if="todayRecords.length" class="mt-4 grid max-h-80 gap-2 overflow-y-auto pr-1">
                    <div
                        v-for="record in todayRecords"
                        :key="record.id"
                        class="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[1fr_auto] sm:items-center"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ record.employeeCode ? `${record.employeeCode} - ${record.employeeName}` : record.employeeName }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">{{ record.employeeProfession || '-' }}</p>
                            <p class="mt-1 truncate text-xs text-muted-foreground">{{ todayRecordDetail(record) }}</p>
                        </div>
                        <span class="w-fit rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(record.status)">
                            {{ statusLabel(record.status) }}
                        </span>
                    </div>
                </div>

                <div v-else class="mt-4 rounded-md border border-dashed p-5 text-center text-sm text-muted-foreground">
                    No attendance submitted by you today.
                </div>
            </section>
        </div>
    </main>
</template>
