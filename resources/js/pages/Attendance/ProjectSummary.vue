<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { overtimeLabel } from '@/lib/overtime';
import { Head, Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';
import ProjectSummaryHistory from './components/ProjectSummaryHistory.vue';

interface Summary {
    workedDays: number;
    persons: number;
    labourDays: number;
    overtimeHours: number;
    basicCost: number;
    overtimeCost: number;
    totalCost: number;
    missingPayroll: number;
}
interface ProjectRow extends Summary {
    projectId: number;
    code: string | null;
    name: string;
    typeLabel: string;
}
const props = defineProps<{
    report: { filters: { projectIds: number[]; from: string; to: string }; rows: ProjectRow[]; totals: Summary & { projects: number } };
    projects: { id: number; project_code: string | null; name: string; type: string }[];
    errors?: Record<string, string>;
}>();
const from = ref(props.report.filters.from);
const to = ref(props.report.filters.to);
const selectedIds = ref([...props.report.filters.projectIds]);
const search = ref('');
const historyOpen = ref(false);
const historyProject = ref<ProjectRow | null>(null);
function openHistory(row: ProjectRow) {
    historyProject.value = row;
    historyOpen.value = true;
}
const picker = ref<HTMLElement | null>(null);
const pickerOpen = ref(false);
onClickOutside(picker, () => {
    pickerOpen.value = false;
});
const visibleProjects = computed(() =>
    props.projects.filter((p) => `${p.project_code ?? ''} ${p.name}`.toLowerCase().includes(search.value.trim().toLowerCase())),
);
const allSelected = computed(() => props.projects.length > 0 && selectedIds.value.length === props.projects.length);
function toggleAll() {
    selectedIds.value = allSelected.value ? [] : props.projects.map((p) => p.id);
}
function apply() {
    pickerOpen.value = false;
    router.get('/attendance/project-summary', { from: from.value, to: to.value, project_ids: selectedIds.value });
}
const exportQuery = computed(() => {
    const params = new URLSearchParams({ from: props.report.filters.from, to: props.report.filters.to });
    props.report.filters.projectIds.forEach((id) => params.append('project_ids[]', String(id)));
    return params.toString();
});
const money = (value: number) => new Intl.NumberFormat('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
const breadcrumbs = [
    { title: 'Attendance', href: '/attendance' },
    { title: 'Project Summary', href: '/attendance/project-summary' },
];
const headings = ['Worked Days', 'Persons', 'Labour Days', 'Total Overtime', 'Salary Cost (AED)', 'OT Cost (AED)', 'Total Cost (AED)'];
</script>

<template>
    <Head title="Project Attendance Summary" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Project Attendance &amp; Labour Cost Summary</h1>
                <p class="mt-1 text-sm text-muted-foreground">Compare attendance and salary costs across selected projects.</p>
            </div>
            <form class="rounded-xl border bg-card p-4" @submit.prevent="apply">
                <div class="mb-4 flex flex-wrap gap-1 text-sm">
                    <Link :href="`/attendance/statement?mode=type&from=${from}&to=${to}`" class="rounded border px-3 py-2">By Employee Type</Link>
                    <Link :href="`/attendance/statement?mode=employee&from=${from}&to=${to}`" class="rounded border px-3 py-2">By Employee</Link>
                    <Link :href="`/attendance/statement?mode=project&from=${from}&to=${to}`" class="rounded border px-3 py-2">By Project</Link>
                    <span class="rounded bg-primary px-3 py-2 text-primary-foreground">Project Summary</span>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div ref="picker" class="relative grid w-full gap-1.5 text-sm sm:w-96" @keydown.esc="pickerOpen = false">
                        <span id="project-picker-label">Projects</span>
                        <button
                            type="button"
                            aria-labelledby="project-picker-label project-selection"
                            :aria-expanded="pickerOpen"
                            aria-controls="project-options"
                            class="flex h-10 items-center justify-between rounded-md border bg-background px-3"
                            @click="pickerOpen = !pickerOpen"
                        >
                            <span id="project-selection">{{
                                allSelected ? 'All Projects' : selectedIds.length ? `${selectedIds.length} projects selected` : 'Select projects'
                            }}</span
                            ><span aria-hidden="true">⌄</span>
                        </button>
                        <div
                            v-if="pickerOpen"
                            id="project-options"
                            class="absolute left-0 top-full z-40 mt-1 w-full rounded-lg border bg-popover text-popover-foreground shadow-lg"
                        >
                            <div class="border-b p-3">
                                <Input v-model="search" placeholder="Search project name or code" aria-label="Search projects" />
                            </div>
                            <label class="flex cursor-pointer items-center gap-3 border-b p-3"
                                ><input
                                    type="checkbox"
                                    :checked="allSelected"
                                    :indeterminate="selectedIds.length > 0 && !allSelected"
                                    class="h-4 w-4"
                                    @change="toggleAll"
                                />Select All Projects ({{ projects.length }})</label
                            >
                            <div class="max-h-64 overflow-y-auto">
                                <label
                                    v-for="project in visibleProjects"
                                    :key="project.id"
                                    class="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-muted"
                                    ><input v-model="selectedIds" type="checkbox" :value="project.id" class="h-4 w-4 shrink-0" />{{
                                        project.project_code ? `${project.project_code} - ` : ''
                                    }}{{ project.name }}</label
                                >
                                <p v-if="!visibleProjects.length" class="p-3 text-muted-foreground">No projects found.</p>
                            </div>
                            <div class="border-t p-2"><Button type="button" class="w-full" @click="apply">Show Selected Projects</Button></div>
                        </div>
                    </div>
                    <label class="grid gap-1.5 text-sm">From<Input v-model="from" type="date" required /></label>
                    <label class="grid gap-1.5 text-sm">To<Input v-model="to" type="date" :min="from" required /></label>
                    <Button type="submit">Show</Button>
                </div>
                <p v-for="(error, key) in errors" :key="key" role="alert" class="mt-2 text-sm text-red-600">{{ error }}</p>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4">
                <div>
                    <p class="font-semibold">{{ report.filters.from }} to {{ report.filters.to }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ report.totals.projects }} projects · {{ report.totals.persons }} unique persons ·
                        {{ overtimeLabel(report.totals.overtimeHours) }} overtime
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline"><a :href="`/attendance/project-summary/export?${exportQuery}`">Excel</a></Button
                    ><Button as-child variant="outline"
                        ><a :href="`/attendance/project-summary/print?${exportQuery}`" target="_blank" rel="noreferrer">Print / PDF</a></Button
                    >
                </div>
            </div>
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                This report includes salary information. Keep it internal. Costs use current payroll rates and include salary plus overtime only.
            </p>
            <p v-if="report.totals.missingPayroll" role="status" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                {{ report.totals.missingPayroll }} person(s) have no payroll settings. Their cost is zero, so the reported cost is incomplete.
            </p>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th scope="col" class="min-w-60 px-4 py-3 text-left">Project</th>
                            <th v-for="heading in headings" :key="heading" scope="col" class="min-w-28 px-3 py-3 text-right">{{ heading }}</th>
                            <th scope="col" class="px-3 py-3 text-right">History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.rows" :key="row.projectId" class="border-t">
                            <th scope="row" class="px-4 py-3 text-left font-medium">
                                <Link
                                    :href="`/attendance/statement?mode=project&project_id=${row.projectId}&from=${report.filters.from}&to=${report.filters.to}&layout=grid`"
                                    class="hover:underline"
                                    >{{ row.code ? `${row.code} - ` : '' }}{{ row.name }}</Link
                                >
                                <p class="mt-1 text-xs font-normal text-muted-foreground">{{ row.typeLabel }}</p>
                                <p v-if="row.missingPayroll" class="mt-1 text-xs font-normal text-red-600">
                                    Incomplete cost: {{ row.missingPayroll }} missing payroll
                                </p>
                            </th>
                            <td class="px-3 py-3 text-right tabular-nums">{{ row.workedDays }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ row.persons }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ row.labourDays }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ overtimeLabel(row.overtimeHours) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ money(row.basicCost) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ money(row.overtimeCost) }}</td>
                            <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ money(row.totalCost) }}</td>
                            <td class="px-3 py-3 text-right">
                                <Button type="button" variant="outline" :aria-label="`Employee history for ${row.name}`" @click="openHistory(row)"
                                    >Employee History</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!report.rows.length">
                            <td colspan="9" class="p-10 text-center text-muted-foreground">Select one or more projects and click Show.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.rows.length" class="border-t-2 bg-muted font-semibold">
                        <tr>
                            <th scope="row" class="px-4 py-3 text-left">Totals (persons unique)</th>
                            <td class="px-3 py-3 text-right">{{ report.totals.workedDays }}</td>
                            <td class="px-3 py-3 text-right">{{ report.totals.persons }}</td>
                            <td class="px-3 py-3 text-right">{{ report.totals.labourDays }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ overtimeLabel(report.totals.overtimeHours) }}</td>
                            <td class="px-3 py-3 text-right">{{ money(report.totals.basicCost) }}</td>
                            <td class="px-3 py-3 text-right">{{ money(report.totals.overtimeCost) }}</td>
                            <td class="px-3 py-3 text-right">{{ money(report.totals.totalCost) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-xs leading-relaxed text-muted-foreground">
                Worked Days counts distinct dates with work or overtime on each project; the total adds project dates. Persons counts each worker once
                per project and once across all projects in the total. Labour Days adds full/half days on the main project. Salary cost uses the daily
                rate (monthly salary ÷ 30 for Fixed 30 Days); OT cost uses daily rate ÷ standard hours × overtime hours, or zero when overtime pay is
                disabled. Overtime assigned to another project belongs to that project. Overhead, purchases and expenses are excluded.
            </p>
        </div>
        <ProjectSummaryHistory v-model:open="historyOpen" :project="historyProject" :from="report.filters.from" :to="report.filters.to" />
    </AppLayout>
</template>
