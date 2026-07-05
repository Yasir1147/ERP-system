<script setup lang="ts">
import SortableHeader from '@/components/SortableHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Eye, Search, Trash2, XCircle } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Expense {
    id: number;
    employeeType: string;
    employeeTypeLabel: string;
    projectName: string | null;
    projectStatus: string | null;
    expenseDate: string;
    expenseDateLabel: string;
    purpose: string;
    amount: number;
    receiptUrl: string | null;
    status: string;
    statusLabel: string;
    note: string | null;
    adminNote: string | null;
    submittedBy: string | null;
    submittedByRole: string | null;
    reviewedBy: string | null;
    reviewedAtLabel: string | null;
}

const props = defineProps<{
    expenses: Expense[];
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
        status: string;
        from: string;
        to: string;
        perPage: number;
        sort: string;
        direction: 'asc' | 'desc';
    };
    summary: {
        totalCount: number;
        totalAmount: number;
        pendingAmount: number;
        approvedAmount: number;
        rejectedAmount: number;
    };
    employeeTypes: Record<string, string>;
    purposes: string[];
    statuses: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Expenses', href: '/expenses' }];
const page = usePage();
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const perPage = ref(props.filters.perPage || 10);
const sortKey = ref(props.filters.sort || 'expense_date');
const sortDirection = ref<'asc' | 'desc'>(props.filters.direction || 'desc');
const actionForms = reactive<Record<number, { adminNote: string }>>({});

const successMessage = computed(() => page.props.flash?.success as string | undefined);
const money = (amount: number) => Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const statusClass = (expenseStatus: string) => {
    if (expenseStatus === 'approved') {
        return 'border-green-600/30 bg-green-600/10 text-green-700';
    }

    if (expenseStatus === 'rejected') {
        return 'border-red-600/30 bg-red-600/10 text-red-700';
    }

    return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
};

const ensureActionForm = (expense: Expense) => {
    if (!actionForms[expense.id]) {
        actionForms[expense.id] = { adminNote: '' };
    }

    return actionForms[expense.id];
};

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const reloadExpenses = (pageNumber = 1) => {
    router.get(
        '/expenses',
        {
            search: search.value.trim() || undefined,
            status: status.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
            per_page: perPage.value,
            sort: sortKey.value,
            direction: sortDirection.value,
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

    searchTimer = setTimeout(() => reloadExpenses(1), 350);
});

watch([status, from, to, perPage], () => reloadExpenses(1));

const sortExpenses = (key: string) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDirection.value = key === 'expense_date' ? 'desc' : 'asc';
    }

    reloadExpenses(1);
};

const approveExpense = (expense: Expense) => {
    const form = ensureActionForm(expense);

    router.post(
        `/expenses/${expense.id}/approve`,
        {
            admin_note: form.adminNote,
        },
        { preserveScroll: true },
    );
};

const rejectExpense = (expense: Expense) => {
    const form = ensureActionForm(expense);

    if (!confirm(`Reject expense for ${expense.purpose}?`)) {
        return;
    }

    router.post(
        `/expenses/${expense.id}/reject`,
        {
            admin_note: form.adminNote,
        },
        { preserveScroll: true },
    );
};

const deleteExpense = (expense: Expense) => {
    if (!confirm(`Delete expense bill for ${expense.purpose}? This cannot be undone.`)) {
        return;
    }

    router.delete(`/expenses/${expense.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Expenses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Daily Expenses</h1>
                <p class="mt-1 text-sm text-muted-foreground">Review Rope Access daily expense bills and receipt images.</p>
            </div>

            <div v-if="successMessage" class="rounded-md border border-green-600/20 bg-green-600/10 px-3 py-2 text-sm font-medium text-green-700">
                {{ successMessage }}
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Total Submitted</p>
                    <p class="mt-2 text-2xl font-semibold">{{ money(summary.totalAmount) }}</p>
                    <p class="text-xs text-muted-foreground">{{ summary.totalCount }} records</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Pending</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ money(summary.pendingAmount) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Approved</p>
                    <p class="mt-2 text-2xl font-semibold text-green-700">{{ money(summary.approvedAmount) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Rejected</p>
                    <p class="mt-2 text-2xl font-semibold text-red-700">{{ money(summary.rejectedAmount) }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <div class="grid gap-4 lg:grid-cols-[160px_160px_180px_minmax(260px,1fr)_120px]">
                    <div class="grid gap-2">
                        <Label for="expense-from">From</Label>
                        <Input id="expense-from" v-model="from" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expense-to">To</Label>
                        <Input id="expense-to" v-model="to" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expense-status">Status</Label>
                        <select
                            id="expense-status"
                            v-model="status"
                            class="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="expense-search">Search</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="expense-search" v-model="search" type="search" class="pl-9" placeholder="Search purpose, project, submitter, note" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="expense-per-page">Rows</Label>
                        <select
                            id="expense-per-page"
                            v-model.number="perPage"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option :value="10">10 / page</option>
                            <option :value="15">15 / page</option>
                            <option :value="25">25 / page</option>
                            <option :value="50">50 / page</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Expense List</h2>
                        <p class="text-sm text-muted-foreground">
                            Showing {{ pagination.from || 0 }}-{{ pagination.to || 0 }} of {{ pagination.total }} expense bills
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a href="/expenses/create?type=rope_access">Create Expense Bill</a>
                    </Button>
                </div>

                <div v-if="pagination.total === 0 && !search" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No expense bills submitted yet.
                </div>

                <div v-else-if="expenses.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No expense bills match your filters.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1260px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[115px] px-4 py-3 font-medium">
                                    <SortableHeader label="Date" column="expense_date" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortExpenses" />
                                </th>
                                <th class="w-[190px] px-4 py-3 font-medium">
                                    <SortableHeader label="Project" column="project" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortExpenses" />
                                </th>
                                <th class="w-[180px] px-4 py-3 font-medium">
                                    <SortableHeader label="Purpose" column="purpose" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortExpenses" />
                                </th>
                                <th class="w-[120px] px-4 py-3 text-right font-medium">
                                    <SortableHeader label="Amount" column="amount" align="right" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortExpenses" />
                                </th>
                                <th class="w-[110px] px-4 py-3 font-medium">Receipt</th>
                                <th class="w-[130px] px-4 py-3 font-medium">
                                    <SortableHeader label="Status" column="status" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortExpenses" />
                                </th>
                                <th class="w-[190px] px-4 py-3 font-medium">Notes</th>
                                <th class="w-[150px] px-4 py-3 font-medium">
                                    <SortableHeader label="Submitted By" column="submitted_by" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortExpenses" />
                                </th>
                                <th class="w-[280px] px-4 py-3 font-medium">Admin Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="expense in expenses" :key="expense.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">{{ expense.expenseDateLabel }}</td>
                                <td class="px-4 py-3">
                                    <p class="truncate font-medium">{{ expense.projectName || 'General expense' }}</p>
                                    <p v-if="expense.projectStatus" class="truncate text-xs capitalize text-muted-foreground">{{ expense.projectStatus }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="truncate">{{ expense.purpose }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ expense.employeeTypeLabel }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">{{ money(expense.amount) }}</td>
                                <td class="px-4 py-3">
                                    <Button v-if="expense.receiptUrl" as-child size="sm" variant="outline">
                                        <a :href="expense.receiptUrl" target="_blank" rel="noreferrer">
                                            <Eye class="size-4" />
                                            View
                                        </a>
                                    </Button>
                                    <span v-else class="text-xs text-muted-foreground">No image</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium" :class="statusClass(expense.status)">
                                        {{ expense.statusLabel }}
                                    </span>
                                    <p v-if="expense.reviewedBy" class="mt-1 text-xs text-muted-foreground">{{ expense.reviewedBy }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="truncate text-muted-foreground">{{ expense.note || '-' }}</p>
                                    <p v-if="expense.adminNote" class="mt-1 truncate text-xs text-muted-foreground">Admin: {{ expense.adminNote }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <p>{{ expense.submittedBy || '-' }}</p>
                                    <p v-if="expense.submittedByRole" class="text-xs">{{ expense.submittedByRole }}</p>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div v-if="expense.status === 'pending'" class="rounded-md border bg-muted/20 p-3">
                                        <div class="grid gap-2">
                                            <Label class="text-xs text-muted-foreground">Admin Note</Label>
                                            <Input v-model="ensureActionForm(expense).adminNote" type="text" placeholder="Optional admin note" />
                                        </div>
                                        <div class="mt-3 flex justify-end gap-2">
                                            <Button size="sm" type="button" variant="outline" @click="rejectExpense(expense)">
                                                <XCircle class="size-4" />
                                                Reject
                                            </Button>
                                            <Button size="sm" type="button" @click="approveExpense(expense)">
                                                <CheckCircle2 class="size-4" />
                                                Approve
                                            </Button>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-muted-foreground">
                                        <p>{{ expense.reviewedAtLabel || '-' }}</p>
                                    </div>
                                    <div class="mt-3 flex justify-end">
                                        <Button size="sm" type="button" variant="destructive" @click="deleteExpense(expense)">
                                            <Trash2 class="size-4" />
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagination.lastPage > 1" class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</p>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" size="sm" :disabled="pagination.currentPage === 1" @click="reloadExpenses(pagination.currentPage - 1)">Previous</Button>
                        <Button type="button" variant="outline" size="sm" :disabled="pagination.currentPage === pagination.lastPage" @click="reloadExpenses(pagination.currentPage + 1)">Next</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
