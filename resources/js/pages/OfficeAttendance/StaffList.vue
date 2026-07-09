<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, CheckCircle2, Clock3, Laptop, Search, UserCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StaffMember {
    id: number;
    code: string;
    name: string;
    designation: string | null;
    staffTypeLabel: string;
    photoUrl: string | null;
    markUrl: string;
    status: 'checked_in' | 'checked_out' | 'not_marked';
    statusLabel: string;
    todayRecord: {
        workModeLabel: string;
        checkInTime: string | null;
        checkOutTime: string | null;
        latestCheckInTime: string | null;
        latestCheckOutTime: string | null;
        hasOpenSession: boolean;
        sessionCount: number;
    } | null;
}

const props = defineProps<{
    staffMembers: StaffMember[];
    today: string;
}>();

const page = usePage();
const search = ref('');
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const checkedInCount = computed(() => props.staffMembers.filter((member) => member.status === 'checked_in').length);
const checkedOutCount = computed(() => props.staffMembers.filter((member) => member.status === 'checked_out').length);
const notMarkedCount = computed(() => props.staffMembers.filter((member) => member.status === 'not_marked').length);

const filteredStaff = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.staffMembers;
    }

    return props.staffMembers.filter((member) =>
        [member.code, member.name, member.designation ?? '', member.staffTypeLabel, member.statusLabel].some((value) =>
            value.toLowerCase().includes(query),
        ),
    );
});

const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

const modeIcon = (mode?: string) => (mode === 'Remote Work' ? Laptop : Building2);

const formatDisplayTime = (time?: string | null) => {
    if (!time) {
        return '-';
    }

    const match = time.match(/^(\d{1,2}):(\d{2})/);
    if (!match) {
        return time;
    }

    const hour = Number(match[1]);
    const minute = match[2];
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;

    return `${displayHour}:${minute} ${period}`;
};

const displayCheckIn = (member: StaffMember) =>
    formatDisplayTime(member.todayRecord?.latestCheckInTime ?? member.todayRecord?.checkInTime);

const displayCheckOut = (member: StaffMember) => {
    if (member.todayRecord?.hasOpenSession) {
        return 'Open';
    }

    return formatDisplayTime(member.todayRecord?.latestCheckOutTime ?? member.todayRecord?.checkOutTime);
};
</script>

<template>
    <Head title="Office Staff Attendance" />

    <main class="min-h-svh bg-[#f4f7fb] px-4 py-6 text-slate-950">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6 flex flex-col gap-5 rounded-lg border border-slate-200 bg-white px-5 py-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <AppLogoIcon class="size-16 shrink-0" />
                    <div>
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            Live Staff Board
                        </div>
                        <h1 class="mt-2 text-2xl font-semibold tracking-normal md:text-3xl">Office Staff Attendance</h1>
                        <p class="mt-1 text-sm text-slate-500">Select your profile to check in or check out for today.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-center">
                    <div class="px-4 py-3">
                        <p class="text-xl font-semibold text-emerald-700">{{ checkedInCount }}</p>
                        <p class="text-xs text-slate-500">Checked In</p>
                    </div>
                    <div class="border-x border-slate-200 px-4 py-3">
                        <p class="text-xl font-semibold text-blue-700">{{ checkedOutCount }}</p>
                        <p class="text-xs text-slate-500">Checked Out</p>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-xl font-semibold text-slate-700">{{ notMarkedCount }}</p>
                        <p class="text-xs text-slate-500">Not Marked</p>
                    </div>
                </div>
            </div>

            <div v-if="successMessage" class="mb-4 rounded-md border border-green-600/20 bg-green-600/10 px-4 py-3 text-sm font-medium text-green-700">
                {{ successMessage }}
            </div>

            <div class="mb-5 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-medium">Staff List</h2>
                    <p class="text-sm text-slate-500">{{ filteredStaff.length }} of {{ staffMembers.length }} active staff members</p>
                </div>
                <div class="relative w-full md:max-w-md">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="search"
                        type="search"
                        class="h-11 border-slate-200 bg-white pl-9"
                        placeholder="Search by code, name, designation, or status"
                    />
                </div>
            </div>

            <div v-if="filteredStaff.length === 0" class="rounded-lg border border-slate-200 bg-white p-12 text-center text-sm text-slate-500">
                No active staff found.
            </div>

            <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Link
                    v-for="member in filteredStaff"
                    :key="member.id"
                    :href="member.markUrl"
                    class="group overflow-hidden rounded-lg border border-white bg-white p-3 shadow-[0_18px_45px_rgba(15,23,42,0.10)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_22px_55px_rgba(15,23,42,0.16)]"
                >
                    <div class="relative overflow-hidden rounded-lg bg-gradient-to-br from-slate-100 via-slate-50 to-slate-200">
                        <div class="absolute right-3 top-3 z-10 flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold shadow-sm ring-1 ring-slate-900/5">
                            <span
                                class="size-2 rounded-full"
                                :class="{
                                    'bg-emerald-500': member.status === 'checked_in',
                                    'bg-blue-500': member.status === 'checked_out',
                                    'bg-slate-300': member.status === 'not_marked',
                                }"
                            ></span>
                            {{ member.statusLabel }}
                        </div>

                        <div class="flex aspect-[4/3] items-center justify-center p-3">
                            <img
                                v-if="member.photoUrl"
                                :src="member.photoUrl"
                                :alt="member.name"
                                class="h-full w-full rounded-lg object-cover object-top shadow-inner"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center rounded-lg bg-slate-200 text-6xl font-semibold text-slate-500">
                                {{ initials(member.name) }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 px-2 pb-2 pt-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-xl font-semibold leading-tight">{{ member.code }} - {{ member.name }}</h3>
                                <span
                                    class="inline-flex size-5 shrink-0 items-center justify-center rounded-full"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700': member.status === 'checked_in',
                                        'bg-blue-100 text-blue-700': member.status === 'checked_out',
                                        'bg-slate-100 text-slate-500': member.status === 'not_marked',
                                    }"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                </span>
                            </div>
                            <p class="truncate text-sm text-slate-500">{{ member.designation || member.staffTypeLabel }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 border-y border-slate-100 py-3">
                            <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-200/80">
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <component :is="modeIcon(member.todayRecord?.workModeLabel)" class="size-3.5" />
                                    Work Mode
                                </div>
                                <p class="mt-1 truncate text-sm font-semibold">{{ member.todayRecord?.workModeLabel || '-' }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-200/80">
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <UserCheck class="size-3.5" />
                                    Sessions
                                </div>
                                <p class="mt-1 text-sm font-semibold">{{ member.todayRecord?.sessionCount || 0 }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <Clock3 class="size-3.5" />
                                    Check In
                                </div>
                                <p class="mt-1 font-semibold">{{ displayCheckIn(member) }}</p>
                            </div>
                            <div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <CheckCircle2 class="size-3.5" />
                                    Check Out
                                </div>
                                <p class="mt-1 font-semibold" :class="{ 'text-amber-700': member.todayRecord?.hasOpenSession }">
                                    {{ displayCheckOut(member) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between rounded-full bg-slate-100 p-1 text-xs font-semibold text-slate-950">
                            <span class="px-3">{{ today }}</span>
                            <span class="rounded-full bg-white px-4 py-2 shadow-sm transition group-hover:translate-x-0.5">Open attendance</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </main>
</template>
