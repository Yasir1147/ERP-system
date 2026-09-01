<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Holiday {
    id: number;
    date: string;
    dateLabel: string;
    weekday: string;
    name: string;
    isPaid: boolean;
    employeeType: string | null;
    employeeTypeLabel: string;
    note: string | null;
    createdBy: string | null;
}

const props = defineProps<{
    holidays: Holiday[];
    filters: { year: number };
    years: number[];
    employeeTypes: Array<{ value: string; label: string }>;
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Attendance', href: '/attendance' },
    { title: 'Holidays', href: '/holidays' },
];

const year = ref(String(props.filters.year));
const editingId = ref<number | null>(null);
const showForm = ref(false);

const emptyForm = () => ({
    holiday_date: '',
    name: '',
    is_paid: true,
    employee_type: '',
    note: '',
});

const form = useForm(emptyForm());

const resetForm = () => {
    editingId.value = null;
    showForm.value = false;
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
};

const openCreate = () => {
    resetForm();
    showForm.value = true;
};

const startEditing = (holiday: Holiday) => {
    editingId.value = holiday.id;
    showForm.value = true;
    form.holiday_date = holiday.date;
    form.name = holiday.name;
    form.is_paid = holiday.isPaid;
    form.employee_type = holiday.employeeType ?? '';
    form.note = holiday.note ?? '';
    form.clearErrors();
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editingId.value) {
        form.put(`/holidays/${editingId.value}`, options);
        return;
    }

    form.post('/holidays', options);
};

const remove = (holiday: Holiday) => {
    if (window.confirm(`Remove ${holiday.name} on ${holiday.dateLabel}? Payroll for that month will be recalculated.`)) {
        router.delete(`/holidays/${holiday.id}`, { preserveScroll: true });
    }
};

const applyYear = () => {
    router.get('/holidays', { year: year.value }, { preserveState: false });
};
</script>

<template>
    <Head title="Holidays" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Holidays</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                        A paid holiday pays every employee for a day nobody was asked to work. Anyone who came in anyway keeps that paid day
                        and has the hours counted as overtime, so the day is never paid twice as ordinary work.
                    </p>
                </div>
                <div class="flex gap-2">
                    <select v-model="year" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyYear">
                        <option v-for="option in years" :key="option" :value="String(option)">{{ option }}</option>
                    </select>
                    <Button type="button" @click="openCreate"><Plus class="size-4" />New Holiday</Button>
                </div>
            </div>

            <div
                v-if="page.props.flash?.success"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700"
            >
                {{ page.props.flash.success }}
            </div>

            <section v-if="showForm" class="rounded-xl border bg-card shadow-sm">
                <div class="flex items-start justify-between border-b p-4">
                    <h2 class="font-semibold">{{ editingId ? 'Edit Holiday' : 'Add Holiday' }}</h2>
                    <Button type="button" size="icon" variant="ghost" @click="resetForm"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="save">
                    <div class="grid gap-1.5">
                        <Label>Date *</Label>
                        <Input v-model="form.holiday_date" type="date" />
                        <InputError :message="form.errors.holiday_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Name *</Label>
                        <Input v-model="form.name" placeholder="Eid Al Fitr" maxlength="120" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Applies To</Label>
                        <select v-model="form.employee_type" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All employees</option>
                            <option v-for="option in employeeTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.employee_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Paid</Label>
                        <label class="flex h-10 items-center gap-2 text-sm">
                            <input v-model="form.is_paid" type="checkbox" class="size-4 rounded border-input" />
                            Pay everyone for this day
                        </label>
                        <InputError :message="form.errors.is_paid" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2 xl:col-span-4">
                        <Label>Note</Label>
                        <Input v-model="form.note" placeholder="Optional" maxlength="500" />
                        <InputError :message="form.errors.note" />
                    </div>
                    <div class="flex gap-2 md:col-span-2 xl:col-span-4">
                        <Button type="submit" :disabled="form.processing">Save</Button>
                        <Button type="button" variant="outline" @click="resetForm">Cancel</Button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b p-4">
                    <h2 class="font-medium">{{ filters.year }} Holidays</h2>
                    <p class="text-sm text-muted-foreground">{{ holidays.length }} declared.</p>
                </div>

                <div v-if="holidays.length" class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="w-[120px] px-3 py-2 font-medium">Date</th>
                                <th class="w-[110px] px-3 py-2 font-medium">Day</th>
                                <th class="px-3 py-2 font-medium">Name</th>
                                <th class="w-[170px] px-3 py-2 font-medium">Applies To</th>
                                <th class="w-[90px] px-3 py-2 font-medium">Paid</th>
                                <th class="w-[150px] px-3 py-2 font-medium">Added By</th>
                                <th class="w-[100px] px-3 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="holiday in holidays" :key="holiday.id" class="border-b last:border-b-0">
                                <td class="px-3 py-3">{{ holiday.dateLabel }}</td>
                                <td class="px-3 py-3 text-muted-foreground">{{ holiday.weekday }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-medium">{{ holiday.name }}</p>
                                    <p v-if="holiday.note" class="text-xs text-muted-foreground">{{ holiday.note }}</p>
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">{{ holiday.employeeTypeLabel }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            holiday.isPaid
                                                ? 'border-green-600/30 bg-green-600/10 text-green-700'
                                                : 'border-border bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ holiday.isPaid ? 'Paid' : 'Unpaid' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">{{ holiday.createdBy || '-' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="text-muted-foreground hover:text-foreground" @click="startEditing(holiday)">
                                            <Pencil class="size-4" />
                                        </button>
                                        <button type="button" class="text-red-600 hover:text-red-700" @click="remove(holiday)">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="flex min-h-40 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                    <CalendarDays class="size-6" />
                    No holidays declared for {{ filters.year }}.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
