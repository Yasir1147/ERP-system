<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Banknote, CalendarCheck, Clock3, Save, Search, Users } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

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
    bonusExtra: number;
    previousBalance: number;
    totalBalance: number;
    deduction: number;
    paidByCash: number;
    balance: number;
    remarks: string | null;
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
        totalBalance: number;
        paidByCash: number;
        balance: number;
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
const savingEmployeeId = ref<number | null>(null);

const adjustments = reactive<Record<number, { bonusExtra: string; previousBalance: string; deduction: string; paidByCash: string; remarks: string }>>(
    props.payrollRows.reduce<Record<number, { bonusExtra: string; previousBalance: string; deduction: string; paidByCash: string; remarks: string }>>((values, row) => {
        values[row.employeeId] = {
            bonusExtra: String(row.bonusExtra),
            previousBalance: String(row.previousBalance),
            deduction: String(row.deduction),
            paidByCash: String(row.paidByCash),
            remarks: row.remarks || '',
        };

        return values;
    }, {}),
);

const employeeOptions = computed(() => props.employees.filter((employee) => filterType.value === 'all' || employee.type === filterType.value));

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.payrollRows;
    }

    return props.payrollRows.filter((row) =>
        [
            row.employeeName,
            row.employeeProfession,
            props.employeeTypes[row.employeeType],
            props.salaryRules[row.salaryRule],
            adjustments[row.employeeId]?.remarks,
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

const money = (value: number) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const numeric = (value: string) => Number(value || 0);

const liveTotalBalance = (row: PayrollRow) => row.totalSalary + numeric(adjustments[row.employeeId]?.bonusExtra || '0') + numeric(adjustments[row.employeeId]?.previousBalance || '0');

const liveBalance = (row: PayrollRow) => liveTotalBalance(row) - numeric(adjustments[row.employeeId]?.deduction || '0') - numeric(adjustments[row.employeeId]?.paidByCash || '0');

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

const saveAdjustment = (row: PayrollRow) => {
    const adjustment = adjustments[row.employeeId];

    if (!adjustment) {
        return;
    }

    savingEmployeeId.value = row.employeeId;

    router.put(
        `/payroll/report/${row.employeeId}/adjustment`,
        {
            type: filterType.value,
            employee_id: filterEmployeeId.value,
            month: filterMonth.value,
            bonus_extra: adjustment.bonusExtra,
            previous_balance: adjustment.previousBalance,
            deduction: adjustment.deduction,
            paid_by_cash: adjustment.paidByCash,
            remarks: adjustment.remarks,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                savingEmployeeId.value = null;
            },
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
                    <p class="mt-1 text-sm text-muted-foreground">Monthly salary calculation from attendance and payroll adjustments.</p>
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
                    <p class="text-sm text-muted-foreground">Paid by Cash</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(summary.paidByCash) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Balance</p>
                            <p class="mt-2 text-2xl font-semibold">{{ money(summary.balance) }}</p>
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
                    <div class="max-h-[560px] overflow-auto">
                        <table class="w-full min-w-[1840px] table-fixed text-sm">
                            <thead class="sticky top-0 z-10 border-b bg-card text-left text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th class="w-[54px] px-3 py-2 font-medium">S.No</th>
                                    <th class="w-[230px] px-3 py-2 font-medium">Employee</th>
                                    <th class="w-[70px] px-3 py-2 font-medium">Days</th>
                                    <th class="w-[90px] px-3 py-2 font-medium">Per Day</th>
                                    <th class="w-[100px] px-3 py-2 font-medium">Salary</th>
                                    <th class="w-[80px] px-3 py-2 font-medium">OT Hrs</th>
                                    <th class="w-[100px] px-3 py-2 font-medium">OT Salary</th>
                                    <th class="w-[110px] px-3 py-2 font-medium">New Total</th>
                                    <th class="w-[130px] px-3 py-2 font-medium">Bonus</th>
                                    <th class="w-[130px] px-3 py-2 font-medium">Pr. Balance</th>
                                    <th class="w-[120px] px-3 py-2 font-medium">Total Balance</th>
                                    <th class="w-[130px] px-3 py-2 font-medium">Deduction</th>
                                    <th class="w-[130px] px-3 py-2 font-medium">Paid Cash</th>
                                    <th class="w-[110px] px-3 py-2 font-medium">Balance</th>
                                    <th class="w-[180px] px-3 py-2 font-medium">Remarks</th>
                                    <th class="w-[76px] px-3 py-2 text-right font-medium">Save</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in filteredRows" :key="row.employeeId" class="border-b last:border-b-0">
                                    <td class="px-3 py-3 text-muted-foreground">{{ index + 1 }}</td>
                                    <td class="px-3 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ row.employeeName }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ row.employeeProfession }} - {{ employeeTypes[row.employeeType] }}</p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">{{ row.presentDays }}</td>
                                    <td class="px-3 py-3">{{ money(row.dailySalary) }}</td>
                                    <td class="px-3 py-3">{{ money(row.basicSalary) }}</td>
                                    <td class="px-3 py-3">{{ row.overtimeHours }}</td>
                                    <td class="px-3 py-3">{{ money(row.overtimeAmount) }}</td>
                                    <td class="px-3 py-3 font-semibold">{{ money(row.totalSalary) }}</td>
                                    <td class="px-3 py-3">
                                        <input v-model="adjustments[row.employeeId].bonusExtra" type="number" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input v-model="adjustments[row.employeeId].previousBalance" type="number" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                    </td>
                                    <td class="px-3 py-3 font-medium">{{ money(liveTotalBalance(row)) }}</td>
                                    <td class="px-3 py-3">
                                        <input v-model="adjustments[row.employeeId].deduction" type="number" min="0" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input v-model="adjustments[row.employeeId].paidByCash" type="number" min="0" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                    </td>
                                    <td class="px-3 py-3 font-semibold">{{ money(liveBalance(row)) }}</td>
                                    <td class="px-3 py-3">
                                        <input v-model="adjustments[row.employeeId].remarks" type="text" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" placeholder="Remarks" />
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex size-9 items-center justify-center rounded-md border text-sm hover:bg-accent disabled:opacity-60"
                                            :disabled="savingEmployeeId === row.employeeId"
                                            @click="saveAdjustment(row)"
                                        >
                                            <Save class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="mt-4 flex min-h-56 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                    No payroll records match the selected filters.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
