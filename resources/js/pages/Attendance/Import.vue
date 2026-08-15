<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, FileUp, Upload } from 'lucide-vue-next';
import { computed } from 'vue';

interface PreviewRow {
    line: number;
    date: string | null;
    sourceName: string;
    employeeCode: string | null;
    employeeName: string | null;
    projectName: string | null;
    status: string | null;
    action: string;
    errors: string[];
}

interface Preview {
    fatal: string | null;
    rows: PreviewRow[];
    summary: {
        total: number;
        create: number;
        skip: number;
        duplicate: number;
        error: number;
        firstDate: string | null;
        lastDate: string | null;
    };
    created?: number;
}

const props = defineProps<{
    preview: Preview | null;
    token: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Attendance', href: '/attendance' },
    { title: 'Import', href: '/attendance/import' },
];

const uploadForm = useForm({ file: null as File | null });
const confirmForm = useForm({ token: props.token ?? '' });

/// Only rows a person needs to act on. A run of hundreds of good rows does
/// not need reading; the problems do.
const attentionRows = computed(() => (props.preview?.rows ?? []).filter((row) => row.action !== 'create'));

const wasImported = computed(() => typeof props.preview?.created === 'number');

const upload = () => {
    uploadForm.post('/attendance/import/preview', { forceFormData: true, preserveScroll: true });
};

const confirm = () => {
    if (!props.token) return;
    confirmForm.token = props.token;
    confirmForm.post('/attendance/import', { preserveScroll: true });
};

const actionLabel = (action: string) =>
    ({ create: 'Will import', skip: 'Already recorded', duplicate: 'Duplicate in file', error: 'Error' })[action] ?? action;

const actionClass = (action: string) =>
    ({
        create: 'bg-green-600/10 text-green-700 border-green-600/30',
        skip: 'bg-slate-500/10 text-slate-600 border-slate-500/30',
        duplicate: 'bg-amber-600/10 text-amber-700 border-amber-600/30',
        error: 'bg-red-600/10 text-red-700 border-red-600/30',
    })[action] ?? 'bg-muted text-muted-foreground border-border';
</script>

<template>
    <Head title="Import Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Import Attendance</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Upload a reviewed workbook to record past attendance. Nothing is saved until you confirm.
                </p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border" @submit.prevent="upload">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="grid flex-1 gap-2">
                        <label class="text-sm font-medium" for="import-file">Workbook (.xlsx)</label>
                        <input
                            id="import-file"
                            type="file"
                            accept=".xlsx,.xls"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            @input="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <InputError :message="uploadForm.errors.file" />
                        <p class="text-xs text-muted-foreground">
                            The file needs an <b>Attendance</b> sheet and an <b>Employee Map</b> sheet with a code against every name.
                        </p>
                    </div>
                    <Button type="submit" :disabled="uploadForm.processing || !uploadForm.file">
                        <FileUp class="size-4" />
                        Check File
                    </Button>
                </div>
            </form>

            <div v-if="preview?.fatal" class="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                {{ preview.fatal }}
            </div>

            <template v-else-if="preview">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Rows in file</p>
                        <p class="mt-1 text-xl font-semibold">{{ preview.summary.total }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">{{ wasImported ? 'Imported' : 'Will import' }}</p>
                        <p class="mt-1 text-xl font-semibold text-green-700">
                            {{ wasImported ? preview.created : preview.summary.create }}
                        </p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Already recorded</p>
                        <p class="mt-1 text-xl font-semibold">{{ preview.summary.skip }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Duplicate in file</p>
                        <p class="mt-1 text-xl font-semibold">{{ preview.summary.duplicate }}</p>
                    </div>
                    <div class="rounded-md border p-3">
                        <p class="text-xs text-muted-foreground">Errors</p>
                        <p class="mt-1 text-xl font-semibold" :class="preview.summary.error ? 'text-red-700' : ''">
                            {{ preview.summary.error }}
                        </p>
                    </div>
                </div>

                <p v-if="preview.summary.firstDate" class="text-sm text-muted-foreground">
                    Covers {{ preview.summary.firstDate }} to {{ preview.summary.lastDate }}.
                </p>

                <div
                    v-if="wasImported"
                    class="flex items-start gap-2 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800"
                >
                    <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
                    {{ preview.created }} record(s) imported. Rows listed below were not imported.
                </div>

                <div v-else-if="preview.summary.error" class="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    Fix the errors below and upload again. The other rows can still be imported now.
                </div>

                <div v-if="!wasImported && token && preview.summary.create > 0">
                    <Button :disabled="confirmForm.processing" @click="confirm">
                        <Upload class="size-4" />
                        Import {{ preview.summary.create }} Record{{ preview.summary.create === 1 ? '' : 's' }}
                    </Button>
                    <p class="mt-2 text-xs text-muted-foreground">
                        This creates attendance for past dates and will change payroll for those months.
                    </p>
                </div>

                <div v-if="attentionRows.length" class="overflow-hidden rounded-lg border">
                    <div class="border-b p-3">
                        <h2 class="font-medium">Rows needing attention</h2>
                        <p class="text-sm text-muted-foreground">{{ attentionRows.length }} of {{ preview.summary.total }} rows.</p>
                    </div>
                    <div class="max-h-[28rem] overflow-auto">
                        <table class="w-full min-w-[880px] text-sm">
                            <thead class="sticky top-0 border-b bg-muted/60 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th class="w-[70px] px-3 py-2 font-medium">Line</th>
                                    <th class="w-[110px] px-3 py-2 font-medium">Date</th>
                                    <th class="w-[160px] px-3 py-2 font-medium">Chat Name</th>
                                    <th class="w-[170px] px-3 py-2 font-medium">Employee</th>
                                    <th class="w-[140px] px-3 py-2 font-medium">Result</th>
                                    <th class="px-3 py-2 font-medium">Why</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in attentionRows" :key="row.line" class="border-b last:border-b-0">
                                    <td class="px-3 py-2 text-muted-foreground">{{ row.line }}</td>
                                    <td class="px-3 py-2">{{ row.date ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.sourceName }}</td>
                                    <td class="px-3 py-2">
                                        <span v-if="row.employeeName">{{ row.employeeCode }} - {{ row.employeeName }}</span>
                                        <span v-else class="text-muted-foreground">{{ row.employeeCode || '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="inline-block rounded-full border px-2 py-0.5 text-xs font-medium" :class="actionClass(row.action)">
                                            {{ actionLabel(row.action) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-muted-foreground">{{ row.errors.join(' ') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
