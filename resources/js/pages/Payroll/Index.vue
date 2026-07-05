<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SortableHeader from '@/components/SortableHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Save, Search, ShieldMinus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PayrollSetting {
    dailySalary: string;
    monthlySalary: string;
    salaryRule: string;
    standardHoursPerDay: number;
    isOvertimeEnabled: boolean;
}

interface Employee {
    id: number;
    code: string | null;
    name: string;
    profession: string;
    type: string;
    status: string;
    label: string;
    payrollSetting: PayrollSetting;
}

interface TypeOption {
    value: string;
    label: string;
}

const props = defineProps<{
    employees: Employee[];
    filters: {
        type: string;
    };
    typeOptions: TypeOption[];
    employeeTypes: Record<string, string>;
    salaryRules: Record<string, string>;
    absenceDeductionSettings: {
        enabled: boolean;
        apply_to: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payroll',
        href: '/payroll',
    },
];

const filterType = ref(props.filters.type);
const editingEmployeeId = ref<number | null>(null);
const search = ref('');
type SortKey = 'employee' | 'daily_salary' | 'monthly_salary' | 'salary_rule' | 'hours_per_day' | 'overtime';
const sortKey = ref<SortKey>('employee');
const sortDirection = ref<'asc' | 'desc'>('asc');

const settingForm = useForm({
    daily_salary: '0.00',
    monthly_salary: '',
    salary_rule: 'present_days',
    standard_hours_per_day: 8,
    is_overtime_enabled: true,
    type: props.filters.type,
});

const absenceRuleForm = useForm({
    absence_deduction_enabled: props.absenceDeductionSettings.enabled,
    absence_deduction_apply_to: props.absenceDeductionSettings.apply_to,
    type: props.filters.type,
});

const sortValue = (employee: Employee, key: SortKey) => {
    if (key === 'daily_salary') {
        return displayDailySalary(employee);
    }

    if (key === 'monthly_salary') {
        return Number(employee.payrollSetting.monthlySalary || 0);
    }

    if (key === 'salary_rule') {
        return salaryRules[employee.payrollSetting.salaryRule] ?? employee.payrollSetting.salaryRule;
    }

    if (key === 'hours_per_day') {
        return Number(employee.payrollSetting.standardHoursPerDay);
    }

    if (key === 'overtime') {
        return employee.payrollSetting.isOvertimeEnabled ? 1 : 0;
    }

    return employee.code ? `${employee.code.padStart(12, '0')} ${employee.name}` : employee.name;
};

const displayDailySalary = (employee: Employee) => {
    if (employee.payrollSetting.salaryRule === 'fixed_30_days' && employee.payrollSetting.monthlySalary) {
        return Number(employee.payrollSetting.monthlySalary) / 30;
    }

    return Number(employee.payrollSetting.dailySalary);
};

const sortEmployees = (key: string) => {
    const nextKey = key as SortKey;

    if (sortKey.value === nextKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextKey;
    sortDirection.value = 'asc';
};

const employeeOptions = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.employees
        .filter((employee) => filterType.value === 'all' || employee.type === filterType.value)
        .filter((employee) => {
            if (!query) {
                return true;
            }

            return [employee.code, employee.name, employee.profession, props.employeeTypes[employee.type], employee.label]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query));
        })
        .sort((first, second) => {
            const firstValue = sortValue(first, sortKey.value);
            const secondValue = sortValue(second, sortKey.value);

            const comparison =
                typeof firstValue === 'number' && typeof secondValue === 'number'
                    ? firstValue - secondValue
                    : String(firstValue).localeCompare(String(secondValue), undefined, { numeric: true, sensitivity: 'base' });

            return sortDirection.value === 'asc' ? comparison : -comparison;
        });
});

const money = (value: number) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const applyFilters = () => {
    router.get('/payroll', { type: filterType.value }, { preserveScroll: true, preserveState: false });
};

const startEditing = (employee: Employee) => {
    editingEmployeeId.value = employee.id;
    settingForm.clearErrors();
    settingForm.daily_salary = employee.payrollSetting.dailySalary;
    settingForm.monthly_salary = employee.payrollSetting.monthlySalary;
    settingForm.salary_rule = employee.payrollSetting.salaryRule;
    settingForm.standard_hours_per_day = employee.payrollSetting.standardHoursPerDay;
    settingForm.is_overtime_enabled = employee.payrollSetting.isOvertimeEnabled;
    settingForm.type = filterType.value;
};

const cancelEditing = () => {
    editingEmployeeId.value = null;
    settingForm.reset();
    settingForm.clearErrors();
};

const updateSetting = (employee: Employee) => {
    settingForm.type = filterType.value;

    settingForm.put(`/payroll/settings/${employee.id}`, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const updateAbsenceRule = () => {
    absenceRuleForm.type = filterType.value;

    absenceRuleForm.put('/payroll/absence-rule', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Payroll" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Payroll</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Manage employee daily salary, payroll rule, and overtime settings.</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-[220px_auto]">
                    <select
                        v-model="filterType"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <button type="button" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" @click="applyFilters">
                        Filter
                    </button>
                </div>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="updateAbsenceRule">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <ShieldMinus class="size-5 text-amber-600" />
                            <h2 class="text-base font-medium">Absence Deduction Rule</h2>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            When enabled, absent days deduct one daily salary from fixed 30 days employees. Present-days employees are already paid by attendance days.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[minmax(220px,260px)_auto_auto] sm:items-end">
                        <label class="grid gap-2 text-sm font-medium">
                            Apply Rule
                            <select
                                v-model="absenceRuleForm.absence_deduction_apply_to"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="fixed_only">Fixed 30 Days employees only</option>
                            </select>
                        </label>
                        <label class="flex h-10 items-center gap-2 text-sm font-medium">
                            <input v-model="absenceRuleForm.absence_deduction_enabled" type="checkbox" class="size-4 rounded border-input" />
                            Enabled
                        </label>
                        <Button type="submit" :disabled="absenceRuleForm.processing">
                            <Save class="size-4" />
                            Save Rule
                        </Button>
                    </div>
                </div>
                <InputError :message="absenceRuleForm.errors.absence_deduction_enabled || absenceRuleForm.errors.absence_deduction_apply_to" class="mt-2" />
            </form>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Salary Settings</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ employeeOptions.length }} of {{ employees.length }} employees. Set per day salary, payroll rule, and overtime settings.</p>
                    </div>
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search by code, name, profession, or type" />
                    </div>
                </div>
                <div class="mt-4 overflow-hidden rounded-md border">
                    <div class="grid min-w-[1160px] grid-cols-[1fr_0.7fr_0.7fr_0.7fr_0.65fr_0.6fr_120px] border-b px-3 py-2 text-xs font-medium text-muted-foreground">
                        <SortableHeader label="Employee" column="employee" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortEmployees" />
                        <SortableHeader label="Daily Salary" column="daily_salary" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortEmployees" />
                        <SortableHeader label="Monthly Salary" column="monthly_salary" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortEmployees" />
                        <SortableHeader label="Salary Rule" column="salary_rule" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortEmployees" />
                        <SortableHeader label="Hours / Day" column="hours_per_day" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortEmployees" />
                        <SortableHeader label="Overtime" column="overtime" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortEmployees" />
                        <span class="text-right">Actions</span>
                    </div>
                    <div class="max-h-[640px] overflow-auto">
                        <div v-for="employee in employeeOptions" :key="employee.id" class="grid min-w-[1160px] grid-cols-[1fr_0.7fr_0.7fr_0.7fr_0.65fr_0.6fr_120px] items-start gap-3 border-b px-3 py-3 text-sm last:border-b-0">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ employee.code ? `${employee.code} - ${employee.name}` : employee.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ employee.profession }} - {{ employeeTypes[employee.type] }}</p>
                            </div>
                            <div>
                                <Input v-if="editingEmployeeId === employee.id" v-model="settingForm.daily_salary" type="number" min="0" step="0.01" />
                                <span v-else>{{ money(displayDailySalary(employee)) }}</span>
                                <InputError v-if="editingEmployeeId === employee.id" :message="settingForm.errors.daily_salary" class="mt-2" />
                            </div>
                            <div>
                                <template v-if="editingEmployeeId === employee.id">
                                    <Input
                                        v-model="settingForm.monthly_salary"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="settingForm.salary_rule !== 'fixed_30_days'"
                                        :placeholder="settingForm.salary_rule === 'fixed_30_days' ? 'Monthly amount' : 'Present days only'"
                                    />
                                    <p v-if="settingForm.salary_rule === 'fixed_30_days'" class="mt-1 text-[11px] leading-tight text-muted-foreground">Used as exact fixed salary.</p>
                                </template>
                                <span v-else>{{ employee.payrollSetting.salaryRule === 'fixed_30_days' && employee.payrollSetting.monthlySalary ? money(Number(employee.payrollSetting.monthlySalary)) : '-' }}</span>
                                <InputError v-if="editingEmployeeId === employee.id" :message="settingForm.errors.monthly_salary" class="mt-2" />
                            </div>
                            <div>
                                <select
                                    v-if="editingEmployeeId === employee.id"
                                    v-model="settingForm.salary_rule"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option v-for="(label, rule) in salaryRules" :key="rule" :value="rule">{{ label }}</option>
                                </select>
                                <span v-else>{{ salaryRules[employee.payrollSetting.salaryRule] }}</span>
                                <InputError v-if="editingEmployeeId === employee.id" :message="settingForm.errors.salary_rule" class="mt-2" />
                            </div>
                            <div>
                                <Input v-if="editingEmployeeId === employee.id" v-model="settingForm.standard_hours_per_day" type="number" min="1" max="24" />
                                <span v-else>{{ employee.payrollSetting.standardHoursPerDay }}</span>
                                <InputError v-if="editingEmployeeId === employee.id" :message="settingForm.errors.standard_hours_per_day" class="mt-2" />
                            </div>
                            <div>
                                <label v-if="editingEmployeeId === employee.id" class="flex h-10 items-center gap-2">
                                    <input v-model="settingForm.is_overtime_enabled" type="checkbox" class="size-4 rounded border-input" />
                                    <span>Enabled</span>
                                </label>
                                <span v-else>{{ employee.payrollSetting.isOvertimeEnabled ? 'Enabled' : 'Disabled' }}</span>
                                <InputError v-if="editingEmployeeId === employee.id" :message="settingForm.errors.is_overtime_enabled" class="mt-2" />
                            </div>
                            <div class="flex justify-end gap-2">
                                <template v-if="editingEmployeeId === employee.id">
                                    <Button size="icon" type="button" :disabled="settingForm.processing" @click="updateSetting(employee)">
                                        <Save class="size-4" />
                                    </Button>
                                    <Button size="icon" type="button" variant="outline" @click="cancelEditing">
                                        <X class="size-4" />
                                    </Button>
                                </template>
                                <Button v-else size="icon" type="button" variant="outline" @click="startEditing(employee)">
                                    <Pencil class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
