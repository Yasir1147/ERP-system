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

interface UserRow {
    id: number;
    name: string;
    username: string;
    email: string;
    role: string;
}

const props = defineProps<{
    users: UserRow[];
    roles: Record<string, string>;
    currentUserId: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: '/users' }];
const search = ref('');
const editingUserId = ref<number | null>(null);

const createForm = useForm({
    name: '',
    username: '',
    email: '',
    role: 'attendance_user',
    password: '',
    password_confirmation: '',
});

const editForm = useForm({
    name: '',
    username: '',
    email: '',
    role: 'attendance_user',
    password: '',
    password_confirmation: '',
});

const filteredUsers = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) return props.users;

    return props.users.filter((user) =>
        [user.name, user.username, user.email, props.roles[user.role]].filter(Boolean).some((value) => value.toLowerCase().includes(query)),
    );
});

const createUser = () => {
    createForm.post('/users', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name', 'username', 'email', 'password', 'password_confirmation'),
    });
};

const startEditing = (user: UserRow) => {
    editingUserId.value = user.id;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.username = user.username;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.password = '';
    editForm.password_confirmation = '';
};

const cancelEditing = () => {
    editingUserId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const updateUser = (user: UserRow) => {
    editForm.put(`/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const deleteUser = (user: UserRow) => {
    if (!confirm(`Delete ${user.name}?`)) return;

    router.delete(`/users/${user.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Users</h1>
                <p class="mt-1 text-sm text-muted-foreground">Create users and assign admin or attendance-only access.</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="createUser">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_220px]">
                    <div class="grid min-w-0 gap-2">
                        <Label for="user-name">Name</Label>
                        <Input id="user-name" v-model="createForm.name" type="text" placeholder="User name" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="user-username">Username</Label>
                        <Input id="user-username" v-model="createForm.username" type="text" placeholder="username" />
                        <InputError :message="createForm.errors.username" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="user-email">Email</Label>
                        <Input id="user-email" v-model="createForm.email" type="email" placeholder="user@example.com" />
                        <InputError :message="createForm.errors.email" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="user-role">Role</Label>
                        <select
                            id="user-role"
                            v-model="createForm.role"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="(label, role) in roles" :key="role" :value="role">{{ label }}</option>
                        </select>
                        <InputError :message="createForm.errors.role" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="user-password">Password</Label>
                        <Input id="user-password" v-model="createForm.password" type="password" placeholder="Password" />
                        <InputError :message="createForm.errors.password" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="user-password-confirmation">Confirm Password</Label>
                        <Input id="user-password-confirmation" v-model="createForm.password_confirmation" type="password" placeholder="Confirm password" />
                    </div>
                    <Button class="mt-0 whitespace-nowrap lg:mt-8" type="submit" :disabled="createForm.processing">
                        <Plus class="size-4" />
                        Add User
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">User List</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredUsers.length }} of {{ users.length }} users</p>
                    </div>
                    <div class="relative w-full md:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search users" />
                    </div>
                </div>

                <div v-if="users.length === 0" class="flex min-h-56 items-center justify-center text-sm text-muted-foreground">
                    No users added yet.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[17%] px-4 py-3 font-medium">Name</th>
                                <th class="w-[17%] px-4 py-3 font-medium">Username</th>
                                <th class="w-[23%] px-4 py-3 font-medium">Email</th>
                                <th class="w-[16%] px-4 py-3 font-medium">Role</th>
                                <th class="w-[17%] px-4 py-3 font-medium">Password</th>
                                <th class="w-[120px] px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in filteredUsers" :key="user.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <Input v-if="editingUserId === user.id" v-model="editForm.name" type="text" />
                                    <span v-else class="block truncate font-medium">{{ user.name }}</span>
                                    <InputError v-if="editingUserId === user.id" :message="editForm.errors.name" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingUserId === user.id" v-model="editForm.username" type="text" />
                                    <span v-else class="block truncate">{{ user.username }}</span>
                                    <InputError v-if="editingUserId === user.id" :message="editForm.errors.username" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <Input v-if="editingUserId === user.id" v-model="editForm.email" type="email" />
                                    <span v-else class="block truncate">{{ user.email }}</span>
                                    <InputError v-if="editingUserId === user.id" :message="editForm.errors.email" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="editingUserId === user.id"
                                        v-model="editForm.role"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="(label, role) in roles" :key="role" :value="role">{{ label }}</option>
                                    </select>
                                    <span v-else class="inline-flex rounded-md border px-2 py-1 text-xs font-medium">{{ roles[user.role] }}</span>
                                    <InputError v-if="editingUserId === user.id" :message="editForm.errors.role" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="editingUserId === user.id" class="grid gap-2">
                                        <Input v-model="editForm.password" type="password" placeholder="New password optional" />
                                        <Input v-model="editForm.password_confirmation" type="password" placeholder="Confirm new password" />
                                        <InputError :message="editForm.errors.password" />
                                    </div>
                                    <span v-else class="text-muted-foreground">Hidden</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <template v-if="editingUserId === user.id">
                                            <Button size="icon" type="button" :disabled="editForm.processing" @click="updateUser(user)">
                                                <Check class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="outline" @click="cancelEditing">
                                                <X class="size-4" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <Button size="icon" type="button" variant="outline" @click="startEditing(user)">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="destructive" :disabled="user.id === currentUserId" @click="deleteUser(user)">
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
