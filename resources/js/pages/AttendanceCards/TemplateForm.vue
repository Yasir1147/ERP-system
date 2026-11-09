<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CardCanvas from './components/CardCanvas.vue';
import { sampleCard, type CardLayout, type CardTemplate, type SectionKey } from './types';

const props = defineProps<{ template: CardTemplate | null; defaultLayout: CardLayout }>();
const form = useForm({
    name: props.template?.name ?? '',
    version: props.template?.version ?? 1,
    layout: JSON.parse(JSON.stringify(props.template?.layout ?? props.defaultLayout)) as CardLayout,
});
const selected = ref<SectionKey>('grid');
const zoom = ref(0.9);
const asset = useForm<{ kind: string; image: File | null }>({ kind: 'signature', image: null });
const imagePreview = ref('');
watch(
    () => asset.image,
    (file) => {
        if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
        imagePreview.value = file ? URL.createObjectURL(file) : '';
    },
);
watch(
    () => asset.kind,
    () => {
        asset.reset('image');
        asset.clearErrors();
    },
);
const page = usePage<{ flash: { success?: string } }>();
const preview = computed<CardTemplate>(() => ({
    id: props.template?.id ?? null,
    name: form.name,
    version: form.version,
    layout: form.layout,
    logoUrl: asset.kind === 'logo' && imagePreview.value ? imagePreview.value : (props.template?.logoUrl ?? '/al-mohafiz-logo.png'),
    signatureUrl: asset.kind === 'signature' && imagePreview.value ? imagePreview.value : (props.template?.signatureUrl ?? null),
}));
const sample = computed(() => sampleCard(form.layout));
const sections: SectionKey[] = ['title', 'logo', 'identity', 'grid', 'footer', 'signature'];
const section = computed(() => form.layout.sections[selected.value]);
const labels: Record<SectionKey, string> = {
    title: 'Title',
    logo: 'Company Logo',
    identity: 'Employee Details',
    grid: 'Attendance Grid',
    footer: 'Totals & Signatures',
    signature: 'Admin Signature Image',
};
function move(key: SectionKey, x: number, y: number) {
    form.layout.sections[key].x = x;
    form.layout.sections[key].y = y;
}
function save() {
    if (form.processing || asset.processing) return;
    const submitted = JSON.parse(JSON.stringify(form.data()));
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.version = props.template?.version ?? form.version;
            form.defaults({ name: submitted.name, layout: submitted.layout, version: form.version });
            if (asset.image) upload();
        },
    };
    if (props.template?.id) form.put(`/attendance-card-templates/${props.template.id}`, options);
    else form.post('/attendance-card-templates', options);
}
function upload() {
    if (!props.template?.id || !asset.image) return;
    asset.post(`/attendance-card-templates/${props.template.id}/asset`, {
        preserveScroll: true,
        onSuccess: () => {
            asset.reset('image');
            form.version = props.template?.version ?? form.version;
            form.defaults('version', form.version);
        },
    });
}
function fileChanged(event: Event) {
    asset.image = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (asset.image && asset.kind === 'signature') {
        selected.value = 'signature';
        form.layout.show_signature = true;
        form.layout.sections.signature.visible = true;
    }
}
function resetLayout() {
    if (window.confirm('Reset the layout to the standard attendance card? Save to keep this change.'))
        form.layout = JSON.parse(JSON.stringify(props.defaultLayout));
}
function beforeUnload(event: BeforeUnloadEvent) {
    if (form.isDirty || asset.image) {
        event.preventDefault();
        event.returnValue = '';
    }
}
let removeNavigationGuard: (() => void) | undefined;
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    removeNavigationGuard = router.on('before', (event) => {
        if (
            event.detail.visit.method === 'get' &&
            (form.isDirty || asset.image) &&
            !window.confirm('Leave without saving this attendance card layout and image?')
        )
            event.preventDefault();
    });
});
onBeforeUnmount(() => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    window.removeEventListener('beforeunload', beforeUnload);
    removeNavigationGuard?.();
});
</script>

<template>
    <Head title="Attendance Card Designer" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Attendance Cards', href: '/attendance-cards' },
            { title: 'Card Designer', href: '#' },
        ]"
    >
        <div class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Attendance Card Designer</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Drag sections or enter their measurements in millimetres. Preview and print use the same layout.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline"><Link href="/attendance-cards">Attendance Cards</Link></Button
                    ><Button type="button" :disabled="form.processing || asset.processing" @click="save">{{ form.processing ? 'Saving...' : 'Save Format' }}</Button>
                </div>
            </div>
            <p v-if="page.props.flash?.success" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                {{ page.props.flash.success }}
            </p>
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-lg border border-red-200 p-3 text-sm text-red-700">
                <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
            </div>
            <section class="flex flex-wrap items-end gap-4 rounded-xl border bg-card p-4">
                <div class="min-w-52 flex-1">
                    <h2 class="font-semibold">Signature Image / Company Logo</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Choose your image to preview it. Save Format also uploads the selected image. PNG, JPG or WebP, up to 5 MB.
                    </p>
                </div>
                <label class="grid gap-1 text-sm"
                    >Image Type<select v-model="asset.kind" :disabled="form.processing || asset.processing" class="h-10 rounded-md border bg-background px-2">
                        <option value="signature">Admin Signature</option>
                        <option value="logo">Company Logo</option>
                    </select></label
                >
                <label class="grid gap-1 text-sm"
                    >Select Image<input
                        :key="asset.kind"
                        type="file"
                        :disabled="form.processing || asset.processing"
                        accept="image/png,image/jpeg,image/webp"
                        class="max-w-72 text-sm"
                        @change="fileChanged"
                /></label>
                <Button v-if="asset.image" type="button" :disabled="asset.processing || form.processing" @click="save">{{
                    asset.processing ? 'Uploading...' : 'Save Format & Image'
                }}</Button>
                <Button v-if="preview.signatureUrl" type="button" variant="outline" @click="selected = 'signature'">Position Signature</Button>
                <p v-for="(error, key) in asset.errors" :key="key" class="w-full text-sm text-red-600" role="alert">{{ error }}</p>
            </section>
            <div class="grid items-start gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
                <div class="space-y-4">
                    <section class="space-y-3 rounded-xl border bg-card p-4">
                        <h2 class="font-semibold">Format Settings</h2>
                        <label class="grid gap-1 text-sm"
                            >Format Name<Input v-model="form.name" maxlength="150" placeholder="e.g. Contracting Attendance Card"
                        /></label>
                        <label class="grid gap-1 text-sm">Title<Input v-model="form.layout.title" maxlength="100" /></label>
                        <p class="text-xs text-muted-foreground">Use {year} or {month} for the selected card month.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="grid gap-1 text-sm"
                                >Width (mm)<Input v-model.number="form.layout.width" type="number" min="150" max="190" step="0.1" /></label
                            ><label class="grid gap-1 text-sm"
                                >Height (mm)<Input v-model.number="form.layout.height" type="number" min="100" max="277" step="0.1"
                            /></label>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Two per A4 page needs a height of 136.5 mm or less. Taller cards can use one per page, or the scaled four-card layout.
                        </p>
                        <label class="grid gap-1 text-sm"
                            >Font<select v-model="form.layout.font" class="h-10 rounded-md border bg-background px-2">
                                <option v-for="font in ['Arial', 'Tahoma', 'Verdana', 'Times New Roman', 'Courier New']" :key="font">
                                    {{ font }}
                                </option>
                            </select></label
                        >
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                v-for="(label, key) in {
                                    text_color: 'Text Color',
                                    border_color: 'Border Color',
                                    header_color: 'Day Header',
                                    rest_color: 'Weekly Off Color',
                                }"
                                :key="key"
                                class="grid gap-1 text-sm"
                                >{{ label
                                }}<input v-model="form.layout[key]" type="color" class="h-9 w-full cursor-pointer rounded border bg-background"
                            /></label>
                        </div>
                        <label class="grid gap-1 text-sm"
                            >Weekly Off<select v-model.number="form.layout.rest_day" class="h-10 rounded-md border bg-background px-2">
                                <option :value="-1">None</option>
                                <option
                                    v-for="(day, i) in ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']"
                                    :key="day"
                                    :value="i"
                                >
                                    {{ day }}
                                </option>
                            </select></label
                        >
                        <label class="flex items-center gap-2 text-sm"
                            ><input v-model="form.layout.show_ot_site" type="checkbox" />Show separate overtime-site row</label
                        >
                        <details class="rounded border p-2">
                            <label class="mb-2 flex items-center gap-2 text-sm"
                                ><input v-model="form.layout.show_signature" type="checkbox" />Print uploaded admin signature</label
                            >
                            <summary class="cursor-pointer text-sm font-medium">Row &amp; signature labels</summary>
                            <div class="mt-3 grid gap-3">
                                <label
                                    v-for="(label, key) in {
                                        attendance_label: 'Attendance Row',
                                        site_label: 'Site Row',
                                        overtime_label: 'Overtime Row',
                                        admin_label: 'Admin Signature',
                                        employee_label: 'Employee Signature',
                                    }"
                                    :key="key"
                                    class="grid gap-1 text-sm"
                                    >{{ label }}<Input v-model="form.layout[key]" maxlength="40"
                                /></label>
                            </div>
                        </details>
                    </section>
                    <section class="space-y-3 rounded-xl border bg-card p-4">
                        <h2 class="font-semibold">Section Position</h2>
                        <label class="grid gap-1 text-sm"
                            >Section<select v-model="selected" class="h-10 rounded-md border bg-background px-2">
                                <option v-for="key in sections" :key="key" :value="key">{{ labels[key] }}</option>
                            </select></label
                        ><label class="flex items-center gap-2 text-sm"><input v-model="section.visible" type="checkbox" />Visible</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                v-for="(label, key) in {
                                    x: 'X (mm)',
                                    y: 'Y (mm)',
                                    width: 'Width (mm)',
                                    height: 'Height (mm)',
                                    font_size: 'Font Size (pt)',
                                }"
                                :key="key"
                                class="grid gap-1 text-sm"
                                >{{ label }}<Input v-model.number="section[key]" type="number" :min="key === 'x' || key === 'y' ? 0 : 5" step="0.1"
                            /></label>
                        </div>
                        <Button type="button" variant="outline" class="w-full" @click="resetLayout">Reset Standard Layout</Button>
                    </section>
                </div>
                <section class="min-w-0 rounded-xl border bg-card">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                        <div>
                            <h2 class="font-semibold">Card Designer / Preview</h2>
                            <p class="text-xs text-muted-foreground">Sample data · October 2026 · Click a section to edit or drag it.</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm"
                            >Zoom<input v-model.number="zoom" type="range" min="0.4" max="1.2" step="0.1" />{{ Math.round(zoom * 100) }}%</label
                        >
                    </div>
                    <div class="overflow-auto bg-muted/30 p-5">
                        <div
                            :style="{
                                width: `${((form.layout.width * 96) / 25.4) * zoom}px`,
                                height: `${((form.layout.height * 96) / 25.4) * zoom}px`,
                            }"
                        >
                            <div :style="{ transform: `scale(${zoom})`, transformOrigin: 'top left' }">
                                <CardCanvas
                                    :template="preview"
                                    :card="sample"
                                    editable
                                    :selected="selected"
                                    @select="selected = $event"
                                    @move="move"
                                />
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
