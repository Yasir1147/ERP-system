<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CardCanvas from './components/CardCanvas.vue';
import { cardQuery, type CardReport, type CardTemplate } from './types';

const props = defineProps<{
    report: CardReport;
    templates: CardTemplate[];
    employees: { id: number; code: string; name: string; profession: string; type: string }[];
    employeeTypes: Record<string, string>;
    errors?: Record<string, string>;
}>();
const month = ref(props.report.filters.month);
const templateId = ref(props.report.filters.templateId ? String(props.report.filters.templateId) : '');
const type = ref(props.report.filters.employeeType);
const employeesForType = computed(() => props.employees.filter((e) => e.type === type.value));
const ids = ref<number[]>(
    props.report.filters.allEmployees ? props.employees.filter((e) => e.type === type.value).map((e) => e.id) : [...props.report.filters.employeeIds],
);
const perPage = ref(props.report.filters.perPage);
const search = ref('');
const visibleEmployees = computed(() =>
    employeesForType.value.filter((e) => `${e.code} ${e.name} ${e.profession}`.toLowerCase().includes(search.value.trim().toLowerCase())),
);
const allSelected = computed(() => employeesForType.value.length > 0 && ids.value.length === employeesForType.value.length);
watch(type, () => {
    ids.value = [];
    search.value = '';
});
const selectedTemplate = computed(() => props.templates.find((t) => String(t.id) === templateId.value));
const tooTall = computed(() => (selectedTemplate.value?.layout.height ?? 132) > 136.5 && perPage.value === 2);
function toggleAll() {
    ids.value = allSelected.value ? [] : employeesForType.value.map((e) => e.id);
}
function generate() {
    router.get(
        `/attendance-cards?${cardQuery({ month: month.value, templateId: templateId.value ? Number(templateId.value) : null, employeeType: type.value, employeeIds: ids.value, allEmployees: allSelected.value, perPage: perPage.value })}`,
    );
}
const printUrl = computed(() => `/attendance-cards/print?${cardQuery(props.report.filters)}`);
const printTooTall = computed(() => props.report.filters.perPage === 2 && props.report.template.layout.height > 136.5);
</script>

<template>
    <Head title="Attendance Cards" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Attendance', href: '/attendance' },
            { title: 'Attendance Cards', href: '/attendance-cards' },
        ]"
    >
        <div class="flex min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Monthly Attendance Cards</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Choose a month and employees, then generate cards using a saved format.</p>
                </div>
                <Button as-child><Link href="/attendance-card-templates/create">Create Card Format</Link></Button>
            </div>
            <form class="grid gap-4 rounded-xl border bg-card p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" @submit.prevent="generate">
                <div class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm">Month<Input v-model="month" type="month" required /></label
                        ><label class="grid gap-1 text-sm"
                            >Employee Type<select v-model="type" class="h-10 rounded-md border bg-background px-2">
                                <option v-for="(label, value) in employeeTypes" :key="value" :value="value">{{ label }}</option>
                            </select></label
                        >
                    </div>
                    <label class="grid gap-1 text-sm"
                        >Card Format<select v-model="templateId" class="h-10 rounded-md border bg-background px-2">
                            <option value="">Standard Attendance Card</option>
                            <option v-for="template in templates" :key="template.id!" :value="String(template.id)">{{ template.name }}</option>
                        </select></label
                    >
                    <div class="flex flex-wrap gap-2" v-if="selectedTemplate">
                        <Button as-child type="button" variant="outline"
                            ><Link :href="`/attendance-card-templates/${selectedTemplate.id}/edit`">Edit Selected Format</Link></Button
                        ><Button type="button" variant="outline" @click="router.post(`/attendance-card-templates/${selectedTemplate.id}/duplicate`)"
                            >Duplicate Format</Button
                        >
                    </div>
                    <fieldset class="rounded-md border p-3">
                        <legend class="px-1 text-sm font-medium">A4 Print Layout</legend>
                        <label class="mr-5 inline-flex items-center gap-2 text-sm"
                            ><input v-model.number="perPage" type="radio" :value="1" />1 card per page</label
                        ><label class="inline-flex items-center gap-2 text-sm"
                            ><input v-model.number="perPage" type="radio" :value="2" />2 cards per page</label
                        >
                    </fieldset>
                    <p v-if="tooTall" class="text-sm text-red-600">
                        This format is too tall for two cards. Select one per page or reduce its height in the designer.
                    </p>
                    <Button type="submit" :disabled="tooTall">Generate Cards</Button>
                </div>
                <div class="rounded-lg border">
                    <div class="border-b p-3">
                        <Input v-model="search" placeholder="Search employee code, name or profession" aria-label="Search employees" />
                    </div>
                    <label class="flex cursor-pointer items-center gap-3 border-b p-3 text-sm font-medium"
                        ><input type="checkbox" :checked="allSelected" :indeterminate="ids.length > 0 && !allSelected" @change="toggleAll" />Select
                        All ({{ employeesForType.length }}) · {{ ids.length }} selected</label
                    >
                    <div class="max-h-60 overflow-y-auto">
                        <label
                            v-for="employee in visibleEmployees"
                            :key="employee.id"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-muted"
                            ><input v-model="ids" type="checkbox" :value="employee.id" />{{ employee.code }} - {{ employee.name
                            }}<span class="ml-auto text-xs text-muted-foreground">{{ employee.profession }}</span></label
                        >
                        <p v-if="!visibleEmployees.length" class="p-4 text-sm text-muted-foreground">No employees found.</p>
                    </div>
                </div>
                <p v-for="(error, key) in errors" :key="key" class="text-sm text-red-600" role="alert">{{ error }}</p>
            </form>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4">
                <div>
                    <h2 class="font-semibold">{{ report.cards.length }} card(s) · {{ report.filters.month }}</h2>
                    <p class="text-sm text-muted-foreground">{{ report.template.name }} · {{ report.filters.perPage }} per A4 page</p>
                </div>
                <Button v-if="report.cards.length && !printTooTall" as-child
                    ><a :href="printUrl" target="_blank" rel="noreferrer">Print All / Save PDF</a></Button
                >
                <p v-if="printTooTall" class="text-sm text-red-600">Choose one card per page and generate again for this tall format.</p>
            </div>
            <p class="text-xs text-muted-foreground">
                Preview shows the first 3 cards; Print All includes every generated card. P = Present, ½ = Half day, A = Absent, L = Leave, H =
                Holiday. Blank means no record. Overtime uses hours:minutes. Cards reflect attendance and do not change payroll.
            </p>
            <div v-for="card in report.cards.slice(0, 3)" :key="card.employeeId" class="overflow-auto rounded-xl border bg-muted/30 p-4">
                <CardCanvas :template="report.template" :card="card" />
            </div>
            <p v-if="!report.cards.length" class="rounded-xl border p-10 text-center text-muted-foreground">
                Select employees and click Generate Cards to preview their monthly cards.
            </p>
        </div>
    </AppLayout>
</template>
