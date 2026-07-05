<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, LoaderCircle, Printer, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface StaffOption {
    id: number;
    label: string;
    designation: string | null;
}

interface SummaryRow {
    id: number;
    code: string;
    name: string;
    designation: string | null;
    staffTypeLabel: string;
    remoteDays: number;
    officeDays: number;
    totalDays: number;
}

interface AttendanceRow {
    id: number;
    dateLabel: string | null;
    staffCode: string | null;
    staffName: string | null;
    designation: string | null;
    staffTypeLabel: string | null;
    workMode: string;
    workModeLabel: string;
    checkInTime: string | null;
    checkOutTime: string | null;
    sessionCount: number;
    sessionSummary: string;
    note: string | null;
    submittedBy: string | null;
}

const props = defineProps<{
    staff: StaffOption[];
    workModes: Record<string, string>;
    filters: {
        from: string;
        to: string;
        staffId: string;
        workMode: string;
        search: string;
        perPage: number;
    };
    summaryRows: SummaryRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Office Staff', href: '/office-staff' },
    { title: 'Attendance Report', href: '/office-attendance/report' },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const staffId = ref(props.filters.staffId);
const workMode = ref(props.filters.workMode);
const search = ref(props.filters.search);
const perPage = ref(props.filters.perPage || 15);
const editRows = ref<Record<number, { work_mode: string; check_in_time: string; check_out_time: string; note: string }>>({});
const detailOpen = ref(false);
const detailLoading = ref(false);
const detailStaff = ref<SummaryRow | null>(null);
const detailRows = ref<AttendanceRow[]>([]);
const detailFrom = ref(props.filters.from);
const detailTo = ref(props.filters.to);
const detailWorkMode = ref(props.filters.workMode);
const detailSearch = ref('');

const totals = computed(() =>
    props.summaryRows.reduce(
        (carry, row) => ({
            staff: carry.staff + 1,
            remote: carry.remote + row.remoteDays,
            office: carry.office + row.officeDays,
            total: carry.total + row.totalDays,
        }),
        { staff: 0, remote: 0, office: 0, total: 0 },
    ),
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const reloadReport = (pageNumber = 1) => {
    router.get(
        '/office-attendance/report',
        {
            from: from.value || undefined,
            to: to.value || undefined,
            staff_id: staffId.value || undefined,
            work_mode: workMode.value || undefined,
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

    searchTimer = setTimeout(() => reloadReport(1), 350);
});

watch(perPage, () => reloadReport(1));

const printUrl = computed(() => {
    const params = new URLSearchParams();

    if (from.value) params.set('from', from.value);
    if (to.value) params.set('to', to.value);
    if (staffId.value) params.set('staff_id', staffId.value);
    if (workMode.value) params.set('work_mode', workMode.value);
    if (search.value.trim()) params.set('search', search.value.trim());

    return `/office-attendance/report-print?${params.toString()}`;
});

const detailPrintUrl = computed(() => {
    const params = new URLSearchParams();

    if (detailFrom.value) params.set('from', detailFrom.value);
    if (detailTo.value) params.set('to', detailTo.value);
    if (detailStaff.value) params.set('staff_id', String(detailStaff.value.id));
    if (detailWorkMode.value) params.set('work_mode', detailWorkMode.value);
    if (detailSearch.value.trim()) params.set('search', detailSearch.value.trim());

    return `/office-attendance/report-print?${params.toString()}`;
});

const loadStaffDetails = async (row: SummaryRow) => {
    detailStaff.value = row;
    detailFrom.value = from.value;
    detailTo.value = to.value;
    detailWorkMode.value = workMode.value;
    detailSearch.value = '';
    detailOpen.value = true;

    await fetchStaffDetails();
};

const fetchStaffDetails = async () => {
    if (!detailStaff.value) return;

    detailLoading.value = true;

    const params = new URLSearchParams();
    if (detailFrom.value) params.set('from', detailFrom.value);
    if (detailTo.value) params.set('to', detailTo.value);
    if (detailWorkMode.value) params.set('work_mode', detailWorkMode.value);
    if (detailSearch.value.trim()) params.set('search', detailSearch.value.trim());

    const response = await fetch(`/office-attendance/report/${detailStaff.value.id}/details?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (response.ok) {
        const data = await response.json();
        detailRows.value = data.rows ?? [];
        detailRows.value.forEach((attendance: AttendanceRow) => editRow(attendance));
    }

    detailLoading.value = false;
};

const editRow = (row: AttendanceRow) => {
    if (!editRows.value[row.id]) {
        editRows.value[row.id] = {
            work_mode: row.workMode,
            check_in_time: row.checkInTime ?? '',
            check_out_time: row.checkOutTime ?? '',
            note: row.note ?? '',
        };
    }

    return editRows.value[row.id];
};

const updateAttendance = (row: AttendanceRow) => {
    const payload = editRow(row);

    router.put(`/office-attendance/report/${row.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => fetchStaffDetails(),
    });
};
</script>

<template>
    <Head title="Office Attendance Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Office Attendance Report</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Monthly or date-range report for remote and office staff attendance.</p>
                </div>
                <Button as-child variant="outline">
                    <a :href="printUrl" target="_blank" rel="noreferrer">
                        <Printer class="size-4" />
                        Report PDF
                    </a>
                </Button>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Staff</p>
                    <p class="mt-2 text-2xl font-semibold">{{ totals.staff }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Office Days</p>
                    <p class="mt-2 text-2xl font-semibold">{{ totals.office }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Remote Days</p>
                    <p class="mt-2 text-2xl font-semibold">{{ totals.remote }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Total Records</p>
                    <p class="mt-2 text-2xl font-semibold">{{ totals.total }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                <div class="grid gap-4 xl:grid-cols-[160px_160px_minmax(220px,1fr)_170px_minmax(260px,1fr)_auto] xl:items-end">
                    <div class="grid gap-2">
                        <Label for="office-from">From</Label>
                        <Input id="office-from" v-model="from" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="office-to">To</Label>
                        <Input id="office-to" v-model="to" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="office-staff-filter">Staff</Label>
                        <select
                            id="office-staff-filter"
                            v-model="staffId"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Staff</option>
                            <option v-for="member in staff" :key="member.id" :value="String(member.id)">{{ member.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="office-work-mode">Mode</Label>
                        <select
                            id="office-work-mode"
                            v-model="workMode"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Modes</option>
                            <option v-for="(label, mode) in workModes" :key="mode" :value="mode">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="office-search">Search</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="office-search" v-model="search" type="search" class="pl-9" placeholder="Search code, name, designation, note" />
                        </div>
                    </div>
                    <Button type="button" @click="reloadReport(1)">Filter</Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="border-b p-4">
                    <h2 class="text-base font-medium">Staff Summary</h2>
                    <p class="text-sm text-muted-foreground">{{ summaryRows.length }} staff members in selected range</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[30%] px-4 py-3 font-medium">Staff</th>
                                <th class="w-[22%] px-4 py-3 font-medium">Designation</th>
                                <th class="w-[14%] px-4 py-3 text-center font-medium">Office Days</th>
                                <th class="w-[14%] px-4 py-3 text-center font-medium">Remote Days</th>
                                <th class="w-[10%] px-4 py-3 text-center font-medium">Total</th>
                                <th class="w-[10%] px-4 py-3 text-right font-medium">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in summaryRows" :key="row.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ row.code }} - {{ row.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.staffTypeLabel }}</p>
                                </td>
                                <td class="px-4 py-3">{{ row.designation || '-' }}</td>
                                <td class="px-4 py-3 text-center">{{ row.officeDays }}</td>
                                <td class="px-4 py-3 text-center">{{ row.remoteDays }}</td>
                                <td class="px-4 py-3 text-center font-semibold">{{ row.totalDays }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button type="button" variant="outline" size="sm" @click="loadStaffDetails(row)">
                                        <BookOpen class="size-4" />
                                        Details
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog v-model:open="detailOpen">
                <DialogScrollContent class="w-[96vw] max-w-[1500px]">
                    <DialogHeader>
                        <DialogTitle>{{ detailStaff ? `${detailStaff.code} - ${detailStaff.name}` : 'Staff Attendance Details' }}</DialogTitle>
                        <DialogDescription>
                            Review, edit, and print attendance details for the selected staff member.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3 md:grid-cols-[150px_150px_170px_minmax(220px,1fr)_auto_auto] md:items-end">
                        <div class="grid gap-2">
                            <Label for="detail-from">From</Label>
                            <Input id="detail-from" v-model="detailFrom" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="detail-to">To</Label>
                            <Input id="detail-to" v-model="detailTo" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="detail-mode">Mode</Label>
                            <select
                                id="detail-mode"
                                v-model="detailWorkMode"
                                class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">All Modes</option>
                                <option v-for="(label, mode) in workModes" :key="mode" :value="mode">{{ label }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="detail-search">Search</Label>
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input id="detail-search" v-model="detailSearch" type="search" class="pl-9" placeholder="Search note, code, name" />
                            </div>
                        </div>
                        <Button type="button" variant="outline" :disabled="detailLoading" @click="fetchStaffDetails">
                            <LoaderCircle v-if="detailLoading" class="size-4 animate-spin" />
                            Search
                        </Button>
                        <Button as-child variant="outline">
                            <a :href="detailPrintUrl" target="_blank" rel="noreferrer">
                                <Printer class="size-4" />
                                PDF
                            </a>
                        </Button>
                    </div>

                    <div class="overflow-hidden rounded-lg border">
                        <div class="border-b p-3">
                            <h3 class="text-sm font-medium">Attendance Detail</h3>
                            <p class="text-xs text-muted-foreground">{{ detailRows.length }} records</p>
                        </div>
                        <div v-if="detailLoading" class="flex min-h-40 items-center justify-center text-sm text-muted-foreground">
                            <LoaderCircle class="mr-2 size-4 animate-spin" />
                            Loading details...
                        </div>
                        <div v-else-if="detailRows.length === 0" class="flex min-h-40 items-center justify-center text-sm text-muted-foreground">
                            No attendance records found for this staff member.
                        </div>
                        <div v-else class="max-h-[55vh] overflow-auto">
                            <table class="w-full min-w-[1260px] table-fixed text-sm">
                                <thead class="sticky top-0 border-b bg-muted/90 text-left text-muted-foreground">
                                    <tr>
                                        <th class="w-[120px] px-4 py-3 font-medium">Date</th>
                                        <th class="w-[170px] px-4 py-3 font-medium">Work Mode</th>
                                        <th class="w-[160px] px-4 py-3 font-medium">Check In</th>
                                        <th class="w-[160px] px-4 py-3 font-medium">Check Out</th>
                                        <th class="w-[210px] px-4 py-3 font-medium">Sessions</th>
                                        <th class="w-[240px] px-4 py-3 font-medium">Note</th>
                                        <th class="w-[140px] px-4 py-3 font-medium">Submitted By</th>
                                        <th class="w-[100px] px-4 py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in detailRows" :key="row.id" class="border-b last:border-b-0">
                                        <td class="px-4 py-3">{{ row.dateLabel }}</td>
                                        <td class="px-4 py-3">
                                            <select
                                                v-model="editRow(row).work_mode"
                                                class="h-9 w-full rounded-md border border-input bg-background px-2 py-1 text-xs ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            >
                                                <option v-for="(label, mode) in workModes" :key="mode" :value="mode">{{ label }}</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Input v-model="editRow(row).check_in_time" type="time" class="h-9 text-xs" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <Input v-model="editRow(row).check_out_time" type="time" class="h-9 text-xs" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-xs font-medium">{{ row.sessionCount || 0 }} session{{ row.sessionCount === 1 ? '' : 's' }}</p>
                                            <p class="truncate text-xs text-muted-foreground" :title="row.sessionSummary">{{ row.sessionSummary || '-' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Input v-model="editRow(row).note" class="h-9 text-xs" placeholder="Optional note" />
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">{{ row.submittedBy || '-' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <Button type="button" size="sm" @click="updateAttendance(row)">Save</Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </DialogScrollContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
