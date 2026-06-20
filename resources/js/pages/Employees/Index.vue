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

const props = defineProps<{
    employees: Employee[];
    employeeType: string;
    employeeTypeLabel: string;
    employeeStatuses: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Employees',
        href: '/employees',
    },
    {
        title: props.employeeTypeLabel,
        href: `/employees/${props.employeeType}`,
    },
];

const editingEmployeeId = ref<number | null>(null);
const search = ref('');

const createForm = useForm({
    name: '',
    profession: '',
    type: props.employeeType,
    status: 'active',
});

const editForm = useForm({
    name: '',
    profession: '',
    type: props.employeeType,
    status: 'active',
});

const filteredEmployees = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.employees;
    }

    return props.employees.filter((employee) =>
        [employee.name, employee.profession, props.employeeStatuses[employee.status]].some((value) => value.toLowerCase().includes(query)),
    );
});

const createEmployee = () => {
    createForm.type = props.employeeType;

    createForm.post('/employees', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name', 'profession'),
    });
};

const startEditing = (employee: Employee) => {
    editingEmployeeId.value = employee.id;
    editForm.clearErrors();
    editForm.name = employee.name;
    editForm.profession = employee.profession;
    editForm.type = employee.type;
    editForm.status = employee.status;
};

const cancelEditing = () => {
    editingEmployeeId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const updateEmployee = (employee: Employee) => {
    editForm.type = props.employeeType;

    editForm.put(`/employees/${employee.id}`, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const deleteEmployee = (employee: Employee) => {
    if (!confirm(`Delete ${employee.name}?`)) {
        return;
    }

    router.delete(`/employees/${employee.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Employees" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ employeeTypeLabel }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Manage employee names and professions.</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="createEmployee">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_180px_auto] lg:items-start">
                    <div class="grid min-w-0 gap-2">
                        <Label for="employee-name">Name</Label>
                        <Input id="employee-name" v-model="createForm.name" type="text" placeholder="Employee name" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="employee-profession">Profession</Label>
                        <Input id="employee-profession" v-model="createForm.profession" type="text" placeholder="Profession" />
                        <InputError :message="createForm.errors.profession" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="employee-status">Status</Label>
                        <select
                            id="employee-status"
                            v-model="createForm.status"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="(label, status) in employeeStatuses" :key="status" :value="status">{{ label }}</option>
                        </select>
                        <InputError :message="createForm.errors.status" />
                    </div>
                    <Button class="mt-0 whitespace-nowrap lg:mt-8" type="submit" :disabled="createForm.processing">
                        <Plus class="size-4" />
                        Add Employee
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Employee List</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredEmployees.length }} of {{ employees.length }} employees</p>
                    </div>
                    <div class="relative w-full md:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search by name, profession, or status" />
                    </div>
                </div>

                <div v-if="employees.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No employees added yet.
                </div>

                <div v-else-if="filteredEmployees.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No employees match your search.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[35%] px-4 py-3 font-medium">Name</th>
                                <th class="w-[32%] px-4 py-3 font-medium">Profession</th>
                                <th class="w-[18%] px-4 py-3 font-medium">Status</th>
                                <th class="w-[120px] px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="employee in filteredEmployees" :key="employee.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <Input v-if="editingEmployeeId === employee.id" v-model="editForm.name" type="text" />
                                    <span v-else class="block truncate font-medium">{{ employee.name }}</span>
                                    <InputError v-if="editingEmployeeId === employee.id" :message="editForm.errors.name" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingEmployeeId === employee.id" v-model="editForm.profession" type="text" />
                                    <span v-else class="block truncate">{{ employee.profession }}</span>
                                    <InputError v-if="editingEmployeeId === employee.id" :message="editForm.errors.profession" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="editingEmployeeId === employee.id"
                                        v-model="editForm.status"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="(label, status) in employeeStatuses" :key="status" :value="status">{{ label }}</option>
                                    </select>
                                    <span v-else class="inline-flex rounded-md border px-2 py-1 text-xs font-medium">
                                        {{ employeeStatuses[employee.status] }}
                                    </span>
                                    <InputError v-if="editingEmployeeId === employee.id" :message="editForm.errors.status" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <template v-if="editingEmployeeId === employee.id">
                                            <Button size="icon" type="button" :disabled="editForm.processing" @click="updateEmployee(employee)">
                                                <Check class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="outline" @click="cancelEditing">
                                                <X class="size-4" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <Button size="icon" type="button" variant="outline" @click="startEditing(employee)">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="destructive" @click="deleteEmployee(employee)">
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
