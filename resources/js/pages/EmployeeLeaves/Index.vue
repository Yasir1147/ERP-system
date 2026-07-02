<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SortableHeader from '@/components/SortableHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, ShieldCheck, ShieldMinus, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
    code: string | null;
    name: string;
    profession: string;
    type: string;
    status: string;
}

interface EmployeeLeave {
    id: number | string;
    source: string;
    canEdit: boolean;
    employeeId: number;
    employeeCode: string | null;
    employeeName: string;
    employeeProfession: string;
    employeeType: string;
    employeeStatus: string;
    startDate: string;
    endDate: string;
    startDateLabel: string;
    endDateLabel: string;
    durationDays: number;
    reason: string | null;
    createdBy: string | null;
    createdByRole: string | null;
    payrollDeductionStatus: string;
    payrollDeductDays: number;
    payrollDeductionMonth: string | null;
    payrollDeductionMonthLabel: string | null;
    payrollDeductionNote: string | null;
    payrollDeductionReviewedBy: string | null;
    payrollDeductionReviewedAtLabel: string | null;
}

const props = defineProps<{
    employees: Employee[];
    employeeTypes: Record<string, string>;
    leaves: EmployeeLeave[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Leaves',
        href: '/employee-leaves',
    },
];

const editingLeaveId = ref<number | null>(null);
const search = ref('');
type SortKey = 'employee' | 'type' | 'start' | 'end' | 'duration' | 'created_by' | 'deduction_status';
const sortKey = ref<SortKey>('start');
const sortDirection = ref<'asc' | 'desc'>('desc');
const currentMonth = new Date().toISOString().slice(0, 7);
const leaveKey = (leave: EmployeeLeave) => `${leave.source}:${leave.id}`;
const deductionDays = ref<Record<string, string>>(
    Object.fromEntries(props.leaves.map((leave) => [leaveKey(leave), leave.payrollDeductDays ? String(leave.payrollDeductDays) : String(Math.min(leave.durationDays, 1))])),
);
const deductionMonths = ref<Record<string, string>>(
    Object.fromEntries(props.leaves.map((leave) => [leaveKey(leave), leave.payrollDeductionMonth || leave.startDate.slice(0, 7) || currentMonth])),
);
const deductionNotes = ref<Record<string, string>>(Object.fromEntries(props.leaves.map((leave) => [leaveKey(leave), leave.payrollDeductionNote || ''])));

const createForm = useForm({
    employee_id: '',
    start_date: '',
    end_date: '',
    reason: '',
});

const editForm = useForm({
    employee_id: '',
    start_date: '',
    end_date: '',
    reason: '',
});

const employeeLabel = (employee: Employee) => `${employee.code ? `${employee.code} - ` : ''}${employee.name} - ${employee.profession} (${props.employeeTypes[employee.type]})`;
const leaveEmployeeLabel = (leave: EmployeeLeave) => (leave.employeeCode ? `${leave.employeeCode} - ${leave.employeeName}` : leave.employeeName);

const submittedByLabel = (leave: EmployeeLeave) => {
    if (!leave.createdBy) {
        return '-';
    }

    return leave.createdByRole === 'admin' ? `${leave.createdBy} (Admin)` : leave.createdBy;
};

const sourceLabel = (leave: EmployeeLeave) => {
    if (leave.source === 'daily_absent') {
        return 'Daily Absent';
    }

    return leave.source === 'daily_leave' ? 'Daily Leave' : leave.durationDays > 3 ? 'Long Leave' : 'Leave Range';
};

const sourceClass = (leave: EmployeeLeave) =>
    leave.source === 'daily_absent'
        ? 'border-red-600/30 bg-red-600/10 text-red-700'
        : leave.source === 'daily_leave'
        ? 'border-sky-600/30 bg-sky-600/10 text-sky-700'
        : leave.durationDays > 3
          ? 'border-amber-600/30 bg-amber-600/10 text-amber-700'
          : 'border-muted-foreground/30 bg-muted text-muted-foreground';

const filteredLeaves = computed(() => {
    const query = search.value.trim().toLowerCase();

    const leaves = query
        ? props.leaves.filter((leave) =>
              [
                  leave.employeeName,
                  leave.employeeCode,
                  leave.employeeProfession,
                  props.employeeTypes[leave.employeeType],
                  leave.reason,
                  sourceLabel(leave),
                  leave.startDateLabel,
                  leave.endDateLabel,
                  leave.createdBy,
              ]
                  .filter(Boolean)
                  .some((value) => String(value).toLowerCase().includes(query)),
          )
        : props.leaves;

    return [...leaves].sort((first, second) => {
        const valueFor = (leave: EmployeeLeave) => {
            if (sortKey.value === 'employee') return leave.employeeCode || leave.employeeName;
            if (sortKey.value === 'type') return sourceLabel(leave);
            if (sortKey.value === 'start') return leave.startDate;
            if (sortKey.value === 'end') return leave.endDate;
            if (sortKey.value === 'duration') return leave.durationDays;
            if (sortKey.value === 'created_by') return submittedByLabel(leave);
            return deductionStatusLabel(leave);
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

const sortLeaves = (key: string) => {
    const nextKey = key as SortKey;

    if (sortKey.value === nextKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextKey;
    sortDirection.value = nextKey === 'start' || nextKey === 'end' ? 'desc' : 'asc';
};

const createLeave = () => {
    createForm.post('/employee-leaves', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const startEditing = (leave: EmployeeLeave) => {
    if (!leave.canEdit || typeof leave.id !== 'number') {
        return;
    }

    editingLeaveId.value = leave.id;
    editForm.clearErrors();
    editForm.employee_id = String(leave.employeeId);
    editForm.start_date = leave.startDate;
    editForm.end_date = leave.endDate;
    editForm.reason = leave.reason || '';
};

const cancelEditing = () => {
    editingLeaveId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const updateLeave = (leave: EmployeeLeave) => {
    if (!leave.canEdit || typeof leave.id !== 'number') {
        return;
    }

    const url = leave.source === 'daily_leave' ? `/employee-leaves/attendance/${leave.id}` : `/employee-leaves/${leave.id}`;

    editForm.put(url, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const deleteLeave = (leave: EmployeeLeave) => {
    if (!leave.canEdit || typeof leave.id !== 'number') {
        return;
    }

    if (!confirm(`Delete leave for ${leave.employeeName}?`)) {
        return;
    }

    const url = leave.source === 'daily_leave' ? `/employee-leaves/attendance/${leave.id}` : `/employee-leaves/${leave.id}`;

    router.delete(url, {
        preserveScroll: true,
    });
};

const canReviewDeduction = (leave: EmployeeLeave) => leave.source === 'long_leave' || leave.source === 'daily_leave';

const deductionStatusLabel = (leave: EmployeeLeave) => {
    if (leave.source === 'daily_absent') {
        return 'Attendance absent';
    }

    if (leave.payrollDeductionStatus === 'applied') {
        return `Applied: ${leave.payrollDeductDays} of ${leave.durationDays} day${leave.durationDays === 1 ? '' : 's'} in ${leave.payrollDeductionMonthLabel}`;
    }

    if (leave.payrollDeductionStatus === 'waived') {
        return 'Waived';
    }

    return 'Pending review';
};

const applyDeduction = (leave: EmployeeLeave) => {
    if (!canReviewDeduction(leave) || typeof leave.id !== 'number') {
        return;
    }

    const url = leave.source === 'daily_leave' ? `/employee-leaves/attendance/${leave.id}/deduction` : `/employee-leaves/${leave.id}/deduction`;
    const key = leaveKey(leave);

    router.put(
        url,
        {
            payroll_deduct_days: deductionDays.value[key],
            payroll_deduction_month: deductionMonths.value[key],
            payroll_deduction_note: deductionNotes.value[key],
        },
        { preserveScroll: true },
    );
};

const waiveDeduction = (leave: EmployeeLeave) => {
    if (!canReviewDeduction(leave) || typeof leave.id !== 'number') {
        return;
    }

    const url = leave.source === 'daily_leave' ? `/employee-leaves/attendance/${leave.id}/deduction/waive` : `/employee-leaves/${leave.id}/deduction/waive`;
    const key = leaveKey(leave);

    router.put(
        url,
        {
            payroll_deduction_note: deductionNotes.value[key],
        },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Employee Leaves" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Employee Leaves</h1>
                <p class="mt-1 text-sm text-muted-foreground">Manage leave records, daily absents, and admin payroll deduction decisions.</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="createLeave">
                <div class="grid gap-4 xl:grid-cols-[minmax(220px,1fr)_160px_160px_minmax(240px,1fr)_auto] xl:items-start">
                    <div class="grid min-w-0 gap-2">
                        <Label for="leave-employee">Employee</Label>
                        <select
                            id="leave-employee"
                            v-model="createForm.employee_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">Select employee</option>
                            <option v-for="employee in employees" :key="employee.id" :value="String(employee.id)">
                                {{ employeeLabel(employee) }}
                            </option>
                        </select>
                        <InputError :message="createForm.errors.employee_id" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="leave-start">Start Date</Label>
                        <Input id="leave-start" v-model="createForm.start_date" type="date" />
                        <InputError :message="createForm.errors.start_date" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="leave-end">End Date</Label>
                        <Input id="leave-end" v-model="createForm.end_date" type="date" />
                        <InputError :message="createForm.errors.end_date" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="leave-reason">Reason</Label>
                        <Input id="leave-reason" v-model="createForm.reason" type="text" placeholder="Optional reason" />
                        <InputError :message="createForm.errors.reason" />
                    </div>
                    <Button class="mt-0 whitespace-nowrap xl:mt-8" type="submit" :disabled="createForm.processing">
                        <Plus class="size-4" />
                        Add Leave
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Leave List</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredLeaves.length }} of {{ leaves.length }} records, including daily leaves, long leaves, and absents</p>
                    </div>
                    <div class="relative w-full md:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search leaves" />
                    </div>
                </div>

                <div v-if="leaves.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No leave records added yet.
                </div>

                <div v-else-if="filteredLeaves.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No leave records match your search.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1540px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[18%] px-4 py-3 font-medium">
                                    <SortableHeader label="Employee" column="employee" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortLeaves" />
                                </th>
                                <th class="w-[10%] px-4 py-3 font-medium">
                                    <SortableHeader label="Type" column="type" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortLeaves" />
                                </th>
                                <th class="w-[10%] px-4 py-3 font-medium">
                                    <SortableHeader label="Start" column="start" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortLeaves" />
                                </th>
                                <th class="w-[10%] px-4 py-3 font-medium">
                                    <SortableHeader label="End" column="end" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortLeaves" />
                                </th>
                                <th class="w-[14%] px-4 py-3 font-medium">Reason</th>
                                <th class="w-[11%] px-4 py-3 font-medium">
                                    <SortableHeader label="Created By" column="created_by" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortLeaves" />
                                </th>
                                <th class="w-[280px] px-4 py-3 font-medium">
                                    <SortableHeader label="Payroll Deduction" column="deduction_status" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortLeaves" />
                                </th>
                                <th class="w-[120px] px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="leave in filteredLeaves" :key="leave.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <select
                                        v-if="editingLeaveId === leave.id"
                                        v-model="editForm.employee_id"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="employee in employees" :key="employee.id" :value="String(employee.id)">
                                            {{ employeeLabel(employee) }}
                                        </option>
                                    </select>
                                    <div v-else class="min-w-0">
                                        <p class="truncate font-medium">{{ leaveEmployeeLabel(leave) }}</p>
                                        <p class="truncate text-xs text-muted-foreground">{{ leave.employeeProfession }} - {{ employeeTypes[leave.employeeType] }}</p>
                                    </div>
                                    <InputError v-if="editingLeaveId === leave.id" :message="editForm.errors.employee_id" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium" :class="sourceClass(leave)">
                                        {{ sourceLabel(leave) }}
                                    </span>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ leave.durationDays }} day{{ leave.durationDays === 1 ? '' : 's' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingLeaveId === leave.id" v-model="editForm.start_date" type="date" />
                                    <span v-else>{{ leave.startDateLabel }}</span>
                                    <InputError v-if="editingLeaveId === leave.id" :message="editForm.errors.start_date" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingLeaveId === leave.id" v-model="editForm.end_date" type="date" :disabled="leave.source === 'daily_leave'" />
                                    <span v-else>{{ leave.endDateLabel }}</span>
                                    <InputError v-if="editingLeaveId === leave.id" :message="editForm.errors.end_date" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingLeaveId === leave.id" v-model="editForm.reason" type="text" />
                                    <span v-else class="block truncate text-muted-foreground">{{ leave.reason || '-' }}</span>
                                    <InputError v-if="editingLeaveId === leave.id" :message="editForm.errors.reason" class="mt-2" />
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ submittedByLabel(leave) }}</td>
                                <td class="px-4 py-3">
                                    <div v-if="canReviewDeduction(leave)" class="space-y-2">
                                        <p
                                            class="inline-flex rounded-full border px-2 py-1 text-xs font-medium"
                                            :class="
                                                leave.payrollDeductionStatus === 'applied'
                                                    ? 'border-green-600/30 bg-green-600/10 text-green-700'
                                                    : leave.payrollDeductionStatus === 'waived'
                                                      ? 'border-slate-500/30 bg-slate-500/10 text-slate-600'
                                                      : 'border-amber-600/30 bg-amber-600/10 text-amber-700'
                                            "
                                        >
                                            {{ deductionStatusLabel(leave) }}
                                        </p>
                                        <div class="grid grid-cols-[74px_1fr] gap-2">
                                            <Input v-model="deductionDays[leaveKey(leave)]" type="number" min="1" :max="leave.durationDays" class="h-9" />
                                            <Input v-model="deductionMonths[leaveKey(leave)]" type="month" class="h-9" />
                                        </div>
                                        <Input v-model="deductionNotes[leaveKey(leave)]" type="text" class="h-9" placeholder="Admin note" />
                                        <p v-if="leave.payrollDeductionReviewedBy" class="text-xs text-muted-foreground">
                                            Reviewed by {{ leave.payrollDeductionReviewedBy }}<template v-if="leave.payrollDeductionReviewedAtLabel"> - {{ leave.payrollDeductionReviewedAtLabel }}</template>
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <Button size="sm" type="button" @click="applyDeduction(leave)">
                                                <ShieldMinus class="size-4" />
                                                Apply as Absent
                                            </Button>
                                            <Button size="sm" type="button" variant="outline" @click="waiveDeduction(leave)">
                                                <ShieldCheck class="size-4" />
                                                Waive
                                            </Button>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-muted-foreground">
                                        {{ deductionStatusLabel(leave) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <template v-if="editingLeaveId === leave.id">
                                            <Button size="icon" type="button" :disabled="editForm.processing" @click="updateLeave(leave)">
                                                <Check class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="outline" @click="cancelEditing">
                                                <X class="size-4" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <Button v-if="leave.canEdit" size="icon" type="button" variant="outline" @click="startEditing(leave)">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button v-if="leave.canEdit" size="icon" type="button" variant="destructive" @click="deleteLeave(leave)">
                                                <Trash2 class="size-4" />
                                            </Button>
                                            <span v-if="!leave.canEdit" class="text-xs text-muted-foreground">Attendance record</span>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
