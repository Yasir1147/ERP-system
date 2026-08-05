<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarCheck2, CalendarPlus2, CheckCircle2, ClipboardList, CopyPlus, Edit3, Search, Trash2, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DutyPlanSummary {
    id: number;
    date: string;
    status: string;
    createdBy: string | null;
    assignmentCount: number;
    projectCount: number;
    projectNames: string[];
    canSubmit: boolean;
}

const props = defineProps<{
    activeStatus: 'all' | 'open' | 'submitted';
    dateMin: string | null;
    dateMax: string;
    plans: DutyPlanSummary[];
    summary: { open: number; submitted: number; employees: number };
}>();

const page = usePage();
const search = ref('');
const repeatPlan = ref<DutyPlanSummary | null>(null);
const repeatDate = ref('');
const repeatProcessing = ref(false);

const tomorrow = () => {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const filteredPlans = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return props.plans;

    return props.plans.filter((plan) =>
        [formatDate(plan.date), plan.createdBy, ...plan.projectNames].filter(Boolean).some((value) => String(value).toLowerCase().includes(query)),
    );
});

const formatDate = (date: string) => {
    const [year, month, day] = date.split('-');
    return `${day}/${month}/${year}`;
};

const statusLabel = (status: string) => (status === 'finalized' ? 'Submitted' : 'Open');
const statusClass = (status: string) =>
    status === 'finalized' ? 'border-green-600/30 bg-green-600/10 text-green-700' : 'border-amber-600/30 bg-amber-600/10 text-amber-800';

const openRepeat = (plan: DutyPlanSummary) => {
    repeatPlan.value = plan;
    const [year, month, day] = plan.date.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1, day + 1));
    repeatDate.value = target.toISOString().slice(0, 10);
};

const submitRepeat = () => {
    if (!repeatPlan.value || !repeatDate.value || repeatProcessing.value) return;
    repeatProcessing.value = true;
    router.post(
        `/contracting-duty-plans/${repeatPlan.value.id}/repeat`,
        {
            target_date: repeatDate.value,
        },
        {
            onSuccess: () => {
                repeatPlan.value = null;
            },
            onFinish: () => {
                repeatProcessing.value = false;
            },
        },
    );
};

const deletePlan = (plan: DutyPlanSummary) => {
    if (plan.status === 'finalized' || !window.confirm(`Delete the open duty for ${formatDate(plan.date)}?`)) return;
    router.delete(`/contracting-duty-plans/${plan.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Duty Dashboard" />

    <main class="min-h-svh bg-background px-4 py-5 text-foreground sm:px-6">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-5">
            <header class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center sm:p-5">
                <div class="flex items-center gap-4">
                    <AppLogoIcon class="size-16 shrink-0 sm:size-20" />
                    <div>
                        <p class="text-xs font-semibold uppercase text-primary">Contracting workforce</p>
                        <h1 class="mt-1 text-2xl font-semibold">Duty Dashboard</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Create, review, repeat, and submit only the duties assigned to your account.</p>
                    </div>
                </div>
                <Button as-child class="h-11">
                    <Link :href="`/contracting-duty-plans/create?date=${tomorrow()}`"> <CalendarPlus2 class="size-4" />Create New Duty </Link>
                </Button>
            </header>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.errors?.plan || page.props.errors?.target_date"
                class="rounded-md border border-red-600/30 bg-red-600/10 px-4 py-3 text-sm text-red-700"
            >
                {{ page.props.errors.plan || page.props.errors.target_date }}
            </div>

            <section class="grid gap-3 sm:grid-cols-3">
                <article class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Open Duties</span><ClipboardList class="size-5" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ summary.open }}</p>
                </article>
                <article class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Submitted Duties</span><CalendarCheck2 class="size-5" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ summary.submitted }}</p>
                </article>
                <article class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-sm">Employees Scheduled</span><Users class="size-5" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ summary.employees }}</p>
                </article>
            </section>

            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="grid gap-3 border-b p-4 sm:grid-cols-[auto_minmax(240px,1fr)] sm:items-center sm:p-5">
                    <nav class="flex rounded-lg bg-muted p-1" aria-label="Duty status">
                        <Link
                            v-for="item in [
                                { value: 'open', label: 'Open' },
                                { value: 'submitted', label: 'Submitted' },
                                { value: 'all', label: 'All' },
                            ]"
                            :key="item.value"
                            :href="`/contracting-duty-plans?status=${item.value}`"
                            class="flex-1 rounded-md px-4 py-2 text-center text-sm font-medium transition-colors"
                            :class="
                                activeStatus === item.value
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            >{{ item.label }}</Link
                        >
                    </nav>
                    <div class="relative sm:w-80 sm:justify-self-end">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="pl-9" placeholder="Search date or project" />
                    </div>
                </div>

                <div v-if="filteredPlans.length" class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-3">
                    <article
                        v-for="plan in filteredPlans"
                        :key="plan.id"
                        class="flex min-h-56 flex-col rounded-xl border bg-background p-4 transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-semibold">{{ formatDate(plan.date) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Created by {{ plan.createdBy || 'Unknown user' }}</p>
                            </div>
                            <span class="rounded-full border px-2.5 py-1 text-xs" :class="statusClass(plan.status)">{{
                                statusLabel(plan.status)
                            }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3 text-sm">
                            <div>
                                <p class="text-muted-foreground">Projects</p>
                                <p class="mt-1 font-semibold">{{ plan.projectCount }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Employees</p>
                                <p class="mt-1 font-semibold">{{ plan.assignmentCount }}</p>
                            </div>
                        </div>
                        <p class="mt-3 line-clamp-2 text-sm text-muted-foreground">
                            {{ plan.projectNames.join(', ') || 'No assignments added yet' }}
                        </p>
                        <div class="mt-auto flex flex-wrap gap-2 border-t pt-4">
                            <Button as-child size="sm" :variant="plan.status === 'finalized' ? 'outline' : 'default'">
                                <Link :href="`/contracting-duty-plans/${plan.id}/edit?step=${plan.status === 'finalized' ? 3 : 2}`">
                                    <component :is="plan.status === 'finalized' ? CheckCircle2 : Edit3" class="size-4" />
                                    {{ plan.status === 'finalized' ? 'View' : 'Edit' }}
                                </Link>
                            </Button>
                            <Button v-if="plan.status !== 'finalized'" as-child size="sm" variant="outline">
                                <Link :href="`/contracting-duty-plans/${plan.id}/edit?step=3`">
                                    <ClipboardList class="size-4" />{{ plan.canSubmit ? 'Submit Attendance' : 'Review' }}
                                </Link>
                            </Button>
                            <Button v-else as-child size="sm">
                                <Link :href="`/contracting-duty-plans/${plan.id}/edit?step=2&extend=1`">
                                    <CalendarPlus2 class="size-4" />Add More Attendance
                                </Link>
                            </Button>
                            <Button type="button" size="sm" variant="outline" @click="openRepeat(plan)"><CopyPlus class="size-4" />Repeat</Button>
                            <Button
                                v-if="plan.status !== 'finalized'"
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="ml-auto text-destructive"
                                title="Delete open duty"
                                @click="deletePlan(plan)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </article>
                </div>
                <div v-else class="p-12 text-center text-sm text-muted-foreground">
                    <ClipboardList class="mx-auto mb-3 size-9" />No duties match this view.
                </div>
            </section>
        </div>

        <div v-if="repeatPlan" class="fixed inset-0 z-50 grid place-items-center bg-black/55 p-4" @click.self="repeatPlan = null">
            <div class="w-full max-w-md rounded-xl border bg-card p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Repeat Duty</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Copy {{ formatDate(repeatPlan.date) }} assignments to another date.</p>
                    </div>
                    <Button type="button" size="icon" variant="ghost" @click="repeatPlan = null"><X class="size-4" /></Button>
                </div>
                <label class="mt-5 grid gap-2 text-sm font-medium"
                    >New duty date
                    <Input v-model="repeatDate" type="date" :min="dateMin || undefined" :max="dateMax" />
                </label>
                <div class="mt-5 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="repeatPlan = null">Cancel</Button>
                    <Button type="button" :disabled="repeatProcessing || !repeatDate || repeatDate === repeatPlan.date" @click="submitRepeat">
                        <CopyPlus class="size-4" />{{ repeatProcessing ? 'Copying...' : 'Create Copy' }}
                    </Button>
                </div>
            </div>
        </div>
    </main>
</template>
