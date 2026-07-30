<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BellOff,
    CheckCircle2,
    ChevronDown,
    Download,
    FileClock,
    MessageCircle,
    Pencil,
    Plus,
    Search,
    Settings2,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface DocumentRow {
    id: number;
    employeeId: number;
    employeeCode: string | null;
    employeeName: string;
    employeeProfession: string | null;
    employeeType: string;
    categoryId: number;
    categoryName: string;
    documentNumber: string | null;
    issueDate: string | null;
    expiryDate: string;
    expiryDateLabel: string;
    daysUntilExpiry: number;
    status: 'active' | 'expiring' | 'expired';
    fileAvailable: boolean;
    notes: string | null;
    reminderDays: number | null;
    effectiveReminderDays: number;
    notificationEnabled: boolean;
    emailEnabled: boolean;
    whatsappEnabled: boolean;
    notificationEmail: string | null;
    whatsappNumber: string | null;
    lastNotifications: { channel: string; status: string; date: string | null; error: string | null }[];
}

interface Category {
    id: number;
    name: string;
    default_reminder_days: number;
    is_active: boolean;
    documents_count: number;
}

interface Employee {
    id: number;
    label: string;
    type: string;
    status: string;
}

const props = defineProps<{
    documents: DocumentRow[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number; from: number | null; to: number | null };
    summary: { total: number; active: number; expiring: number; expired: number; notificationsDisabled: number };
    categories: Category[];
    employees: Employee[];
    filters: { search: string; categoryId: string; employeeId: string; status: string; notification: string; perPage: number };
    whatsappSettings: {
        enabled: boolean;
        graphVersion: string;
        phoneNumberId: string;
        templateName: string;
        templateLanguage: string;
        tokenConfigured: boolean;
    };
    defaultEmail: string | null;
}>();

const page = usePage();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Documents & Expiry', href: '/employee-documents' }];
const search = ref(props.filters.search);
const categoryId = ref(props.filters.categoryId);
const employeeId = ref(props.filters.employeeId);
const status = ref(props.filters.status);
const notification = ref(props.filters.notification);
const perPage = ref(String(props.filters.perPage));
const showDocumentForm = ref(false);
const showCategories = ref(false);
const showWhatsApp = ref(false);
const editingId = ref<number | null>(null);
const editingCategoryId = ref<number | null>(null);
const filterEmployeeSearch = ref('');
const filterEmployeeOpen = ref(false);
const filterEmployeeDropdownRef = ref<HTMLElement | null>(null);
const formEmployeeSearch = ref('');
const formEmployeeOpen = ref(false);
const formEmployeeDropdownRef = ref<HTMLElement | null>(null);

const documentForm = useForm({
    employee_id: '',
    document_category_id: '',
    document_number: '',
    issue_date: '',
    expiry_date: '',
    document_file: null as File | null,
    notes: '',
    reminder_days: '' as number | '',
    notification_enabled: true,
    email_enabled: true,
    whatsapp_enabled: false,
    notification_email: props.defaultEmail ?? '',
    whatsapp_number: '',
});

const categoryForm = useForm({
    name: '',
    default_reminder_days: 10,
    is_active: true,
});

const whatsappForm = useForm({
    whatsapp_enabled: props.whatsappSettings.enabled,
    whatsapp_graph_version: props.whatsappSettings.graphVersion || 'v23.0',
    whatsapp_phone_number_id: props.whatsappSettings.phoneNumberId,
    whatsapp_access_token: '',
    whatsapp_template_name: props.whatsappSettings.templateName || 'document_expiry_reminder',
    whatsapp_template_language: props.whatsappSettings.templateLanguage || 'en',
});

const activeCategories = computed(() =>
    props.categories.filter((category) => category.is_active || category.id === Number(documentForm.document_category_id)),
);
const employeeMatches = (employee: Employee, searchValue: string) => {
    const query = searchValue.trim().toLowerCase();

    return !query || [employee.label, employee.type, employee.status].some((value) => value.toLowerCase().includes(query));
};
const filteredFilterEmployees = computed(() => props.employees.filter((employee) => employeeMatches(employee, filterEmployeeSearch.value)));
const filteredFormEmployees = computed(() => props.employees.filter((employee) => employeeMatches(employee, formEmployeeSearch.value)));
const selectedFilterEmployee = computed(() => props.employees.find((employee) => String(employee.id) === employeeId.value));
const selectedFormEmployee = computed(() => props.employees.find((employee) => String(employee.id) === documentForm.employee_id));
const selectFilterEmployee = (employee?: Employee) => {
    employeeId.value = employee ? String(employee.id) : '';
    filterEmployeeSearch.value = '';
    filterEmployeeOpen.value = false;
};
const selectFormEmployee = (employee: Employee) => {
    documentForm.employee_id = String(employee.id);
    formEmployeeSearch.value = '';
    formEmployeeOpen.value = false;
};
const closeEmployeeDropdowns = (event: MouseEvent) => {
    const target = event.target as Node;

    if (filterEmployeeDropdownRef.value && !filterEmployeeDropdownRef.value.contains(target)) {
        filterEmployeeOpen.value = false;
    }

    if (formEmployeeDropdownRef.value && !formEmployeeDropdownRef.value.contains(target)) {
        formEmployeeOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeEmployeeDropdowns));
onBeforeUnmount(() => document.removeEventListener('click', closeEmployeeDropdowns));

const statusClass = (value: DocumentRow['status']) => {
    if (value === 'expired') return 'border-red-600/30 bg-red-600/10 text-red-700';
    if (value === 'expiring') return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
    return 'border-green-600/30 bg-green-600/10 text-green-700';
};
const statusLabel = (document: DocumentRow) => {
    if (document.daysUntilExpiry < 0) return `Expired ${Math.abs(document.daysUntilExpiry)} day(s) ago`;
    if (document.daysUntilExpiry === 0) return 'Expires today';
    return `${document.daysUntilExpiry} day(s) remaining`;
};

const reload = (number = 1) =>
    router.get(
        '/employee-documents',
        {
            search: search.value.trim() || undefined,
            category_id: categoryId.value || undefined,
            employee_id: employeeId.value || undefined,
            status: status.value || undefined,
            notification: notification.value || undefined,
            per_page: perPage.value,
            page: number,
        },
        { preserveState: true, replace: true },
    );

const resetFilters = () => {
    search.value = '';
    categoryId.value = '';
    employeeId.value = '';
    filterEmployeeSearch.value = '';
    filterEmployeeOpen.value = false;
    status.value = '';
    notification.value = '';
    perPage.value = '15';
    reload();
};

const openDocument = (document?: DocumentRow) => {
    formEmployeeSearch.value = '';
    formEmployeeOpen.value = false;
    documentForm.transform((data) => data);
    documentForm.reset();
    documentForm.clearErrors();
    editingId.value = document?.id ?? null;
    if (document) {
        documentForm.employee_id = String(document.employeeId);
        documentForm.document_category_id = String(document.categoryId);
        documentForm.document_number = document.documentNumber ?? '';
        documentForm.issue_date = document.issueDate ?? '';
        documentForm.expiry_date = document.expiryDate;
        documentForm.notes = document.notes ?? '';
        documentForm.reminder_days = document.reminderDays ?? '';
        documentForm.notification_enabled = document.notificationEnabled;
        documentForm.email_enabled = document.emailEnabled;
        documentForm.whatsapp_enabled = document.whatsappEnabled;
        documentForm.notification_email = document.notificationEmail ?? '';
        documentForm.whatsapp_number = document.whatsappNumber ?? '';
    } else {
        documentForm.notification_email = props.defaultEmail ?? '';
    }
    showDocumentForm.value = true;
};

const closeDocument = () => {
    formEmployeeSearch.value = '';
    formEmployeeOpen.value = false;
    showDocumentForm.value = false;
    editingId.value = null;
    documentForm.reset();
    documentForm.clearErrors();
};

const saveDocument = () => {
    const options = { preserveScroll: true, forceFormData: true, onSuccess: closeDocument };
    if (editingId.value) {
        documentForm.transform((data) => ({ ...data, _method: 'put' })).post(`/employee-documents/${editingId.value}`, options);
        return;
    }
    documentForm.transform((data) => data);
    documentForm.post('/employee-documents', options);
};

const toggleNotification = (document: DocumentRow) =>
    router.patch(
        `/employee-documents/${document.id}/notification`,
        { notification_enabled: !document.notificationEnabled },
        { preserveScroll: true },
    );

const removeDocument = (document: DocumentRow) => {
    if (window.confirm(`Delete ${document.categoryName} for ${document.employeeName}?`)) {
        router.delete(`/employee-documents/${document.id}`, { preserveScroll: true });
    }
};

const editCategory = (category?: Category) => {
    categoryForm.reset();
    categoryForm.clearErrors();
    editingCategoryId.value = category?.id ?? null;
    if (category) {
        categoryForm.name = category.name;
        categoryForm.default_reminder_days = category.default_reminder_days;
        categoryForm.is_active = category.is_active;
    }
};

const saveCategory = () => {
    const options = { preserveScroll: true, onSuccess: () => editCategory() };
    if (editingCategoryId.value) {
        categoryForm.put(`/document-categories/${editingCategoryId.value}`, options);
        return;
    }
    categoryForm.post('/document-categories', options);
};

const removeCategory = (category: Category) => {
    if (window.confirm(`Delete category ${category.name}?`)) {
        router.delete(`/document-categories/${category.id}`, { preserveScroll: true });
    }
};

const saveWhatsApp = () =>
    whatsappForm.put('/settings/whatsapp', {
        preserveScroll: true,
        onSuccess: () => {
            showWhatsApp.value = false;
            whatsappForm.whatsapp_access_token = '';
        },
    });
</script>

<template>
    <Head title="Documents & Expiry" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="employee-document-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Employee Documents & Expiry</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Track expiry dates and repeat daily reminders until notification is manually stopped.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="showCategories = true"><Settings2 class="size-4" />Categories</Button>
                    <Button variant="outline" @click="showWhatsApp = true"><MessageCircle class="size-4" />WhatsApp Setup</Button>
                    <Button @click="openDocument()"><Plus class="size-4" />Add Document</Button>
                </div>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.errors?.category" class="rounded-md border border-red-600/30 bg-red-600/10 px-4 py-3 text-sm text-red-700">
                {{ page.props.errors.category }}
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="item in [
                        ['Total Documents', summary.total],
                        ['Active', summary.active],
                        ['Expiring in 10 Days', summary.expiring],
                        ['Expired', summary.expired],
                        ['Notifications Off', summary.notificationsDisabled],
                    ]"
                    :key="String(item[0])"
                    class="rounded-lg border bg-card p-4 shadow-sm"
                >
                    <div class="text-sm text-muted-foreground">{{ item[0] }}</div>
                    <div class="mt-2 text-2xl font-semibold tabular-nums">{{ item[1] }}</div>
                </div>
            </div>

            <section class="rounded-lg border bg-card shadow-sm">
                <form class="grid gap-3 border-b p-4 lg:grid-cols-6" @submit.prevent="reload()">
                    <div class="grid gap-1.5 lg:col-span-2">
                        <Label>Search</Label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" class="pl-9" placeholder="Employee, document number or category" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Category</Label>
                        <select v-model="categoryId" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All categories</option>
                            <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Employee</Label>
                        <div ref="filterEmployeeDropdownRef" class="relative min-w-0">
                            <button
                                type="button"
                                class="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 text-left text-sm"
                                @click="filterEmployeeOpen = !filterEmployeeOpen"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ selectedFilterEmployee?.label || 'All employees' }}</span>
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                            </button>
                            <div
                                v-if="filterEmployeeOpen"
                                class="absolute left-0 top-full z-40 mt-1 w-[min(420px,calc(100vw-2rem))] rounded-md border bg-background p-2 shadow-xl"
                            >
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="filterEmployeeSearch"
                                        type="search"
                                        class="pl-9"
                                        placeholder="Search by code, name, or profession"
                                    />
                                </div>
                                <div class="mt-2 max-h-64 overflow-y-auto rounded-md border">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 border-b px-3 py-2 text-left text-sm hover:bg-muted/60"
                                        :class="!employeeId ? 'bg-primary/10' : ''"
                                        @click="selectFilterEmployee()"
                                    >
                                        <CheckCircle2 class="size-4" :class="!employeeId ? 'text-primary' : 'text-transparent'" />
                                        All employees
                                    </button>
                                    <button
                                        v-for="employee in filteredFilterEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-center gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60"
                                        :class="employeeId === String(employee.id) ? 'bg-primary/10' : ''"
                                        @click="selectFilterEmployee(employee)"
                                    >
                                        <CheckCircle2
                                            class="size-4 shrink-0"
                                            :class="employeeId === String(employee.id) ? 'text-primary' : 'text-transparent'"
                                        />
                                        <span class="min-w-0 truncate">{{ employee.label }}</span>
                                    </button>
                                    <div v-if="!filteredFilterEmployees.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                        No employees found.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Expiry Status</Label>
                        <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="expiring">Expiring</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Notification</Label>
                        <select v-model="notification" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All</option>
                            <option value="enabled">Enabled</option>
                            <option value="disabled">Stopped</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap items-end gap-2 lg:col-span-6">
                        <div class="grid gap-1.5">
                            <Label>Rows</Label>
                            <select v-model="perPage" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                                <option v-for="n in [15, 25, 50]" :key="n">{{ n }}</option>
                            </select>
                        </div>
                        <Button>Apply Filters</Button><Button type="button" variant="outline" @click="resetFilters">Clear</Button>
                    </div>
                </form>

                <div v-if="!pagination.total" class="flex min-h-60 flex-col items-center justify-center gap-2 text-muted-foreground">
                    <FileClock class="size-10" /><span>No employee documents found.</span>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1250px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Employee</th>
                                <th class="px-4 py-3">Document</th>
                                <th class="px-4 py-3">Issue / Expiry</th>
                                <th class="px-4 py-3">Reminder</th>
                                <th class="px-4 py-3">Delivery</th>
                                <th class="px-4 py-3">Last Result</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="document in documents" :key="document.id" class="hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ document.employeeCode ? `${document.employeeCode} - ` : '' }}{{ document.employeeName }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">{{ document.employeeProfession || document.employeeType }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ document.categoryName }}</div>
                                    <div class="text-xs text-muted-foreground">{{ document.documentNumber || 'No document number' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>Expires: {{ document.expiryDateLabel }}</div>
                                    <div class="text-xs text-muted-foreground">Issued: {{ document.issueDate || '-' }}</div>
                                    <span class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-xs" :class="statusClass(document.status)">
                                        {{ statusLabel(document) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs"
                                        :class="
                                            document.notificationEnabled
                                                ? 'border-green-600/30 bg-green-600/10 text-green-700'
                                                : 'text-muted-foreground'
                                        "
                                        @click="toggleNotification(document)"
                                    >
                                        <Bell v-if="document.notificationEnabled" class="size-3.5" /><BellOff v-else class="size-3.5" />
                                        {{ document.notificationEnabled ? `Daily from ${document.effectiveReminderDays} days` : 'Stopped' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div>{{ document.emailEnabled ? `Email: ${document.notificationEmail}` : 'Email off' }}</div>
                                    <div>{{ document.whatsappEnabled ? `WhatsApp: ${document.whatsappNumber}` : 'WhatsApp off' }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div v-if="document.lastNotifications.length">
                                        <div v-for="log in document.lastNotifications.slice(0, 2)" :key="`${log.channel}-${log.date}`">
                                            {{ log.channel }} · {{ log.status }} · {{ log.date }}
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground">Not sent yet</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button v-if="document.fileAvailable" as-child size="icon" variant="ghost">
                                            <Link :href="`/employee-documents/${document.id}/download`"><Download class="size-4" /></Link>
                                        </Button>
                                        <Button size="icon" variant="ghost" @click="openDocument(document)"><Pencil class="size-4" /></Button>
                                        <Button size="icon" variant="ghost" class="text-destructive" @click="removeDocument(document)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination.total" class="flex flex-col gap-2 border-t px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-muted-foreground">Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</span>
                    <div class="flex gap-2">
                        <Button variant="outline" :disabled="pagination.currentPage <= 1" @click="reload(pagination.currentPage - 1)"
                            >Previous</Button
                        >
                        <Button
                            variant="outline"
                            :disabled="pagination.currentPage >= pagination.lastPage"
                            @click="reload(pagination.currentPage + 1)"
                            >Next</Button
                        >
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showDocumentForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" @click.self="closeDocument">
            <section class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-background shadow-xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-background p-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ editingId ? 'Edit Employee Document' : 'Add Employee Document' }}</h2>
                        <p class="text-xs text-muted-foreground">Files are stored privately and require an authenticated download.</p>
                    </div>
                    <Button size="icon" variant="ghost" @click="closeDocument"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="saveDocument">
                    <div class="grid gap-1.5">
                        <Label>Employee *</Label>
                        <div ref="formEmployeeDropdownRef" class="relative min-w-0">
                            <button
                                type="button"
                                class="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 text-left text-sm"
                                @click="formEmployeeOpen = !formEmployeeOpen"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ selectedFormEmployee?.label || 'Select employee' }}</span>
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                            </button>
                            <div
                                v-if="formEmployeeOpen"
                                class="absolute left-0 right-0 top-full z-40 mt-1 rounded-md border bg-background p-2 shadow-xl"
                            >
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="formEmployeeSearch"
                                        type="search"
                                        class="pl-9"
                                        placeholder="Search by code, name, or profession"
                                    />
                                </div>
                                <div class="mt-2 max-h-64 overflow-y-auto rounded-md border">
                                    <button
                                        v-for="employee in filteredFormEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-center gap-2 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60"
                                        :class="documentForm.employee_id === String(employee.id) ? 'bg-primary/10' : ''"
                                        @click="selectFormEmployee(employee)"
                                    >
                                        <CheckCircle2
                                            class="size-4 shrink-0"
                                            :class="documentForm.employee_id === String(employee.id) ? 'text-primary' : 'text-transparent'"
                                        />
                                        <span class="min-w-0 truncate">{{ employee.label }}</span>
                                    </button>
                                    <div v-if="!filteredFormEmployees.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                        No employees found.
                                    </div>
                                </div>
                            </div>
                            <input v-model="documentForm.employee_id" type="hidden" />
                        </div>
                        <InputError :message="documentForm.errors.employee_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Document Category *</Label>
                        <select v-model="documentForm.document_category_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Select category</option>
                            <option v-for="category in activeCategories" :key="category.id" :value="String(category.id)">
                                {{ category.name }}
                            </option></select
                        ><InputError :message="documentForm.errors.document_category_id" />
                    </div>
                    <div class="grid gap-1.5"><Label>Document Number</Label><Input v-model="documentForm.document_number" /></div>
                    <div class="grid gap-1.5">
                        <Label>Document File</Label
                        ><Input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            @change="documentForm.document_file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <InputError :message="documentForm.errors.document_file" />
                    </div>
                    <div class="grid gap-1.5"><Label>Issue / Start Date</Label><Input v-model="documentForm.issue_date" type="date" /></div>
                    <div class="grid gap-1.5">
                        <Label>Expiry / End Date *</Label><Input v-model="documentForm.expiry_date" type="date" /><InputError
                            :message="documentForm.errors.expiry_date"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Reminder Starts (days before expiry)</Label>
                        <Input v-model="documentForm.reminder_days" type="number" min="0" max="365" placeholder="Leave blank for category default" />
                        <p class="text-xs text-muted-foreground">After this point, enabled channels send once every day until stopped.</p>
                    </div>
                    <div class="grid gap-2">
                        <label class="flex items-center gap-2 rounded-md border p-3 text-sm"
                            ><input v-model="documentForm.notification_enabled" type="checkbox" />Enable this document's notifications</label
                        >
                    </div>
                    <div class="grid gap-2 rounded-md border p-3">
                        <label class="flex items-center gap-2 text-sm"
                            ><input v-model="documentForm.email_enabled" type="checkbox" />Send daily email</label
                        >
                        <Input
                            v-model="documentForm.notification_email"
                            type="email"
                            placeholder="alerts@example.com"
                            :disabled="!documentForm.email_enabled"
                        />
                        <InputError :message="documentForm.errors.notification_email" />
                    </div>
                    <div class="grid gap-2 rounded-md border p-3">
                        <label class="flex items-center gap-2 text-sm"
                            ><input v-model="documentForm.whatsapp_enabled" type="checkbox" />Send daily WhatsApp template</label
                        >
                        <Input v-model="documentForm.whatsapp_number" placeholder="+971501234567" :disabled="!documentForm.whatsapp_enabled" />
                        <InputError :message="documentForm.errors.whatsapp_number" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Notes</Label>
                        <textarea v-model="documentForm.notes" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 md:col-span-2">
                        <Button type="button" variant="outline" @click="closeDocument">Cancel</Button>
                        <Button :disabled="documentForm.processing">{{ documentForm.processing ? 'Saving...' : 'Save Document' }}</Button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="showCategories" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" @click.self="showCategories = false">
            <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-background shadow-xl">
                <div class="flex items-center justify-between border-b p-4">
                    <h2 class="text-lg font-semibold">Document Categories</h2>
                    <Button size="icon" variant="ghost" @click="showCategories = false"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-3 border-b p-4 md:grid-cols-[1fr_160px_auto_auto]" @submit.prevent="saveCategory">
                    <div><Label>Name *</Label><Input v-model="categoryForm.name" /><InputError :message="categoryForm.errors.name" /></div>
                    <div><Label>Reminder Days *</Label><Input v-model="categoryForm.default_reminder_days" type="number" min="0" max="365" /></div>
                    <label class="flex items-end gap-2 pb-2 text-sm"><input v-model="categoryForm.is_active" type="checkbox" />Active</label>
                    <Button class="self-end">{{ editingCategoryId ? 'Update' : 'Add Category' }}</Button>
                </form>
                <div class="divide-y">
                    <div v-for="category in categories" :key="category.id" class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-medium">{{ category.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ category.default_reminder_days }} days · {{ category.documents_count }} document(s) ·
                                {{ category.is_active ? 'Active' : 'Inactive' }}
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button size="icon" variant="ghost" @click="editCategory(category)"><Pencil class="size-4" /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-destructive"
                                :disabled="category.documents_count > 0"
                                @click="removeCategory(category)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showWhatsApp" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4" @click.self="showWhatsApp = false">
            <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-background shadow-xl">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="text-lg font-semibold">Meta WhatsApp Cloud API</h2>
                        <p class="text-xs text-muted-foreground">Official individual-number template messaging. No WhatsApp Web automation.</p>
                    </div>
                    <Button size="icon" variant="ghost" @click="showWhatsApp = false"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="saveWhatsApp">
                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm md:col-span-2"
                        ><input v-model="whatsappForm.whatsapp_enabled" type="checkbox" />Enable WhatsApp reminders globally</label
                    >
                    <div class="grid gap-1.5">
                        <Label>Graph API Version</Label><Input v-model="whatsappForm.whatsapp_graph_version" placeholder="v23.0" /><InputError
                            :message="whatsappForm.errors.whatsapp_graph_version"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Phone Number ID</Label><Input v-model="whatsappForm.whatsapp_phone_number_id" /><InputError
                            :message="whatsappForm.errors.whatsapp_phone_number_id"
                        />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Permanent Access Token</Label>
                        <Input
                            v-model="whatsappForm.whatsapp_access_token"
                            type="password"
                            :placeholder="whatsappSettings.tokenConfigured ? 'Token configured — leave blank to keep it' : 'Enter access token'"
                        />
                        <InputError :message="whatsappForm.errors.whatsapp_access_token" />
                    </div>
                    <div class="grid gap-1.5"><Label>Approved Template Name</Label><Input v-model="whatsappForm.whatsapp_template_name" /></div>
                    <div class="grid gap-1.5">
                        <Label>Template Language</Label><Input v-model="whatsappForm.whatsapp_template_language" placeholder="en" />
                    </div>
                    <div class="rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground md:col-span-2">
                        The approved template body must contain four variables in this order: employee, document category, expiry date, and expiry
                        status. Meta may charge for business-initiated template messages.
                    </div>
                    <div class="flex justify-end gap-2 md:col-span-2">
                        <Button type="button" variant="outline" @click="showWhatsApp = false">Cancel</Button>
                        <Button :disabled="whatsappForm.processing">{{ whatsappForm.processing ? 'Saving...' : 'Save WhatsApp Settings' }}</Button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
