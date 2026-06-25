<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, ChevronDown, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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

const props = defineProps<{
    projects: Project[];
    employees: Employee[];
    employeeLeaves: EmployeeLeave[];
    employeeType: string;
    employeeTypeLabel: string;
    submitUrl: string;
    attendanceDateMin: string | null;
    attendanceDateMax: string;
    attendanceDateHelp: string;
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

const form = useForm({
    project_id: '',
    overtime_project_id: '',
    employee_id: '',
    status: '',
    leave_reason: '',
    attendance_date: today,
    has_overtime: false,
    overtime_hours: '',
});

const isPresent = computed(() => form.status === 'present');
const isLeave = computed(() => form.status === 'leave');

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

const selectedEmployee = computed(() => props.employees.find((employee) => String(employee.id) === form.employee_id));

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

const selectEmployee = (employee: Employee) => {
    if (isEmployeeDisabled(employee)) {
        return;
    }

    form.employee_id = String(employee.id);
    employeeSearch.value = '';
    employeeOpen.value = false;
};

const openNativePicker = (event: Event) => {
    const input = event.currentTarget as HTMLInputElement;
    input.showPicker?.();
};

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
        }
    },
);

watch(
    () => form.attendance_date,
    () => {
        const employee = selectedEmployee.value;

        if (employee && isEmployeeDisabled(employee)) {
            form.employee_id = '';
        }
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

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.employee_id = '';
            form.overtime_project_id = '';
            form.leave_reason = '';
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
            </header>

            <form class="rounded-lg border bg-card p-4 shadow-sm sm:p-5" @submit.prevent="submit">
                <div class="grid gap-5">
                    <div class="grid gap-2">
                        <Label for="employee-search">Employee</Label>
                        <div class="relative">
                            <button
                                type="button"
                                class="flex h-11 w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 text-left text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @click="employeeOpen = !employeeOpen"
                            >
                                <span class="min-w-0 truncate">
                                    {{ selectedEmployee ? `${selectedEmployee.code} - ${selectedEmployee.name} - ${selectedEmployee.profession}` : 'Select employee' }}
                                </span>
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                            </button>

                            <div v-if="employeeOpen" class="absolute z-20 mt-2 w-full rounded-md border bg-popover p-2 shadow-lg">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input id="employee-search" v-model="employeeSearch" type="search" class="pl-9" placeholder="Search employee" />
                                </div>
                                <div class="mt-2 max-h-56 overflow-y-auto">
                            <button
                                v-for="employee in filteredEmployees"
                                :key="employee.id"
                                type="button"
                                class="flex w-full flex-col rounded-md px-3 py-2 text-left text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-55 disabled:hover:bg-transparent"
                                :disabled="isEmployeeDisabled(employee)"
                                @click="selectEmployee(employee)"
                            >
                                        <span class="font-medium">{{ employee.code }} - {{ employee.name }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            {{ employee.profession }}<template v-if="employeeLeaveLabel(employee)"> - {{ employeeLeaveLabel(employee) }}</template>
                                        </span>
                            </button>
                                    <div v-if="filteredEmployees.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No employees found.</div>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.employee_id" />
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
                        <Label for="attendance-date">Attendance Date</Label>
                        <div class="relative">
                            <CalendarDays class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="attendance-date"
                                v-model="form.attendance_date"
                                type="date"
                                :min="props.attendanceDateMin"
                                :max="props.attendanceDateMax"
                                class="pl-9"
                                @click="openNativePicker"
                                @focus="openNativePicker"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">{{ attendanceDateHelp }}</p>
                        <InputError :message="form.errors.attendance_date" />
                    </div>

                    <template v-if="isPresent">
                        <div class="grid gap-2">
                            <Label for="project-search">Project</Label>
                            <div class="relative">
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
                            <div class="relative">
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
                        Submit Attendance
                    </Button>

                    <div v-if="form.recentlySuccessful" class="flex items-center justify-center gap-2 rounded-md border border-green-600/30 bg-green-600/10 px-3 py-2 text-sm text-green-600">
                        <CheckCircle2 class="size-4" />
                        Attendance submitted.
                    </div>
                </div>
            </form>
        </div>
    </main>
</template>
