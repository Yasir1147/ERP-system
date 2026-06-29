<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Plus, Search, ShieldX } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

interface Employee {
    id: number;
    code: string | null;
    name: string;
    profession: string;
    type: string;
    status: string;
    label: string;
}

interface Fine {
    id: number;
    employeeId: number;
    employeeCode: string | null;
    employeeName: string;
    employeeProfession: string | null;
    employeeType: string | null;
    fineDate: string;
    fineDateLabel: string;
    deductionMonth: string | null;
    deductionMonthLabel: string | null;
    reason: string;
    amount: number;
    appliedAmount: number | null;
    status: string;
    statusLabel: string;
    note: string | null;
    adminNote: string | null;
    createdBy: string | null;
    createdByRole: string | null;
    reviewedBy: string | null;
    reviewedAtLabel: string | null;
}

const props = defineProps<{
    employees: Employee[];
    fines: Fine[];
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search: string;
        perPage: number;
    };
    employeeTypes: Record<string, string>;
    reasons: string[];
    statuses: Record<string, string>;
    currentMonth: string;
    nextMonth: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fines', href: '/fines' }];
const page = usePage();
const search = ref(props.filters.search || '');
const perPage = ref(props.filters.perPage || 5);
const employeeSearch = ref('');
const employeeOpen = ref(false);
const employeeDropdownRef = ref<HTMLElement | null>(null);

const createForm = useForm({
    employee_id: '',
    fine_date: new Date().toISOString().slice(0, 10),
    reason: '',
    amount: '',
    note: '',
});

const actionForms = reactive<Record<number, { deductionMonth: string; appliedAmount: string; adminNote: string }>>({});

const successMessage = computed(() => page.props.flash?.success as string | undefined);
const employeeLabel = (employee: Employee) => `${employee.label} (${props.employeeTypes[employee.type]})`;
const money = (amount: number) => Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const selectedEmployee = computed(() => props.employees.find((employee) => String(employee.id) === createForm.employee_id));
const createEmployeeButtonLabel = computed(() => (selectedEmployee.value ? employeeLabel(selectedEmployee.value) : 'Select employee'));
const filteredEmployees = computed(() => {
    const query = employeeSearch.value.trim().toLowerCase();

    if (!query) {
        return props.employees;
    }

    return props.employees.filter((employee) =>
        [employee.code, employee.name, employee.profession, props.employeeTypes[employee.type]]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)),
    );
});

const statusClass = (status: string) => {
    if (status === 'applied') {
        return 'border-green-600/30 bg-green-600/10 text-green-700';
    }

    if (status === 'waived') {
        return 'border-slate-500/30 bg-slate-500/10 text-slate-700';
    }

    return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
};

const ensureActionForm = (fine: Fine) => {
    if (!actionForms[fine.id]) {
        actionForms[fine.id] = {
            deductionMonth: props.currentMonth,
            appliedAmount: String(fine.appliedAmount ?? fine.amount),
            adminNote: '',
        };
    }

    return actionForms[fine.id];
};

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const reloadFines = (pageNumber = 1) => {
    router.get(
        '/fines',
        {
            search: search.value.trim() || undefined,
            per_page: perPage.value,
            page: pageNumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => reloadFines(1), 350);
});

watch(perPage, () => reloadFines(1));

const createFine = () => {
    createForm.post('/fines', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('employee_id', 'reason', 'amount', 'note');
            createForm.fine_date = new Date().toISOString().slice(0, 10);
            employeeSearch.value = '';
        },
    });
};

const selectCreateEmployee = (employee: Employee) => {
    createForm.employee_id = String(employee.id);
    employeeSearch.value = '';
    employeeOpen.value = false;
};

const closeDropdownsOnOutsideClick = (event: MouseEvent) => {
    const target = event.target as Node;

    if (employeeDropdownRef.value && !employeeDropdownRef.value.contains(target)) {
        employeeOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdownsOnOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdownsOnOutsideClick);
});

const applyFine = (fine: Fine) => {
    const form = ensureActionForm(fine);

    router.post(
        `/fines/${fine.id}/apply`,
        {
            deduction_month: form.deductionMonth,
            applied_amount: form.appliedAmount,
            admin_note: form.adminNote,
        },
        { preserveScroll: true },
    );
};

const waiveFine = (fine: Fine) => {
    const form = ensureActionForm(fine);

    if (!confirm(`Waive fine for ${fine.employeeName}?`)) {
        return;
    }

    router.post(
        `/fines/${fine.id}/waive`,
        {
            admin_note: form.adminNote,
        },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Fines" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Employee Fines</h1>
                <p class="mt-1 text-sm text-muted-foreground">Create fine tickets and apply approved fines to payroll deductions.</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="createFine">
                <div v-if="successMessage" class="mb-4 rounded-md border border-green-600/20 bg-green-600/10 px-3 py-2 text-sm font-medium text-green-700">
                    {{ successMessage }}
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(240px,1fr)_150px_minmax(180px,220px)_140px_minmax(220px,1fr)_auto] xl:items-start">
                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-employee">Employee</Label>
                        <div ref="employeeDropdownRef" class="relative min-w-0">
                            <button
                                id="fine-employee"
                                type="button"
                                class="flex h-10 w-full max-w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 text-left text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @click="employeeOpen = !employeeOpen"
                            >
                                <span class="block min-w-0 flex-1 truncate">{{ createEmployeeButtonLabel }}</span>
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                            </button>
                            <div v-if="employeeOpen" class="absolute left-0 right-0 top-full z-40 mt-2 rounded-md border bg-background p-2 shadow-lg">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input v-model="employeeSearch" type="search" class="pl-9" placeholder="Search by code, name, or profession" />
                                </div>
                                <div class="mt-2 max-h-64 overflow-y-auto rounded-md border">
                                    <button
                                        v-for="employee in filteredEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-start gap-3 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60"
                                        :class="createForm.employee_id === String(employee.id) ? 'bg-primary/10' : 'bg-background'"
                                        @click="selectCreateEmployee(employee)"
                                    >
                                        <span
                                            class="mt-1 flex size-4 shrink-0 items-center justify-center rounded border"
                                            :class="createForm.employee_id === String(employee.id) ? 'border-primary bg-primary text-primary-foreground' : 'bg-background'"
                                        >
                                            <CheckCircle2 v-if="createForm.employee_id === String(employee.id)" class="size-3" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium">{{ employeeLabel(employee) }}</span>
                                            <span class="block truncate text-xs text-muted-foreground">{{ employeeTypes[employee.type] }}</span>
                                        </span>
                                    </button>
                                    <div v-if="filteredEmployees.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No employees found.</div>
                                </div>
                            </div>
                        </div>
                        <InputError :message="createForm.errors.employee_id" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-date">Fine Date</Label>
                        <Input id="fine-date" v-model="createForm.fine_date" type="date" />
                        <InputError :message="createForm.errors.fine_date" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-reason">Reason</Label>
                        <select
                            id="fine-reason"
                            v-model="createForm.reason"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">Select reason</option>
                            <option v-for="reason in reasons" :key="reason" :value="reason">{{ reason }}</option>
                        </select>
                        <InputError :message="createForm.errors.reason" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-amount">Amount</Label>
                        <Input id="fine-amount" v-model="createForm.amount" type="number" min="0.01" step="0.01" placeholder="0.00" />
                        <InputError :message="createForm.errors.amount" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-note">Note</Label>
                        <Input id="fine-note" v-model="createForm.note" type="text" placeholder="Optional details" />
                        <InputError :message="createForm.errors.note" />
                    </div>
                    <Button class="mt-0 whitespace-nowrap xl:mt-8" type="submit" :disabled="createForm.processing">
                        <Plus class="size-4" />
                        Add Fine
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Fine List</h2>
                        <p class="text-sm text-muted-foreground">
                            Showing {{ pagination.from || 0 }}-{{ pagination.to || 0 }} of {{ pagination.total }} fine tickets
                        </p>
                    </div>
                    <div class="flex w-full flex-col gap-2 md:max-w-xl md:flex-row">
                        <div class="relative min-w-0 flex-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" type="search" class="pl-9" placeholder="Search fines" />
                        </div>
                        <select
                            v-model.number="perPage"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option :value="5">5 / page</option>
                            <option :value="10">10 / page</option>
                            <option :value="15">15 / page</option>
                        </select>
                    </div>
                </div>

                <div v-if="pagination.total === 0 && !search" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No fine tickets created yet.
                </div>

                <div v-else-if="fines.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No fine tickets match your search.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[230px] px-4 py-3 font-medium">Employee</th>
                                <th class="w-[110px] px-4 py-3 font-medium">Fine Date</th>
                                <th class="w-[170px] px-4 py-3 font-medium">Reason</th>
                                <th class="w-[110px] px-4 py-3 text-right font-medium">Amount</th>
                                <th class="w-[140px] px-4 py-3 font-medium">Status</th>
                                <th class="w-[220px] px-4 py-3 font-medium">Notes</th>
                                <th class="w-[150px] px-4 py-3 font-medium">Created By</th>
                                <th class="w-[380px] px-4 py-3 font-medium">Admin Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="fine in fines" :key="fine.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <p class="truncate font-medium">{{ fine.employeeCode }} - {{ fine.employeeName }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ fine.employeeProfession }}<template v-if="fine.employeeType"> - {{ employeeTypes[fine.employeeType] }}</template>
                                    </p>
                                </td>
                                <td class="px-4 py-3">{{ fine.fineDateLabel }}</td>
                                <td class="px-4 py-3">
                                    <p class="truncate">{{ fine.reason }}</p>
                                    <p v-if="fine.deductionMonthLabel" class="mt-1 text-xs text-muted-foreground">Payroll: {{ fine.deductionMonthLabel }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <p class="font-semibold">{{ money(fine.appliedAmount ?? fine.amount) }}</p>
                                    <p v-if="fine.appliedAmount !== null && fine.appliedAmount < fine.amount" class="text-xs text-muted-foreground">
                                        Original: {{ money(fine.amount) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(fine.status)">
                                        {{ fine.statusLabel }}
                                    </span>
                                    <p v-if="fine.reviewedBy" class="mt-1 text-xs text-muted-foreground">{{ fine.reviewedBy }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="truncate text-muted-foreground">{{ fine.note || '-' }}</p>
                                    <p v-if="fine.adminNote" class="mt-1 truncate text-xs text-muted-foreground">Admin: {{ fine.adminNote }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <p>{{ fine.createdBy || '-' }}</p>
                                    <p v-if="fine.createdByRole" class="text-xs">{{ fine.createdByRole }}</p>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div v-if="fine.status === 'pending'" class="rounded-md border bg-muted/20 p-3">
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <div class="grid gap-1">
                                                <Label class="text-xs text-muted-foreground">Payroll Month</Label>
                                                <Input v-model="ensureActionForm(fine).deductionMonth" type="month" />
                                            </div>
                                            <div class="grid gap-1">
                                                <Label class="text-xs text-muted-foreground">Deduct Amount</Label>
                                                <Input
                                                    v-model="ensureActionForm(fine).appliedAmount"
                                                    type="number"
                                                    min="0.01"
                                                    :max="fine.amount"
                                                    step="0.01"
                                                />
                                                <p class="text-xs text-muted-foreground">Original: {{ money(fine.amount) }}</p>
                                            </div>
                                            <div class="grid gap-1 md:col-span-2">
                                                <Label class="text-xs text-muted-foreground">Admin Note</Label>
                                                <Input v-model="ensureActionForm(fine).adminNote" type="text" placeholder="Optional admin note" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex justify-end gap-2">
                                            <Button size="sm" type="button" variant="outline" @click="waiveFine(fine)">
                                                <ShieldX class="size-4" />
                                                Waive
                                            </Button>
                                            <Button size="sm" type="button" @click="applyFine(fine)">
                                                <CheckCircle2 class="size-4" />
                                                Apply Payroll
                                            </Button>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-muted-foreground">
                                        <p>{{ fine.reviewedAtLabel || '-' }}</p>
                                        <p v-if="fine.deductionMonthLabel">Deducted in {{ fine.deductionMonthLabel }}</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagination.lastPage > 1" class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</p>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" size="sm" :disabled="pagination.currentPage === 1" @click="reloadFines(pagination.currentPage - 1)">Previous</Button>
                        <Button type="button" variant="outline" size="sm" :disabled="pagination.currentPage === pagination.lastPage" @click="reloadFines(pagination.currentPage + 1)">Next</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
