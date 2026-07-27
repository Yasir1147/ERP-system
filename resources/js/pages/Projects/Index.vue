<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, Eye, MapPin, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Project {
    id: number;
    projectCode: string | null;
    name: string;
    clientName: string | null;
    location: string | null;
    projectManager: string | null;
    status: string;
    type: string;
    startDate: string | null;
    startDateValue: string | null;
    expectedEndDate: string | null;
    expectedEndDateValue: string | null;
    contractValue: number | null;
    costBudget: number | null;
    progressPercentage: number;
    description: string | null;
    healthStatus: string;
    healthLabel: string;
    totalCost: number;
    budgetRemaining: number | null;
    budgetUsedPercent: number | null;
}

const props = defineProps<{
    projects: Project[];
    statuses: string[];
    projectType: string;
    projectTypeLabel: string;
}>();

const page = usePage();
const editingProjectId = ref<number | null>(null);
const showForm = ref(false);
const search = ref('');
const statusFilter = ref('all');

const emptyForm = () => ({
    project_code: '',
    name: '',
    client_name: '',
    location: '',
    project_manager: '',
    status: 'pending',
    type: props.projectType,
    start_date: '',
    expected_end_date: '',
    contract_value: null as number | null,
    cost_budget: null as number | null,
    progress_percentage: 0,
    description: '',
});

const form = useForm(emptyForm());

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.projectTypeLabel, href: `/projects/${props.projectType}` },
];

const statusLabels = computed(() =>
    props.statuses.reduce<Record<string, string>>((labels, status) => {
        labels[status] = status.charAt(0).toUpperCase() + status.slice(1);
        return labels;
    }, {}),
);

const filteredProjects = computed(() => {
    const query = search.value.trim().toLowerCase();
    return props.projects.filter((project) => {
        const matchesStatus = statusFilter.value === 'all' || project.status === statusFilter.value;
        const matchesSearch =
            !query ||
            [project.projectCode, project.name, project.clientName, project.location, project.projectManager]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query));
        return matchesStatus && matchesSearch;
    });
});

const resetForm = () => {
    editingProjectId.value = null;
    showForm.value = false;
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
};

const openCreate = () => {
    resetForm();
    showForm.value = true;
};

const startEditing = (project: Project) => {
    editingProjectId.value = project.id;
    showForm.value = true;
    form.project_code = project.projectCode ?? '';
    form.name = project.name;
    form.client_name = project.clientName ?? '';
    form.location = project.location ?? '';
    form.project_manager = project.projectManager ?? '';
    form.status = project.status;
    form.type = project.type;
    form.start_date = project.startDateValue ?? '';
    form.expected_end_date = project.expectedEndDateValue ?? '';
    form.contract_value = project.contractValue;
    form.cost_budget = project.costBudget;
    form.progress_percentage = project.progressPercentage;
    form.description = project.description ?? '';
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const saveProject = () => {
    form.type = props.projectType;
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editingProjectId.value) {
        form.put(`/projects/${editingProjectId.value}`, options);
        return;
    }

    form.post('/projects', options);
};

const deleteProject = (project: Project) => {
    if (window.confirm(`Delete ${project.name}? Projects with linked records cannot be deleted.`)) {
        router.delete(`/projects/${project.id}`, { preserveScroll: true });
    }
};

const money = (value: number | null) => {
    if (value === null) return 'Not set';
    return new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(value);
};

const healthClass = (status: string) => {
    if (status === 'on_track' || status === 'completed') return 'border-green-600/30 bg-green-600/10 text-green-700';
    if (status === 'at_risk') return 'border-amber-600/30 bg-amber-600/10 text-amber-700';
    return 'border-red-600/30 bg-red-600/10 text-red-700';
};
</script>

<template>
    <Head title="Projects" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="project-module flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ projectTypeLabel }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Track project value, budget, progress and real-time actual cost.</p>
                </div>
                <Button type="button" @click="openCreate"><Plus class="size-4" />New Project</Button>
            </div>

            <div
                v-if="page.props.errors?.project"
                class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                {{ page.props.errors.project }}
            </div>

            <section v-if="showForm" class="rounded-xl border bg-card shadow-sm">
                <div class="flex items-start justify-between border-b p-4">
                    <div>
                        <h2 class="font-semibold">{{ editingProjectId ? 'Edit Project' : 'Create Project' }}</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Contract value is client revenue; cost budget is the planned project spending limit.
                        </p>
                    </div>
                    <Button type="button" size="icon" variant="ghost" @click="resetForm"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="saveProject">
                    <div class="grid gap-1.5">
                        <Label>Project Code</Label>
                        <Input v-model="form.project_code" placeholder="PRJ-1042" maxlength="50" />
                        <InputError :message="form.errors.project_code" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Project Name *</Label>
                        <Input v-model="form.name" placeholder="Project name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Status *</Label>
                        <select v-model="form.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Client Name</Label>
                        <Input v-model="form.client_name" placeholder="Client or company" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Location</Label>
                        <Input v-model="form.location" placeholder="Dubai, UAE" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Project Manager</Label>
                        <Input v-model="form.project_manager" placeholder="Manager name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Start Date</Label>
                        <Input v-model="form.start_date" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Expected End Date</Label>
                        <Input v-model="form.expected_end_date" type="date" />
                        <InputError :message="form.errors.expected_end_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Contract Value (AED)</Label>
                        <Input v-model.number="form.contract_value" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Cost Budget (AED)</Label>
                        <Input v-model.number="form.cost_budget" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <div class="flex items-center justify-between">
                            <Label>Project Progress</Label><span class="text-sm font-medium">{{ form.progress_percentage }}%</span>
                        </div>
                        <input v-model.number="form.progress_percentage" type="range" min="0" max="100" step="1" class="h-10 w-full accent-primary" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label>Description / Notes</Label>
                        <textarea v-model="form.description" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 md:col-span-2 xl:col-span-4">
                        <Button type="button" variant="outline" @click="resetForm">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save Project' }}</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border bg-card shadow-sm">
                <div class="grid gap-3 border-b p-4 md:grid-cols-[1fr_200px]">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="pl-9" placeholder="Search code, project, client, location or manager" />
                    </div>
                    <select v-model="statusFilter" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="all">All statuses</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ statusLabels[status] }}</option>
                    </select>
                </div>

                <div v-if="!filteredProjects.length" class="flex min-h-56 items-center justify-center text-sm text-muted-foreground">
                    No projects found.
                </div>
                <div v-else class="grid gap-4 p-4 lg:grid-cols-2 2xl:grid-cols-3">
                    <article v-for="project in filteredProjects" :key="project.id" class="rounded-xl border bg-background p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-muted-foreground">{{ project.projectCode || `Project ${project.id}` }}</p>
                                <h2 class="mt-1 truncate text-lg font-semibold">{{ project.name }}</h2>
                                <p class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                    <MapPin class="size-3.5" />{{ project.location || 'Location not set' }}
                                </p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-xs">{{ statusLabels[project.status] }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">Client</p>
                                <p class="truncate font-medium">{{ project.clientName || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Project Manager</p>
                                <p class="truncate font-medium">{{ project.projectManager || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Contract Value</p>
                                <p class="font-medium tabular-nums">{{ money(project.contractValue) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Actual Cost</p>
                                <p class="font-medium tabular-nums">{{ money(project.totalCost) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Cost Budget</p>
                                <p class="font-medium tabular-nums">{{ money(project.costBudget) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Budget Remaining</p>
                                <p
                                    class="font-medium tabular-nums"
                                    :class="project.budgetRemaining !== null && project.budgetRemaining < 0 ? 'text-red-700' : ''"
                                >
                                    {{ money(project.budgetRemaining) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
                            <span class="inline-flex items-center gap-1"
                                ><CalendarDays class="size-3.5" />{{ project.startDate || 'Start not set' }}</span
                            >
                            <span>{{ project.expectedEndDate || 'End not set' }}</span>
                        </div>
                        <div class="mt-3">
                            <div class="flex justify-between text-xs">
                                <span>Progress</span><strong>{{ project.progressPercentage }}%</strong>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-blue-600" :style="{ width: `${project.progressPercentage}%` }" />
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t pt-3">
                            <span class="rounded-full border px-2 py-1 text-xs" :class="healthClass(project.healthStatus)">{{
                                project.healthLabel
                            }}</span>
                            <div class="flex gap-1">
                                <Button as-child size="sm" variant="ghost">
                                    <Link :href="`/projects/overview?type=${project.type}&project_id=${project.id}`"
                                        ><Eye class="size-4" />Details</Link
                                    >
                                </Button>
                                <Button type="button" size="icon" variant="ghost" @click="startEditing(project)"><Pencil class="size-4" /></Button>
                                <Button type="button" size="icon" variant="ghost" class="text-destructive" @click="deleteProject(project)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
