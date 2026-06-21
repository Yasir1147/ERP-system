<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Employee {
    id: number;
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

const employeeLabel = (employee: Employee) => `${employee.name} - ${employee.profession} (${props.employeeTypes[employee.type]})`;

const submittedByLabel = (leave: EmployeeLeave) => {
    if (!leave.createdBy) {
        return '-';
    }

    return leave.createdByRole === 'admin' ? `${leave.createdBy} (Admin)` : leave.createdBy;
};

const sourceLabel = (leave: EmployeeLeave) => (leave.source === 'daily_leave' ? 'Daily Leave' : leave.durationDays > 3 ? 'Long Leave' : 'Leave Range');

const sourceClass = (leave: EmployeeLeave) =>
    leave.source === 'daily_leave'
        ? 'border-sky-600/30 bg-sky-600/10 text-sky-700'
        : leave.durationDays > 3
          ? 'border-amber-600/30 bg-amber-600/10 text-amber-700'
          : 'border-muted-foreground/30 bg-muted text-muted-foreground';

const filteredLeaves = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.leaves;
    }

    return props.leaves.filter((leave) =>
        [
            leave.employeeName,
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
    );
});

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

    editForm.put(`/employee-leaves/${leave.id}`, {
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

    router.delete(`/employee-leaves/${leave.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Employee Leaves" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Employee Leaves</h1>
                <p class="mt-1 text-sm text-muted-foreground">Manage long leave ranges without creating daily attendance records.</p>
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
                        <p class="text-sm text-muted-foreground">{{ filteredLeaves.length }} of {{ leaves.length }} leave records, including daily and long leaves</p>
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
                    <table class="w-full min-w-[1120px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[22%] px-4 py-3 font-medium">Employee</th>
                                <th class="w-[12%] px-4 py-3 font-medium">Type</th>
                                <th class="w-[12%] px-4 py-3 font-medium">Start</th>
                                <th class="w-[12%] px-4 py-3 font-medium">End</th>
                                <th class="w-[20%] px-4 py-3 font-medium">Reason</th>
                                <th class="w-[12%] px-4 py-3 font-medium">Created By</th>
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
                                        <p class="truncate font-medium">{{ leave.employeeName }}</p>
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
                                    <Input v-if="editingLeaveId === leave.id" v-model="editForm.end_date" type="date" />
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
