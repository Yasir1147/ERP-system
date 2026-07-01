<script setup lang="ts">
import SortableHeader from '@/components/SortableHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Banknote, BookOpen, CalendarCheck, Clock3, FileDown, FileSpreadsheet, Save, Search, Users, X } from 'lucide-vue-next';
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
    absenceDeduction: number;
    overtimeAmount: number;
    totalSalary: number;
    bonusExtra: number;
    previousBalance: number;
    autoPreviousBalance: number;
    previousBalanceOverridden: boolean;
    totalBalance: number;
    deduction: number;
    paidByCash: number;
    balance: number;
    remarks: string | null;
    projectCount: number;
}

interface LedgerRow extends PayrollRow {
    month: string;
    monthLabel: string;
}

interface LedgerEmployee {
    id: number;
    name: string;
    profession: string;
    type: string;
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
        absentDays: number;
        overtimeHours: number;
        basicSalary: number;
        absenceDeduction: number;
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
    absenceDeductionSettings: {
        enabled: boolean;
        apply_to: string;
    };
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
type SortKey =
    | 'employee'
    | 'days'
    | 'absent'
    | 'per_day'
    | 'salary'
    | 'absent_deduction'
    | 'ot_hours'
    | 'ot_salary'
    | 'new_total'
    | 'bonus'
    | 'previous_balance'
    | 'total_balance'
    | 'deduction'
    | 'paid_cash'
    | 'balance'
    | 'remarks';
const sortKey = ref<SortKey>('employee');
const sortDirection = ref<'asc' | 'desc'>('asc');
const selectedEmployeeIds = ref<number[]>([]);
const bulkRemarks = ref('');
const savingEmployeeId = ref<number | null>(null);
const savingSelected = ref(false);
const saveMessage = ref('');
const saveError = ref('');
const ledgerOpen = ref(false);
const ledgerLoading = ref(false);
const ledgerSavingKey = ref<string | null>(null);
const ledgerEmployee = ref<LedgerEmployee | null>(null);
const ledgerRows = ref<LedgerRow[]>([]);
const ledgerFromMonth = ref(props.filters.month);
const ledgerToMonth = ref(props.filters.month);

const adjustments = reactive<
    Record<number, { bonusExtra: string; previousBalance: string; previousBalanceOverridden: boolean; deduction: string; paidByCash: string; remarks: string }>
>(
    props.payrollRows.reduce<
        Record<number, { bonusExtra: string; previousBalance: string; previousBalanceOverridden: boolean; deduction: string; paidByCash: string; remarks: string }>
    >((values, row) => {
        values[row.employeeId] = {
            bonusExtra: String(row.bonusExtra),
            previousBalance: String(row.previousBalance),
            previousBalanceOverridden: row.previousBalanceOverridden,
            deduction: String(row.deduction),
            paidByCash: String(row.paidByCash),
            remarks: row.remarks || '',
        };

        return values;
    }, {}),
);

const ledgerAdjustments = reactive<
    Record<string, { bonusExtra: string; previousBalance: string; previousBalanceOverridden: boolean; deduction: string; paidByCash: string; remarks: string }>
>({});

const employeeOptions = computed(() => props.employees.filter((employee) => filterType.value === 'all' || employee.type === filterType.value));

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();
    const rows = query
        ? props.payrollRows.filter((row) =>
              [
                  row.employeeName,
                  row.employeeProfession,
                  props.employeeTypes[row.employeeType],
                  props.salaryRules[row.salaryRule],
                  adjustments[row.employeeId]?.remarks,
              ]
                  .filter(Boolean)
                  .some((value) => String(value).toLowerCase().includes(query)),
          )
        : props.payrollRows;

    return [...rows].sort((first, second) => {
        const numericAdjustment = (employeeId: number, key: 'bonusExtra' | 'previousBalance' | 'deduction' | 'paidByCash') => Number(adjustments[employeeId]?.[key] || 0);
        const totalBalance = (row: PayrollRow) => row.totalSalary + numericAdjustment(row.employeeId, 'bonusExtra') + numericAdjustment(row.employeeId, 'previousBalance');
        const balance = (row: PayrollRow) => totalBalance(row) - numericAdjustment(row.employeeId, 'deduction') - numericAdjustment(row.employeeId, 'paidByCash');
        const valueFor = (row: PayrollRow) => {
            if (sortKey.value === 'employee') return row.employeeName;
            if (sortKey.value === 'days') return row.presentDays;
            if (sortKey.value === 'absent') return row.absentDays;
            if (sortKey.value === 'per_day') return row.dailySalary;
            if (sortKey.value === 'salary') return row.basicSalary;
            if (sortKey.value === 'absent_deduction') return row.absenceDeduction;
            if (sortKey.value === 'ot_hours') return row.overtimeHours;
            if (sortKey.value === 'ot_salary') return row.overtimeAmount;
            if (sortKey.value === 'new_total') return row.totalSalary;
            if (sortKey.value === 'bonus') return numericAdjustment(row.employeeId, 'bonusExtra');
            if (sortKey.value === 'previous_balance') return numericAdjustment(row.employeeId, 'previousBalance');
            if (sortKey.value === 'total_balance') return totalBalance(row);
            if (sortKey.value === 'deduction') return numericAdjustment(row.employeeId, 'deduction');
            if (sortKey.value === 'paid_cash') return numericAdjustment(row.employeeId, 'paidByCash');
            if (sortKey.value === 'balance') return balance(row);
            return adjustments[row.employeeId]?.remarks || '';
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

const visibleRowIds = computed(() => filteredRows.value.map((row) => row.employeeId));

const selectedVisibleCount = computed(() => visibleRowIds.value.filter((id) => selectedEmployeeIds.value.includes(id)).length);

const allVisibleSelected = computed(() => visibleRowIds.value.length > 0 && selectedVisibleCount.value === visibleRowIds.value.length);

const someVisibleSelected = computed(() => selectedVisibleCount.value > 0 && !allVisibleSelected.value);

const selectedRows = computed(() => props.payrollRows.filter((row) => selectedEmployeeIds.value.includes(row.employeeId)));

const bulkPayslipsUrl = computed(() => {
    const params = new URLSearchParams({
        month: filterMonth.value,
        employee_ids: selectedEmployeeIds.value.join(','),
    });

    return `/payroll/report/payslips?${params.toString()}`;
});

const checkboxChecked = (event: Event) => (event.target as HTMLInputElement).checked;

const toggleRowSelection = (employeeId: number, event: Event) => {
    const checked = checkboxChecked(event);
    const selected = new Set(selectedEmployeeIds.value);

    if (checked) {
        selected.add(employeeId);
    } else {
        selected.delete(employeeId);
    }

    selectedEmployeeIds.value = Array.from(selected);
};

const toggleVisibleSelection = (event: Event) => {
    const checked = checkboxChecked(event);
    const selected = new Set(selectedEmployeeIds.value);

    visibleRowIds.value.forEach((id) => {
        if (checked) {
            selected.add(id);
        } else {
            selected.delete(id);
        }
    });

    selectedEmployeeIds.value = Array.from(selected);
};

const openSelectedPayslips = () => {
    if (!selectedEmployeeIds.value.length) {
        return;
    }

    window.open(bulkPayslipsUrl.value, '_blank', 'noopener');
};

const applyBulkRemarks = () => {
    const remark = bulkRemarks.value.trim();

    if (!selectedEmployeeIds.value.length) {
        saveMessage.value = '';
        saveError.value = 'Please select at least one employee.';
        return;
    }

    if (!remark) {
        saveMessage.value = '';
        saveError.value = 'Bulk remarks cannot be empty.';
        return;
    }

    selectedEmployeeIds.value.forEach((employeeId) => {
        if (adjustments[employeeId]) {
            adjustments[employeeId].remarks = remark;
        }
    });

    saveError.value = '';
    saveMessage.value = `Remarks applied to ${selectedEmployeeIds.value.length} selected employee(s). Click Save Selected to store the changes.`;
};

const saveSelectedAdjustments = async () => {
    if (!selectedRows.value.length) {
        saveMessage.value = '';
        saveError.value = 'Please select at least one employee.';
        return;
    }

    saveMessage.value = '';
    saveError.value = '';
    savingSelected.value = true;

    try {
        const token = csrfToken();
        const form = new FormData();
        form.append('_token', token);
        form.append('type', filterType.value);
        form.append('employee_id', filterEmployeeId.value);
        form.append('month', filterMonth.value);

        selectedRows.value.forEach((row, index) => {
            const adjustment = adjustments[row.employeeId];

            form.append(`adjustments[${index}][employee_id]`, String(row.employeeId));
            form.append(`adjustments[${index}][bonus_extra]`, adjustment?.bonusExtra || '0');
            form.append(`adjustments[${index}][previous_balance]`, adjustment?.previousBalance || '0');
            form.append(`adjustments[${index}][previous_balance_overridden]`, adjustment?.previousBalanceOverridden ? '1' : '0');
            form.append(`adjustments[${index}][deduction]`, adjustment?.deduction || '0');
            form.append(`adjustments[${index}][paid_by_cash]`, adjustment?.paidByCash || '0');
            form.append(`adjustments[${index}][remarks]`, adjustment?.remarks || '');
        });

        const response = await fetch('/payroll/report/adjustments-bulk', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: form,
        });

        if (handleSessionExpired(response)) {
            return;
        }

        if (!response.ok) {
            const error = await response.json().catch(() => null);
            const message = error?.message || 'Selected payroll records were not saved. Check the values and try again.';
            throw new Error(message);
        }

        saveMessage.value = `${selectedRows.value.length} selected payroll record(s) saved.`;
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
    } catch (error) {
        saveError.value = error instanceof Error ? error.message : 'Selected payroll records were not saved. Check the values and try again.';
    } finally {
        savingSelected.value = false;
    }
};

watch(filterType, () => {
    if (!employeeOptions.value.some((employee) => String(employee.id) === filterEmployeeId.value)) {
        filterEmployeeId.value = 'all';
    }
});

watch(
    () => props.payrollRows.map((row) => row.employeeId),
    (ids) => {
        const validIds = new Set(ids);
        selectedEmployeeIds.value = selectedEmployeeIds.value.filter((id) => validIds.has(id));
    },
);

const money = (value: number) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const sortPayrollRows = (key: string) => {
    const nextKey = key as SortKey;

    if (sortKey.value === nextKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextKey;
    sortDirection.value = 'asc';
};

const numeric = (value: string) => Number(value || 0);

const rowPreviousBalance = (row: PayrollRow) => {
    const adjustment = adjustments[row.employeeId];

    return adjustment?.previousBalanceOverridden ? numeric(adjustment.previousBalance) : row.autoPreviousBalance;
};

const liveTotalBalance = (row: PayrollRow) => row.totalSalary + numeric(adjustments[row.employeeId]?.bonusExtra || '0') + rowPreviousBalance(row);

const liveBalance = (row: PayrollRow) =>
    liveTotalBalance(row) - row.absenceDeduction - numeric(adjustments[row.employeeId]?.deduction || '0') - numeric(adjustments[row.employeeId]?.paidByCash || '0');

const ledgerKey = (row: LedgerRow) => `${row.employeeId}-${row.month}`;

const ledgerPreviousBalance = (row: LedgerRow) => {
    const adjustment = ledgerAdjustments[ledgerKey(row)];

    return adjustment?.previousBalanceOverridden ? numeric(adjustment.previousBalance) : row.autoPreviousBalance;
};

const liveLedgerTotalBalance = (row: LedgerRow) =>
    row.totalSalary + numeric(ledgerAdjustments[ledgerKey(row)]?.bonusExtra || '0') + ledgerPreviousBalance(row);

const liveLedgerBalance = (row: LedgerRow) =>
    liveLedgerTotalBalance(row) - row.absenceDeduction - numeric(ledgerAdjustments[ledgerKey(row)]?.deduction || '0') - numeric(ledgerAdjustments[ledgerKey(row)]?.paidByCash || '0');

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

const saveAdjustment = async (row: PayrollRow) => {
    const adjustment = adjustments[row.employeeId];

    if (!adjustment) {
        return;
    }

    saveMessage.value = '';
    saveError.value = '';
    savingEmployeeId.value = row.employeeId;

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
        const form = new FormData();
        form.append('_token', csrfToken);
        form.append('_method', 'PUT');
        form.append('type', filterType.value);
        form.append('employee_id', filterEmployeeId.value);
        form.append('month', filterMonth.value);
        form.append('bonus_extra', adjustment.bonusExtra);
        form.append('previous_balance', adjustment.previousBalance);
        form.append('previous_balance_overridden', adjustment.previousBalanceOverridden ? '1' : '0');
        form.append('deduction', adjustment.deduction);
        form.append('paid_by_cash', adjustment.paidByCash);
        form.append('remarks', adjustment.remarks);

        const response = await fetch(`/payroll/report/${row.employeeId}/adjustment`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: form,
        });

        if (handleSessionExpired(response)) {
            return;
        }

        if (!response.ok) {
            const error = await response.json().catch(() => null);
            const message = error?.message || 'Payroll was not saved. Check the values and try again.';
            throw new Error(message);
        }

        saveMessage.value = `${row.employeeName} payroll saved.`;
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
    } catch (error) {
        saveError.value = error instanceof Error ? error.message : 'Payroll was not saved. Check the values and try again.';
    } finally {
        savingEmployeeId.value = null;
    }
};

const payslipUrl = (row: PayrollRow) => `/payroll/report/${row.employeeId}/payslip?month=${encodeURIComponent(filterMonth.value)}`;

const payslipExportUrl = (row: PayrollRow) => `/payroll/report/${row.employeeId}/payslip-export?month=${encodeURIComponent(filterMonth.value)}`;

const ledgerPrintUrl = computed(() => {
    if (!ledgerEmployee.value) {
        return '#';
    }

    const params = new URLSearchParams({
        from_month: ledgerFromMonth.value,
        to_month: ledgerToMonth.value,
    });

    return `/payroll/report/${ledgerEmployee.value.id}/ledger-print?${params.toString()}`;
});

const ledgerExportUrl = computed(() => {
    if (!ledgerEmployee.value) {
        return '#';
    }

    const params = new URLSearchParams({
        from_month: ledgerFromMonth.value,
        to_month: ledgerToMonth.value,
    });

    return `/payroll/report/${ledgerEmployee.value.id}/ledger-export?${params.toString()}`;
});

const reportPrintUrl = computed(() => {
    const params = new URLSearchParams({
        type: filterType.value,
        employee_id: filterEmployeeId.value,
        month: filterMonth.value,
    });

    return `/payroll/report-print?${params.toString()}`;
});

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

const handleSessionExpired = (response: Response) => {
    if (response.status !== 419) {
        return false;
    }

    saveMessage.value = '';
    saveError.value = 'Your session token expired. The page will refresh, then please try saving again.';
    window.setTimeout(() => window.location.reload(), 1000);

    return true;
};

const hydrateLedgerAdjustments = () => {
    ledgerRows.value.forEach((row) => {
        ledgerAdjustments[ledgerKey(row)] = {
            bonusExtra: String(row.bonusExtra),
            previousBalance: String(row.previousBalance),
            previousBalanceOverridden: row.previousBalanceOverridden,
            deduction: String(row.deduction),
            paidByCash: String(row.paidByCash),
            remarks: row.remarks || '',
        };
    });
};

const loadLedger = async (row?: PayrollRow) => {
    const employeeId = row?.employeeId || ledgerEmployee.value?.id;

    if (!employeeId) {
        return;
    }

    if (row) {
        ledgerFromMonth.value = filterMonth.value;
        ledgerToMonth.value = filterMonth.value;
        ledgerOpen.value = true;
    }

    ledgerLoading.value = true;

    try {
        const params = new URLSearchParams({
            from_month: ledgerFromMonth.value,
            to_month: ledgerToMonth.value,
        });
        const response = await fetch(`/payroll/report/${employeeId}/ledger?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load ledger.');
        }

        const data = await response.json();
        ledgerEmployee.value = data.employee;
        ledgerRows.value = data.rows;
        hydrateLedgerAdjustments();
    } finally {
        ledgerLoading.value = false;
    }
};

const closeLedger = () => {
    ledgerOpen.value = false;
    ledgerEmployee.value = null;
    ledgerRows.value = [];
};

const saveLedgerAdjustment = async (row: LedgerRow) => {
    const key = ledgerKey(row);
    const adjustment = ledgerAdjustments[key];

    if (!adjustment) {
        return;
    }

    ledgerSavingKey.value = key;

    try {
        const response = await fetch(`/payroll/report/${row.employeeId}/adjustment`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                type: filterType.value,
                employee_id: filterEmployeeId.value,
                month: row.month,
                bonus_extra: adjustment.bonusExtra,
                previous_balance: adjustment.previousBalance,
                previous_balance_overridden: adjustment.previousBalanceOverridden,
                deduction: adjustment.deduction,
                paid_by_cash: adjustment.paidByCash,
                remarks: adjustment.remarks,
            }),
        });

        if (handleSessionExpired(response)) {
            return;
        }

        if (!response.ok) {
            throw new Error('Unable to save ledger row.');
        }

        await loadLedger();
    } finally {
        ledgerSavingKey.value = null;
    }
};

const syncPreviousBalanceMode = (row: PayrollRow) => {
    const adjustment = adjustments[row.employeeId];

    if (adjustment && !adjustment.previousBalanceOverridden) {
        adjustment.previousBalance = String(row.autoPreviousBalance);
    }
};

const syncLedgerPreviousBalanceMode = (row: LedgerRow) => {
    const adjustment = ledgerAdjustments[ledgerKey(row)];

    if (adjustment && !adjustment.previousBalanceOverridden) {
        adjustment.previousBalance = String(row.autoPreviousBalance);
    }
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

            <div class="grid auto-rows-min gap-4 md:grid-cols-3 xl:grid-cols-6">
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
                    <p class="text-sm text-muted-foreground">Absent Days</p>
                    <p class="mt-2 text-3xl font-semibold">{{ summary.absentDays }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Fixed rule {{ absenceDeductionSettings.enabled ? 'active' : 'off' }}</p>
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
                    <p class="text-sm text-muted-foreground">Absent Deduction</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(summary.absenceDeduction) }}</p>
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
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!selectedEmployeeIds.length"
                            @click="openSelectedPayslips"
                        >
                            <FileDown class="size-4" />
                            Selected Payslips
                            <span v-if="selectedEmployeeIds.length" class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ selectedEmployeeIds.length }}</span>
                        </button>
                        <a
                            :href="reportPrintUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm hover:bg-accent"
                        >
                            <FileDown class="size-4" />
                            Report PDF
                        </a>
                        <div class="relative w-full sm:w-72">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="search" type="search" class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm" placeholder="Search payroll" />
                        </div>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 rounded-md border bg-muted/20 p-3 lg:grid-cols-[minmax(260px,1fr)_auto_auto]">
                    <input
                        v-model="bulkRemarks"
                        type="text"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        placeholder="Bulk remarks for selected employees"
                    />
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-md border px-3 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!selectedEmployeeIds.length"
                        @click="applyBulkRemarks"
                    >
                        Apply to Selected
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!selectedEmployeeIds.length || savingSelected"
                        @click="saveSelectedAdjustments"
                    >
                        <Save class="size-4" />
                        Save Selected
                        <span v-if="selectedEmployeeIds.length" class="rounded bg-primary-foreground/15 px-1.5 py-0.5 text-xs">{{ selectedEmployeeIds.length }}</span>
                    </button>
                </div>

                <div v-if="saveMessage || saveError" class="mt-3 rounded-md border px-3 py-2 text-sm" :class="saveError ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'">
                    {{ saveError || saveMessage }}
                </div>

                <div v-if="filteredRows.length" class="mt-4 overflow-hidden rounded-md border">
                    <div class="max-h-[560px] overflow-auto">
                        <table class="w-full min-w-[2170px] table-fixed text-sm">
                            <thead class="sticky top-0 z-10 border-b bg-card text-left text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th class="w-[48px] px-3 py-2 text-center font-medium">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                            :checked="allVisibleSelected"
                                            :aria-checked="someVisibleSelected ? 'mixed' : allVisibleSelected"
                                            title="Select visible rows"
                                            @change="toggleVisibleSelection"
                                        />
                                    </th>
                                    <th class="w-[54px] px-3 py-2 font-medium">S.No</th>
                                    <th class="w-[230px] px-3 py-2 font-medium">
                                        <SortableHeader label="Employee" column="employee" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[70px] px-3 py-2 font-medium">
                                        <SortableHeader label="Days" column="days" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[70px] px-3 py-2 font-medium">
                                        <SortableHeader label="Absent" column="absent" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[90px] px-3 py-2 font-medium">
                                        <SortableHeader label="Per Day" column="per_day" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[100px] px-3 py-2 font-medium">
                                        <SortableHeader label="Salary" column="salary" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[110px] px-3 py-2 font-medium">
                                        <SortableHeader label="Absent Ded." column="absent_deduction" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[80px] px-3 py-2 font-medium">
                                        <SortableHeader label="OT Hrs" column="ot_hours" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[100px] px-3 py-2 font-medium">
                                        <SortableHeader label="OT Salary" column="ot_salary" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[110px] px-3 py-2 font-medium">
                                        <SortableHeader label="New Total" column="new_total" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[130px] px-3 py-2 font-medium">
                                        <SortableHeader label="Bonus" column="bonus" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[130px] px-3 py-2 font-medium">
                                        <SortableHeader label="Pr. Balance" column="previous_balance" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[120px] px-3 py-2 font-medium">
                                        <SortableHeader label="Total Balance" column="total_balance" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[130px] px-3 py-2 font-medium">
                                        <SortableHeader label="Deduction" column="deduction" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[130px] px-3 py-2 font-medium">
                                        <SortableHeader label="Paid Cash" column="paid_cash" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[110px] px-3 py-2 font-medium">
                                        <SortableHeader label="Balance" column="balance" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[180px] px-3 py-2 font-medium">
                                        <SortableHeader label="Remarks" column="remarks" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortPayrollRows" />
                                    </th>
                                    <th class="w-[42px] px-1 py-2 text-center font-medium">Ledger</th>
                                    <th class="w-[42px] px-1 py-2 text-center font-medium">PDF</th>
                                    <th class="w-[42px] px-1 py-2 text-center font-medium">Excel</th>
                                    <th class="sticky right-0 z-20 w-[52px] bg-card px-1 py-2 text-center font-medium shadow-[-8px_0_14px_-14px_rgba(0,0,0,0.75)]">Save</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in filteredRows" :key="row.employeeId" class="border-b last:border-b-0">
                                    <td class="px-3 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                            :checked="selectedEmployeeIds.includes(row.employeeId)"
                                            :title="`Select ${row.employeeName}`"
                                            @change="toggleRowSelection(row.employeeId, $event)"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-muted-foreground">{{ index + 1 }}</td>
                                    <td class="px-3 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ row.employeeName }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ row.employeeProfession }} - {{ employeeTypes[row.employeeType] }}</p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">{{ row.presentDays }}</td>
                                    <td class="px-3 py-3">{{ row.absentDays }}</td>
                                    <td class="px-3 py-3">{{ money(row.dailySalary) }}</td>
                                    <td class="px-3 py-3">{{ money(row.basicSalary) }}</td>
                                    <td class="px-3 py-3">{{ money(row.absenceDeduction) }}</td>
                                    <td class="px-3 py-3">{{ row.overtimeHours }}</td>
                                    <td class="px-3 py-3">{{ money(row.overtimeAmount) }}</td>
                                    <td class="px-3 py-3 font-semibold">{{ money(row.totalSalary) }}</td>
                                    <td class="px-3 py-3">
                                        <input v-model="adjustments[row.employeeId].bonusExtra" type="number" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="adjustments[row.employeeId].previousBalance"
                                            type="number"
                                            step="0.01"
                                            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm disabled:bg-muted/50"
                                            :disabled="!adjustments[row.employeeId].previousBalanceOverridden"
                                        />
                                        <label class="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                                            <input
                                                v-model="adjustments[row.employeeId].previousBalanceOverridden"
                                                type="checkbox"
                                                class="size-3"
                                                @change="syncPreviousBalanceMode(row)"
                                            />
                                            Manual
                                        </label>
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
                                    <td class="px-1 py-3 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md border text-sm hover:bg-accent"
                                            title="Open ledger"
                                            @click="loadLedger(row)"
                                        >
                                            <BookOpen class="size-4" />
                                        </button>
                                    </td>
                                    <td class="px-1 py-3 text-center">
                                        <a
                                            :href="payslipUrl(row)"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex size-8 items-center justify-center rounded-md border text-sm hover:bg-accent"
                                            title="Download payslip"
                                        >
                                            <FileDown class="size-4" />
                                        </a>
                                    </td>
                                    <td class="px-1 py-3 text-center">
                                        <a
                                            :href="payslipExportUrl(row)"
                                            class="inline-flex size-8 items-center justify-center rounded-md border text-sm hover:bg-accent"
                                            title="Download Excel"
                                        >
                                            <FileSpreadsheet class="size-4" />
                                        </a>
                                    </td>
                                    <td class="sticky right-0 z-10 bg-card px-1 py-3 text-center shadow-[-8px_0_14px_-14px_rgba(0,0,0,0.75)]">
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md border bg-background text-sm hover:bg-accent disabled:opacity-60"
                                            title="Save payroll"
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

            <div v-if="ledgerOpen" class="fixed inset-0 z-50 bg-black/50 p-2 sm:p-4">
                <div class="mx-auto flex max-h-[94vh] w-[calc(100vw-1rem)] max-w-none flex-col overflow-hidden rounded-lg border bg-background shadow-xl sm:w-[calc(100vw-2rem)]">
                    <div class="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Employee Ledger</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ ledgerEmployee?.name }}<template v-if="ledgerEmployee"> - {{ ledgerEmployee.profession }} - {{ employeeTypes[ledgerEmployee.type] }}</template>
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[160px_160px_auto_auto_auto_auto]">
                            <input
                                v-model="ledgerFromMonth"
                                type="month"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            />
                            <input
                                v-model="ledgerToMonth"
                                type="month"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            />
                            <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" :disabled="ledgerLoading" @click="loadLedger()">
                                Filter
                            </button>
                            <a
                                :href="ledgerPrintUrl"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm hover:bg-accent"
                                title="Download ledger PDF"
                            >
                                <FileDown class="size-4" />
                                Ledger PDF
                            </a>
                            <a
                                :href="ledgerExportUrl"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm hover:bg-accent"
                                title="Download ledger Excel"
                            >
                                <FileSpreadsheet class="size-4" />
                                Excel
                            </a>
                            <button type="button" class="inline-flex h-10 items-center justify-center rounded-md border px-3" @click="closeLedger">
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-auto p-4">
                        <div v-if="ledgerLoading" class="flex min-h-56 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                            Loading ledger...
                        </div>

                        <div v-else-if="ledgerRows.length" class="overflow-hidden rounded-md border">
                            <div class="max-h-[68vh] overflow-auto">
                                <table class="w-full min-w-[2040px] table-fixed text-sm">
                                    <thead class="sticky top-0 z-10 border-b bg-card text-left text-xs font-medium text-muted-foreground">
                                        <tr>
                                            <th class="w-[120px] px-3 py-2 font-medium">Month</th>
                                            <th class="w-[70px] px-3 py-2 font-medium">Days</th>
                                            <th class="w-[70px] px-3 py-2 font-medium">Absent</th>
                                            <th class="w-[90px] px-3 py-2 font-medium">Per Day</th>
                                            <th class="w-[100px] px-3 py-2 font-medium">Salary</th>
                                            <th class="w-[110px] px-3 py-2 font-medium">Absent Ded.</th>
                                            <th class="w-[80px] px-3 py-2 font-medium">OT Hrs</th>
                                            <th class="w-[100px] px-3 py-2 font-medium">OT Salary</th>
                                            <th class="w-[110px] px-3 py-2 font-medium">New Total</th>
                                            <th class="w-[130px] px-3 py-2 font-medium">Bonus</th>
                                            <th class="w-[130px] px-3 py-2 font-medium">Pr. Balance</th>
                                            <th class="w-[120px] px-3 py-2 font-medium">Total Balance</th>
                                            <th class="w-[130px] px-3 py-2 font-medium">Deduction</th>
                                            <th class="w-[130px] px-3 py-2 font-medium">Paid Cash</th>
                                            <th class="w-[110px] px-3 py-2 font-medium">Balance</th>
                                            <th class="w-[220px] px-3 py-2 font-medium">Remarks</th>
                                            <th class="w-[76px] px-3 py-2 text-right font-medium">Save</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in ledgerRows" :key="ledgerKey(row)" class="border-b last:border-b-0">
                                            <td class="px-3 py-3 font-medium">{{ row.monthLabel }}</td>
                                            <td class="px-3 py-3">{{ row.presentDays }}</td>
                                            <td class="px-3 py-3">{{ row.absentDays }}</td>
                                            <td class="px-3 py-3">{{ money(row.dailySalary) }}</td>
                                            <td class="px-3 py-3">{{ money(row.basicSalary) }}</td>
                                            <td class="px-3 py-3">{{ money(row.absenceDeduction) }}</td>
                                            <td class="px-3 py-3">{{ row.overtimeHours }}</td>
                                            <td class="px-3 py-3">{{ money(row.overtimeAmount) }}</td>
                                            <td class="px-3 py-3 font-semibold">{{ money(row.totalSalary) }}</td>
                                            <td class="px-3 py-3">
                                                <input v-model="ledgerAdjustments[ledgerKey(row)].bonusExtra" type="number" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input
                                                    v-model="ledgerAdjustments[ledgerKey(row)].previousBalance"
                                                    type="number"
                                                    step="0.01"
                                                    class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm disabled:bg-muted/50"
                                                    :disabled="!ledgerAdjustments[ledgerKey(row)].previousBalanceOverridden"
                                                />
                                                <label class="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                                                    <input
                                                        v-model="ledgerAdjustments[ledgerKey(row)].previousBalanceOverridden"
                                                        type="checkbox"
                                                        class="size-3"
                                                        @change="syncLedgerPreviousBalanceMode(row)"
                                                    />
                                                    Manual
                                                </label>
                                            </td>
                                            <td class="px-3 py-3 font-medium">{{ money(liveLedgerTotalBalance(row)) }}</td>
                                            <td class="px-3 py-3">
                                                <input v-model="ledgerAdjustments[ledgerKey(row)].deduction" type="number" min="0" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input v-model="ledgerAdjustments[ledgerKey(row)].paidByCash" type="number" min="0" step="0.01" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
                                            </td>
                                            <td class="px-3 py-3 font-semibold">{{ money(liveLedgerBalance(row)) }}</td>
                                            <td class="px-3 py-3">
                                                <input v-model="ledgerAdjustments[ledgerKey(row)].remarks" type="text" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm" placeholder="Remarks" />
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <button
                                                    type="button"
                                                    class="inline-flex size-9 items-center justify-center rounded-md border text-sm hover:bg-accent disabled:opacity-60"
                                                    :disabled="ledgerSavingKey === ledgerKey(row)"
                                                    @click="saveLedgerAdjustment(row)"
                                                >
                                                    <Save class="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-else class="flex min-h-56 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                            No ledger records found for the selected range.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
