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

interface Project {
    id: number;
    name: string;
    status: string;
    type: string;
}

const props = defineProps<{
    projects: Project[];
    statuses: string[];
    projectType: string;
    projectTypeLabel: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects',
    },
    {
        title: props.projectTypeLabel,
        href: `/projects/${props.projectType}`,
    },
];

const editingProjectId = ref<number | null>(null);
const search = ref('');

const createForm = useForm({
    name: '',
    status: 'pending',
    type: props.projectType,
});

const editForm = useForm({
    name: '',
    status: 'pending',
    type: props.projectType,
});

const statusLabels = computed(() =>
    props.statuses.reduce<Record<string, string>>((labels, status) => {
        labels[status] = status
            .split('-')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' ');

        return labels;
    }, {}),
);

const filteredProjects = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.projects;
    }

    return props.projects.filter((project) =>
        [project.name, project.status, statusLabels.value[project.status]]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(query)),
    );
});

const createProject = () => {
    createForm.type = props.projectType;

    createForm.post('/projects', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
};

const startEditing = (project: Project) => {
    editingProjectId.value = project.id;
    editForm.clearErrors();
    editForm.name = project.name;
    editForm.status = project.status;
    editForm.type = project.type;
};

const cancelEditing = () => {
    editingProjectId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const updateProject = (project: Project) => {
    editForm.type = props.projectType;

    editForm.put(`/projects/${project.id}`, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
};

const deleteProject = (project: Project) => {
    if (!confirm(`Delete ${project.name}?`)) {
        return;
    }

    router.delete(`/projects/${project.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Projects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ projectTypeLabel }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Manage project names and current status.</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="createProject">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto] lg:items-start">
                    <div class="grid min-w-0 gap-2">
                        <Label for="project-name">Project Name</Label>
                        <Input id="project-name" v-model="createForm.name" type="text" placeholder="Project name" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="project-status">Status</Label>
                        <select
                            id="project-status"
                            v-model="createForm.status"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] }}</option>
                        </select>
                        <InputError :message="createForm.errors.status" />
                    </div>
                    <Button class="mt-0 whitespace-nowrap lg:mt-8" type="submit" :disabled="createForm.processing">
                        <Plus class="size-4" />
                        Add Project
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-medium">Project List</h2>
                        <p class="text-sm text-muted-foreground">{{ filteredProjects.length }} of {{ projects.length }} projects</p>
                    </div>
                    <div class="relative w-full md:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" type="search" class="pl-9" placeholder="Search by project or status" />
                    </div>
                </div>

                <div v-if="projects.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No projects added yet.
                </div>

                <div v-else-if="filteredProjects.length === 0" class="flex min-h-56 items-center justify-center border-dashed text-sm text-muted-foreground">
                    No projects match your search.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] table-fixed text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="w-[55%] px-4 py-3 font-medium">Project Name</th>
                                <th class="w-[30%] px-4 py-3 font-medium">Status</th>
                                <th class="w-[120px] px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="project in filteredProjects" :key="project.id" class="border-b last:border-b-0">
                                <td class="px-4 py-3">
                                    <Input v-if="editingProjectId === project.id" v-model="editForm.name" type="text" />
                                    <span v-else class="block truncate font-medium">{{ project.name }}</span>
                                    <InputError v-if="editingProjectId === project.id" :message="editForm.errors.name" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="editingProjectId === project.id"
                                        v-model="editForm.status"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] }}</option>
                                    </select>
                                    <span v-else class="inline-flex rounded-md border px-2 py-1 text-xs font-medium capitalize">
                                        {{ statusLabels[project.status] }}
                                    </span>
                                    <InputError v-if="editingProjectId === project.id" :message="editForm.errors.status" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <template v-if="editingProjectId === project.id">
                                            <Button size="icon" type="button" :disabled="editForm.processing" @click="updateProject(project)">
                                                <Check class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="outline" @click="cancelEditing">
                                                <X class="size-4" />
                                            </Button>
                                        </template>
                                        <template v-else>
                                            <Button size="icon" type="button" variant="outline" @click="startEditing(project)">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button size="icon" type="button" variant="destructive" @click="deleteProject(project)">
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
