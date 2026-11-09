<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { overtimeLabel } from '@/lib/overtime';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface Costs {
    basicCost: number;
    overtimeCost: number;
    totalCost: number;
    overtimeHours: number;
    labourDays: number;
}
interface Employee extends Costs {
    employeeId: number;
    employeeCode: string | null;
    employeeName: string;
    profession: string | null;
    entries: number;
    workedDays: number;
    costShare: number;
    missingPayroll: boolean;
    submittedBy: string;
}
interface RecordRow extends Costs {
    id: number;
    employeeCode: string | null;
    employeeName: string;
    date: string;
    mainProject: string | null;
    overtimeProject: string | null;
    dailySalary: number;
    submittedBy: string;
}
interface History {
    filters: { from: string; to: string };
    totals: Costs & { workedDays: number; persons: number; missingPayroll: number };
    employees: Employee[];
    records: RecordRow[];
}
const props = defineProps<{ open: boolean; project: { projectId: number; name: string; code: string | null } | null; from: string; to: string }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const history = ref<History | null>(null);
const fromDate = ref('');
const toDate = ref('');
const loading = ref(false);
const error = ref('');
const tab = ref('summary');
let pending: AbortController | null = null;
async function load() {
    pending?.abort();
    if (!props.project || !props.open) return;
    if (!fromDate.value || !toDate.value || toDate.value < fromDate.value) {
        error.value = 'Select a valid date range.';
        return;
    }
    const controller = new AbortController();
    pending = controller;
    loading.value = true;
    error.value = '';
    history.value = null;
    try {
        const params = new URLSearchParams({ from: fromDate.value, to: toDate.value });
        const response = await fetch(`/attendance/project-summary/${props.project.projectId}/history?${params}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('Employee history could not be loaded. Please try again.');
        const result: History = await response.json();
        if (!controller.signal.aborted) history.value = result;
    } catch (cause) {
        if (!controller.signal.aborted) error.value = cause instanceof Error ? cause.message : 'Employee history could not be loaded.';
    } finally {
        if (pending === controller) loading.value = false;
    }
}
watch(
    () => [props.open, props.project?.projectId],
    () => {
        pending?.abort();
        history.value = null;
        if (props.open && props.project) {
            fromDate.value = props.from;
            toDate.value = props.to;
            tab.value = 'summary';
            void load();
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => pending?.abort());
const exportQuery = computed(() => new URLSearchParams({ from: history.value?.filters.from ?? '', to: history.value?.filters.to ?? '' }).toString());
const baseUrl = computed(() => `/attendance/project-summary/${props.project?.projectId}/history`);
const money = (value: number) => new Intl.NumberFormat('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogScrollContent class="w-[96vw] max-w-[96vw]">
            <DialogHeader
                ><DialogTitle>Project Employee History</DialogTitle
                ><DialogDescription
                    >{{ project?.code ? `${project.code} - ` : '' }}{{ project?.name }} · Salary and overtime cost in AED</DialogDescription
                ></DialogHeader
            >
            <form class="flex flex-wrap items-end gap-2 pr-4" @submit.prevent="load">
                <label class="grid gap-1 text-sm">From<Input v-model="fromDate" type="date" required /></label
                ><label class="grid gap-1 text-sm">To<Input v-model="toDate" type="date" :min="fromDate" required /></label>
                <Button type="submit" :disabled="loading">Filter</Button>
                <template v-if="history && !loading"
                    ><Button as-child variant="outline"><a :href="`${baseUrl}/export?${exportQuery}`">Excel</a></Button
                    ><Button as-child variant="outline"
                        ><a :href="`${baseUrl}/print?${exportQuery}`" target="_blank" rel="noreferrer">Print / PDF</a></Button
                    ></template
                >
            </form>
            <p v-if="loading" role="status" class="p-8 text-center text-muted-foreground">Loading employee history...</p>
            <div v-else-if="error" role="alert" class="rounded-lg border border-red-200 p-4 text-red-700">
                {{ error }} <Button type="button" variant="outline" @click="load">Retry</Button>
            </div>
            <template v-else-if="history">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
                    <div
                        v-for="stat in [
                            { label: 'Persons', value: history.totals.persons },
                            { label: 'Worked Days', value: history.totals.workedDays },
                            { label: 'Labour Days', value: history.totals.labourDays },
                            { label: 'Overtime', value: overtimeLabel(history.totals.overtimeHours) },
                            { label: 'Salary Cost', value: money(history.totals.basicCost) },
                            { label: 'OT Cost', value: money(history.totals.overtimeCost) },
                            { label: 'Total Cost', value: money(history.totals.totalCost) },
                        ]"
                        :key="stat.label"
                        class="rounded-lg border p-3"
                    >
                        <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
                        <p class="mt-2 font-semibold">{{ stat.value }}</p>
                    </div>
                </div>
                <p v-if="history.totals.missingPayroll" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">
                    Incomplete cost: {{ history.totals.missingPayroll }} person(s) have no payroll settings and are costed at zero.
                </p>
                <div class="flex gap-2">
                    <Button type="button" :variant="tab === 'summary' ? 'default' : 'outline'" @click="tab = 'summary'">Employee Summary</Button
                    ><Button type="button" :variant="tab === 'daily' ? 'default' : 'outline'" @click="tab = 'daily'">Daily Attendance</Button>
                </div>
                <div class="max-h-[55vh] overflow-auto rounded-lg border">
                    <table v-if="tab === 'summary'" class="w-full whitespace-nowrap text-sm">
                        <thead class="sticky top-0 bg-muted">
                            <tr>
                                <th
                                    v-for="heading in [
                                        'Code',
                                        'Employee',
                                        'Profession',
                                        'Entries',
                                        'Worked Days',
                                        'Labour Days',
                                        'OT Hours',
                                        'Salary Cost',
                                        'OT Cost',
                                        'Total Cost',
                                        'Share',
                                        'Submitted By',
                                    ]"
                                    :key="heading"
                                    class="px-3 py-3 text-left"
                                >
                                    {{ heading }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="employee in history.employees" :key="employee.employeeId" class="border-t">
                                <td class="px-3 py-3">{{ employee.employeeCode || '–' }}</td>
                                <td class="px-3 py-3 font-medium">
                                    {{ employee.employeeName }}
                                    <p v-if="employee.missingPayroll" class="text-xs font-normal text-red-600">Missing payroll setting</p>
                                </td>
                                <td class="px-3 py-3">{{ employee.profession || '–' }}</td>
                                <td class="px-3 py-3">{{ employee.entries }}</td>
                                <td class="px-3 py-3">{{ employee.workedDays }}</td>
                                <td class="px-3 py-3">{{ employee.labourDays }}</td>
                                <td class="px-3 py-3">{{ overtimeLabel(employee.overtimeHours) }}</td>
                                <td class="px-3 py-3">{{ money(employee.basicCost) }}</td>
                                <td class="px-3 py-3">{{ money(employee.overtimeCost) }}</td>
                                <td class="px-3 py-3 font-semibold">{{ money(employee.totalCost) }}</td>
                                <td class="px-3 py-3">{{ employee.costShare }}%</td>
                                <td class="px-3 py-3">{{ employee.submittedBy }}</td>
                            </tr>
                            <tr v-if="!history.employees.length">
                                <td colspan="12" class="p-8 text-center text-muted-foreground">No employee attendance in this date range.</td>
                            </tr>
                        </tbody>
                    </table>
                    <table v-else class="w-full whitespace-nowrap text-sm">
                        <thead class="sticky top-0 bg-muted">
                            <tr>
                                <th
                                    v-for="heading in [
                                        'Date',
                                        'Employee',
                                        'Main Project',
                                        'OT Project',
                                        'Labour Days',
                                        'Daily Salary',
                                        'OT Hours',
                                        'Salary Cost',
                                        'OT Cost',
                                        'Total Cost',
                                        'Submitted By',
                                    ]"
                                    :key="heading"
                                    class="px-3 py-3 text-left"
                                >
                                    {{ heading }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="record in history.records" :key="record.id" class="border-t">
                                <td class="px-3 py-3">{{ record.date }}</td>
                                <td class="px-3 py-3">{{ record.employeeCode }} - {{ record.employeeName }}</td>
                                <td class="px-3 py-3">{{ record.mainProject || '–' }}</td>
                                <td class="px-3 py-3">{{ record.overtimeProject || '–' }}</td>
                                <td class="px-3 py-3">{{ record.labourDays }}</td>
                                <td class="px-3 py-3">{{ money(record.dailySalary) }}</td>
                                <td class="px-3 py-3">{{ overtimeLabel(record.overtimeHours) }}</td>
                                <td class="px-3 py-3">{{ money(record.basicCost) }}</td>
                                <td class="px-3 py-3">{{ money(record.overtimeCost) }}</td>
                                <td class="px-3 py-3">{{ money(record.totalCost) }}</td>
                                <td class="px-3 py-3">{{ record.submittedBy }}</td>
                            </tr>
                            <tr v-if="!history.records.length">
                                <td colspan="11" class="p-8 text-center text-muted-foreground">No attendance in this date range.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-muted-foreground">
                    Current payroll rates. Salary + overtime only; overhead is excluded. Overtime-only work has zero labour days on this project.
                    Costs are rounded for display.
                </p>
            </template>
        </DialogScrollContent>
    </Dialog>
</template>
