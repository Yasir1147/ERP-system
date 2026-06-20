<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Banknote, CalendarCheck, Clock3, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    name: string;
    profession: string;
    type: string;
    status: string;
    label: string;
}

interface PayrollRow {
    employeeId: number;
    employeeName: string;
    employeeProfession: string;
    employeeType: string;
    dailySalary: number;
    salaryRule: string;
    standardHoursPerDay: number;
    presentDays: number;
    absentDays: number;
    leaveDays: number;
    overtimeHours: number;
    hourlyRate: number;
    basicSalary: number;
    overtimeAmount: number;
    totalSalary: number;
    projectCount: number;
}

interface TypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    employees: Employee[];
    payrollRows: PayrollRow[];
    summary: {
        employeeCount: number;
        presentDays: number;
        overtimeHours: number;
        basicSalary: number;
        overtimeAmount: number;
        totalSalary: number;
    };
    filters: {
        type: string;
        employeeId: string;
        month: string;
    };
    typeOptions: TypeOption[];
    employeeTypes: Record<string, string>;
    salaryRules: Record<string, string>;
    selectedMonthLabel: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payroll Report',
        href: '/payroll/report',
    },
];

const filterType = ref(props.filters.type);
const filterEmployeeId = ref(props.filters.employeeId);
const filterMonth = ref(props.filters.month);
const search = ref('');

const employeeOptions = computed(() => props.employees.filter((employee) => filterType.value === 'all' || employee.type === filterType.value));

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.payrollRows;
    }

    return props.payrollRows.filter((row) =>
        [row.employeeName, row.employeeProfession, props.employeeTypes[row.employeeType], props.salaryRules[row.salaryRule]]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)),
    );
});

watch(filterType, () => {
    if (!employeeOptions.value.some((employee) => String(employee.id) === filterEmployeeId.value)) {
        filterEmployeeId.value = 'all';
    }
});

const money = (value: number) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const applyFilters = () => {
    router.get(
        '/payroll/report',
        {
            type: filterType.value,
            employee_id: filterEmployeeId.value,
            month: filterMonth.value,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};
</script>

<template>
    <Head title="Payroll Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Payroll Report</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Monthly salary calculation from attendance.</p>
                </div>
                <div class="grid gap-2 lg:grid-cols-[190px_240px_160px_auto]">
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
                        v-model="filterMonth"
                        type="month"
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
                            <p class="text-sm text-muted-foreground">Employees</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.employeeCount }}</p>
                        </div>
                        <Users class="size-6 text-muted-foreground" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Present Days</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.presentDays }}</p>
                        </div>
                        <CalendarCheck class="size-6 text-green-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Overtime Hours</p>
                            <p class="mt-2 text-3xl font-semibold">{{ summary.overtimeHours }}</p>
                        </div>
                        <Clock3 class="size-6 text-sky-600" />
                    </div>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Overtime Amount</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(summary.overtimeAmount) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Salary</p>
                            <p class="mt-2 text-2xl font-semibold">{{ money(summary.totalSalary) }}</p>
                        </div>
                        <Banknote class="size-6 text-green-700" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Payroll Report</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ selectedMonthLabel }} salary calculation from attendance.</p>
                    </div>
                    <div class="relative w-full sm:w-72">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" type="search" class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm" placeholder="Search payroll" />
                    </div>
                </div>

                <div v-if="filteredRows.length" class="mt-4 overflow-hidden rounded-md border">
                    <div class="grid min-w-[1180px] grid-cols-[1fr_0.65fr_0.55fr_0.55fr_0.55fr_0.65fr_0.7fr_0.7fr_0.7fr_0.75fr] border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                        <span>Employee</span>
                        <span>Rule</span>
                        <span>Present</span>
                        <span>Absent</span>
                        <span>Leave</span>
                        <span>OT Hours</span>
                        <span>Basic</span>
                        <span>OT Amount</span>
                        <span>Total</span>
                        <span>Projects</span>
                    </div>
                    <div class="max-h-[520px] overflow-auto">
                        <div v-for="row in filteredRows" :key="row.employeeId" class="grid min-w-[1180px] grid-cols-[1fr_0.65fr_0.55fr_0.55fr_0.55fr_0.65fr_0.7fr_0.7fr_0.7fr_0.75fr] items-center gap-3 border-b px-3 py-3 text-sm last:border-b-0">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ row.employeeName }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ row.employeeProfession }} - {{ employeeTypes[row.employeeType] }}</p>
                            </div>
                            <span class="text-muted-foreground">{{ salaryRules[row.salaryRule] }}</span>
                            <span>{{ row.presentDays }}</span>
                            <span>{{ row.absentDays }}</span>
                            <span>{{ row.leaveDays }}</span>
                            <span>{{ row.overtimeHours }}</span>
                            <span>{{ money(row.basicSalary) }}</span>
                            <span>{{ money(row.overtimeAmount) }}</span>
                            <span class="font-semibold">{{ money(row.totalSalary) }}</span>
                            <span class="text-muted-foreground">{{ row.projectCount }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="mt-4 flex min-h-56 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                    No payroll records match the selected filters.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
