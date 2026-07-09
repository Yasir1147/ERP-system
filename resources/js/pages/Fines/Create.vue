<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Employee {
    id: number;
    code: string | null;
    name: string;
    profession: string;
    type: string;
    status: string;
    label: string;
}

const props = defineProps<{
    employees: Employee[];
    employeeTypes: Record<string, string>;
    reasons: string[];
    selectedType: string | null;
    employeeTypeLabel: string;
}>();

const page = usePage();
const employeeSearch = ref('');
const employeeOpen = ref(false);
const employeeDropdownRef = ref<HTMLElement | null>(null);

const successMessage = computed(() => page.props.flash?.success as string | undefined);
const attendanceHomeUrl = computed(() => {
    if (props.selectedType === 'rope_access') {
        return '/mark-attendance/rope-access';
    }

    if (props.selectedType === 'contracting') {
        return '/mark-attendance/contracting';
    }

    return '/mark-attendance';
});

const form = useForm({
    employee_id: '',
    fine_date: new Date().toISOString().slice(0, 10),
    reason: '',
    amount: '',
    note: '',
    type: props.selectedType || '',
});

const employeeLabel = (employee: Employee) => `${employee.label} (${props.employeeTypes[employee.type]})`;
const selectedEmployee = computed(() => props.employees.find((employee) => String(employee.id) === form.employee_id));
const filteredEmployees = computed(() => {
    const query = employeeSearch.value.trim().toLowerCase();

    if (!query) {
        return props.employees;
    }

    return props.employees.filter((employee) =>
        [employee.code, employee.name, employee.profession, props.employeeTypes[employee.type]]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)),
    );
});

const employeeButtonLabel = computed(() => {
    if (!selectedEmployee.value) {
        return 'Select employee';
    }

    return employeeLabel(selectedEmployee.value);
});

const selectEmployee = (employee: Employee) => {
    form.employee_id = String(employee.id);
    employeeSearch.value = '';
    employeeOpen.value = false;
};

const closeDropdownsOnOutsideClick = (event: MouseEvent) => {
    const target = event.target as Node;

    if (employeeDropdownRef.value && !employeeDropdownRef.value.contains(target)) {
        employeeOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdownsOnOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdownsOnOutsideClick);
});

const submitFine = () => {
    form.post('/fines', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('employee_id', 'reason', 'amount', 'note');
            form.fine_date = new Date().toISOString().slice(0, 10);
        },
    });
};
</script>

<template>
    <Head title="Create Fine" />

    <main class="min-h-svh bg-background px-4 py-10">
        <div class="mx-auto max-w-xl">
            <div class="mb-8 text-center">
                <AppLogoIcon class="mx-auto size-24" />
                <h1 class="mt-3 text-2xl font-semibold tracking-normal">Create Fine Ticket</h1>
                <p class="mt-1 text-sm text-muted-foreground">Submit a {{ employeeTypeLabel }} fine for admin review.</p>
                <Link :href="attendanceHomeUrl" class="mt-3 inline-flex text-sm font-medium text-primary underline underline-offset-4">
                    Back to Mark Attendance
                </Link>
            </div>

            <form class="min-w-0 overflow-hidden rounded-lg border border-sidebar-border/70 bg-card p-5 shadow-sm" @submit.prevent="submitFine">
                <div v-if="successMessage" class="mb-4 rounded-md border border-green-600/20 bg-green-600/10 px-3 py-2 text-sm font-medium text-green-700">
                    {{ successMessage }}
                </div>

                <div class="grid min-w-0 gap-5">
                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-employee">Employee</Label>
                        <div ref="employeeDropdownRef" class="relative min-w-0">
                            <button
                                id="fine-employee"
                                type="button"
                                class="flex h-11 w-full max-w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2 text-left text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @click="employeeOpen = !employeeOpen"
                            >
                                <span class="block min-w-0 flex-1 truncate">{{ employeeButtonLabel }}</span>
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
                            </button>

                            <div v-if="employeeOpen" class="absolute left-0 right-0 top-full z-30 mt-2 max-w-full rounded-md border bg-background p-2 shadow-lg">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input v-model="employeeSearch" type="search" class="pl-9" placeholder="Search by code, name, or profession" />
                                </div>
                                <div class="mt-2 max-h-56 overflow-y-auto rounded-md border">
                                    <button
                                        v-for="employee in filteredEmployees"
                                        :key="employee.id"
                                        type="button"
                                        class="flex w-full items-start gap-3 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/60"
                                        :class="form.employee_id === String(employee.id) ? 'bg-primary/10' : 'bg-background'"
                                        @click="selectEmployee(employee)"
                                    >
                                        <span
                                            class="mt-1 flex size-4 shrink-0 items-center justify-center rounded border"
                                            :class="form.employee_id === String(employee.id) ? 'border-primary bg-primary text-primary-foreground' : 'bg-background'"
                                        >
                                            <CheckCircle2 v-if="form.employee_id === String(employee.id)" class="size-3" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium">{{ employeeLabel(employee) }}</span>
                                            <span class="block truncate text-xs text-muted-foreground">{{ employeeTypes[employee.type] }}</span>
                                        </span>
                                    </button>
                                    <div v-if="filteredEmployees.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No employees found.</div>
                                </div>
                            </div>
                            <button
                                v-if="selectedEmployee"
                                type="button"
                                class="mt-2 block w-full max-w-full truncate text-left text-xs font-medium text-muted-foreground"
                                @click="employeeOpen = true"
                                :title="employeeLabel(selectedEmployee)"
                            >
                                Selected: {{ employeeLabel(selectedEmployee) }}
                            </button>
                            <input v-model="form.employee_id" type="hidden" />
                        </div>
                        <InputError :message="form.errors.employee_id" />
                    </div>

                    <div class="grid min-w-0 gap-2 sm:grid-cols-2">
                        <div class="grid min-w-0 gap-2">
                            <Label for="fine-date">Fine Date</Label>
                            <Input id="fine-date" v-model="form.fine_date" type="date" />
                            <InputError :message="form.errors.fine_date" />
                        </div>
                        <div class="grid min-w-0 gap-2">
                            <Label for="fine-amount">Fine Amount</Label>
                            <Input id="fine-amount" v-model="form.amount" type="number" min="0.01" step="0.01" placeholder="0.00" />
                            <InputError :message="form.errors.amount" />
                        </div>
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-reason">Reason</Label>
                        <select
                            id="fine-reason"
                            v-model="form.reason"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">Select reason</option>
                            <option v-for="reason in reasons" :key="reason" :value="reason">{{ reason }}</option>
                        </select>
                        <InputError :message="form.errors.reason" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="fine-note">Note</Label>
                        <textarea
                            id="fine-note"
                            v-model="form.note"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Optional details"
                        />
                        <InputError :message="form.errors.note" />
                    </div>

                    <Button type="submit" class="h-11 w-full" :disabled="form.processing">Submit Fine Ticket</Button>
                </div>
            </form>
        </div>
    </main>
</template>
