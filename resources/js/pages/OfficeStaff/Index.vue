<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SortableHeader from '@/components/SortableHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StaffMember {
    id: number;
    code: string;
    name: string;
    designation: string | null;
    staffType: string;
    staffTypeLabel: string;
    status: string;
    statusLabel: string;
    username: string | null;
}

const props = defineProps<{
    staff: StaffMember[];
    staffTypes: Record<string, string>;
    statuses: Record<string, string>;
    nextCode: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Office Staff', href: '/office-staff' }];

const editingId = ref<number | null>(null);
const search = ref('');
type SortKey = 'code' | 'name' | 'designation' | 'staffType' | 'status' | 'username';
const sortKey = ref<SortKey>('code');
const sortDirection = ref<'asc' | 'desc'>('asc');

const createForm = useForm({
    code: props.nextCode,
    name: '',
    designation: '',
    staff_type: 'on_site',
    status: 'active',
});

const editForm = useForm({
    code: '',
    name: '',
    username: '',
    designation: '',
    staff_type: 'on_site',
    status: 'active',
});

const filteredStaff = computed(() => {
    const query = search.value.trim().toLowerCase();
    const rows = query
        ? props.staff.filter((member) =>
              [
                  member.code,
                  member.name,
                  member.designation ?? '',
                  member.staffTypeLabel,
                  member.statusLabel,
                  member.username ?? '',
              ].some((value) => value.toLowerCase().includes(query)),
          )
        : props.staff;

    return [...rows].sort((first, second) => {
        const firstValue =
            sortKey.value === 'staffType'
                ? first.staffTypeLabel
                : sortKey.value === 'status'
                  ? first.statusLabel
                  : (first[sortKey.value] ?? '');
        const secondValue =
            sortKey.value === 'staffType'
                ? second.staffTypeLabel
                : sortKey.value === 'status'
                  ? second.statusLabel
                  : (second[sortKey.value] ?? '');
        const comparison = String(firstValue).localeCompare(String(secondValue), undefined, { numeric: true, sensitivity: 'base' });

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });
});

const sortStaff = (key: string) => {
    const nextKey = key as SortKey;

    if (sortKey.value === nextKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextKey;
    sortDirection.value = 'asc';
};

const createStaff = () => {
    createForm.post('/office-staff', {
        preserveScroll: true,
        onSuccess: (page) => {
            createForm.reset('name', 'designation');
            createForm.code = String(page.props.nextCode ?? props.nextCode);
        },
    });
};

const startEditing = (member: StaffMember) => {
    editingId.value = member.id;
    editForm.clearErrors();
    editForm.code = member.code;
    editForm.name = member.name;
    editForm.username = member.username ?? '';
    editForm.designation = member.designation ?? '';
    editForm.staff_type = member.staffType;
    editForm.status = member.status;
};

const cancelEditing = () => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const updateStaff = (member: StaffMember) => {
    editForm.put(`/office-staff/${member.id}`, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const deleteStaff = (member: StaffMember) => {
    if (!confirm(`Delete ${member.code} - ${member.name}?`)) {
        return;
    }

    router.delete(`/office-staff/${member.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Office Staff" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Office Staff</h1>
                <p class="mt-1 text-sm text-muted-foreground">Create staff logins and manage remote or on-site attendance access.</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="createStaff">
                <div class="grid gap-4 xl:grid-cols-[130px_minmax(0,1fr)_minmax(0,1fr)_180px_150px_auto] xl:items-start">
                    <div class="grid min-w-0 gap-2">
                        <Label for="staff-code">Code</Label>
                        <Input id="staff-code" v-model="createForm.code" type="text" inputmode="numeric" placeholder="101" />
                        <InputError :message="createForm.errors.code" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="staff-name">Name</Label>
                        <Input id="staff-name" v-model="createForm.name" type="text" placeholder="Staff name" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="staff-designation">Designation</Label>
                        <Input id="staff-designation" v-model="createForm.designation" type="text" placeholder="Accountant, HR, etc." />
                        <InputError :message="createForm.errors.designation" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="staff-type">Staff Type</Label>
                        <select
                            id="staff-type"
                            v-model="createForm.staff_type"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="(label, type) in staffTypes" :key="type" :value="type">{{ label }}</option>
                        </select>
                        <InputError :message="createForm.errors.staff_type" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="staff-status">Status</Label>
                        <select
                            id="staff-status"
                            v-model="createForm.status"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="(label, status) in statuses" :key="status" :value="status">{{ label }}</option>
                        </select>
                        <InputError :message="createForm.errors.status" />
                    </div>
                    <Button class="mt-0 whitespace-nowrap xl:mt-8" type="submit" :disabled="createForm.processing">
                        <Plus class="size-4" />
                        Add Staff
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Staff List</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredStaff.length }} of {{ staff.length }} staff members</p>
                    </div>
                    <div class="relative w-full md:max-w-md">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search by code, name, username, designation, or type" />
                    </div>
                </div>

                <div v-if="staff.length === 0" class="flex min-h-56 items-center justify-center text-sm text-muted-foreground">No office staff added yet.</div>
                <div v-else-if="filteredStaff.length === 0" class="flex min-h-56 items-center justify-center text-sm text-muted-foreground">No staff match your search.</div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[10%] px-4 py-3 font-medium">
                                    <SortableHeader label="Code" column="code" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortStaff" />
                                </th>
                                <th class="w-[22%] px-4 py-3 font-medium">
                                    <SortableHeader label="Name" column="name" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortStaff" />
                                </th>
                                <th class="w-[20%] px-4 py-3 font-medium">
                                    <SortableHeader label="Username" column="username" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortStaff" />
                                </th>
                                <th class="w-[20%] px-4 py-3 font-medium">
                                    <SortableHeader label="Designation" column="designation" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortStaff" />
                                </th>
                                <th class="w-[14%] px-4 py-3 font-medium">
                                    <SortableHeader label="Type" column="staffType" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortStaff" />
                                </th>
                                <th class="w-[12%] px-4 py-3 font-medium">
                                    <SortableHeader label="Status" column="status" :sort-key="sortKey" :sort-direction="sortDirection" @sort="sortStaff" />
                                </th>
                                <th class="w-[120px] px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in filteredStaff" :key="member.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <Input v-if="editingId === member.id" v-model="editForm.code" type="text" inputmode="numeric" />
                                    <span v-else class="block truncate font-medium">{{ member.code }}</span>
                                    <InputError v-if="editingId === member.id" :message="editForm.errors.code" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingId === member.id" v-model="editForm.name" type="text" />
                                    <span v-else class="block truncate font-medium">{{ member.code }} - {{ member.name }}</span>
                                    <InputError v-if="editingId === member.id" :message="editForm.errors.name" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingId === member.id" v-model="editForm.username" type="text" />
                                    <span v-else class="block truncate">{{ member.username }}</span>
                                    <InputError v-if="editingId === member.id" :message="editForm.errors.username" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingId === member.id" v-model="editForm.designation" type="text" />
                                    <span v-else class="block truncate">{{ member.designation || '-' }}</span>
                                    <InputError v-if="editingId === member.id" :message="editForm.errors.designation" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="editingId === member.id"
                                        v-model="editForm.staff_type"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="(label, type) in staffTypes" :key="type" :value="type">{{ label }}</option>
                                    </select>
                                    <span v-else class="inline-flex rounded-md border px-2 py-1 text-xs font-medium">{{ member.staffTypeLabel }}</span>
                                    <InputError v-if="editingId === member.id" :message="editForm.errors.staff_type" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="editingId === member.id"
                                        v-model="editForm.status"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="(label, status) in statuses" :key="status" :value="status">{{ label }}</option>
                                    </select>
                                    <span v-else class="inline-flex rounded-md border px-2 py-1 text-xs font-medium">{{ member.statusLabel }}</span>
                                    <InputError v-if="editingId === member.id" :message="editForm.errors.status" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <template v-if="editingId === member.id">
                                            <Button size="icon" type="button" :disabled="editForm.processing" @click="updateStaff(member)">
                                                <Check class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="outline" @click="cancelEditing">
                                                <X class="size-4" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <Button size="icon" type="button" variant="outline" @click="startEditing(member)">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="destructive" @click="deleteStaff(member)">
                                                <Trash2 class="size-4" />
                                            </Button>
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
