<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    staff: { code: string; name: string; fixed_start_time: string; fixed_end_time: string };
    today: string;
    leaveTemplate: string;
    markUrl: string;
    leaveUrl: string;
    publicProfile: boolean;
    todayLeave: { status: string } | null;
    attendance: { check_in_time: string; check_out_time: string; is_fixed: boolean } | null;
    leaves: { id: number; leave_date: string; reason: string | null; label: string; status: string }[];
}>();
const page = usePage<{ flash: { success?: string } }>();
const showLeave = ref(false);
const mark = useForm({});
const defaultMessage = (date: string) => {
    const day = new Date(date + 'T12:00:00Z');
    const format = (value: Date) => value.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    const leaveDate = format(day);
    day.setUTCDate(day.getUTCDate() + 1);
    return props.leaveTemplate
        .replaceAll('[Your Name]', props.staff.name)
        .replaceAll('[Leave Date]', leaveDate)
        .replaceAll('[Return Date]', format(day));
};
const leave = useForm({ leave_date: props.today, reason: defaultMessage(props.today) });
watch(
    () => leave.leave_date,
    (date, oldDate) => {
        if (date && oldDate && leave.reason === defaultMessage(oldDate)) leave.reason = defaultMessage(date);
    },
);
const hours = computed(() => {
    const minutes = (time: string) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
    return (minutes(props.staff.fixed_end_time) - minutes(props.staff.fixed_start_time)) / 60;
});
const completed = computed(() => !!(props.attendance?.check_in_time || props.attendance?.check_out_time));
const leaveToday = computed(() => props.todayLeave);
const timeLabel = (time: string) => `${Number(time.slice(0, 2)) % 12 || 12}:${time.slice(3, 5)} ${Number(time.slice(0, 2)) >= 12 ? 'PM' : 'AM'}`;
const submitLeave = () =>
    leave.post(props.leaveUrl, {
        onSuccess: () => {
            showLeave.value = false;
            leave.reset();
        },
    });
</script>

<template>
    <Head title="My Attendance" />
    <main class="min-h-screen bg-slate-50 px-4 py-10 text-slate-900">
        <div class="mx-auto max-w-xl space-y-6">
            <div class="flex items-center justify-between">
                <span class="font-semibold">Al Mohafiz · My Attendance</span
                ><Link v-if="!publicProfile" href="/logout" method="post" as="button" class="text-sm underline">Log out</Link>
                <Link v-if="publicProfile" href="/office-attendance/staff" class="text-sm underline">Back to Staff List</Link>
            </div>
            <p v-if="page.props.flash?.success" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                {{ page.props.flash.success }}
            </p>
            <section class="space-y-6 rounded-2xl border bg-white p-6 shadow-sm">
                <div>
                    <p class="text-sm text-slate-500">{{ staff.code }} · {{ today }} · UAE</p>
                    <h1 class="mt-2 text-2xl font-semibold">{{ staff.name }}</h1>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="font-medium">{{ timeLabel(staff.fixed_start_time) }} – {{ timeLabel(staff.fixed_end_time) }}</p>
                    <p class="text-sm text-slate-500">{{ hours }} hours · Fixed Daily Attendance</p>
                </div>
                <p v-if="attendance && completed" class="font-medium text-emerald-700">
                    {{ attendance.is_fixed ? 'Attendance Completed' : 'Attendance already recorded' }} ·
                    {{ timeLabel(attendance.check_in_time || staff.fixed_start_time) }} –
                    {{ attendance.check_out_time ? timeLabel(attendance.check_out_time) : 'Open' }}
                </p>
                <p v-if="leaveToday" class="text-amber-700">On Leave{{ leaveToday.status === 'pending' ? ' (Pending approval)' : '' }}</p>
                <InputError v-for="(error, key) in mark.errors" :key="key" :message="error" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Button
                        class="h-12 bg-emerald-700 hover:bg-emerald-800"
                        :disabled="mark.processing || completed || !!leaveToday"
                        @click="mark.post(markUrl)"
                        >Mark Attendance</Button
                    >
                    <Button class="h-12" variant="outline" @click="showLeave = true">Leave</Button>
                </div>
            </section>
            <section v-if="leaves.length" class="rounded-2xl border bg-white p-6">
                <h2 class="mb-4 font-semibold">My Leave Requests</h2>
                <div v-for="request in leaves" :key="request.id" class="border-t py-3">
                    <div class="flex justify-between text-sm">
                        <span>{{ request.leave_date.slice(0, 10) }}</span
                        ><span class="capitalize">{{ request.label }}</span>
                    </div>
                    <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-500">{{ request.reason }}</p>
                </div>
            </section>
        </div>
        <Dialog v-model:open="showLeave"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>Apply for Leave</DialogTitle
                    ><DialogDescription>Your request will be sent to info@almohafiz.com for admin review.</DialogDescription></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="submitLeave">
                    <label class="grid gap-2 text-sm"
                        >Leave date<input
                            v-model="leave.leave_date"
                            type="date"
                            :min="today"
                            required
                            class="rounded-md border bg-background p-2" /><InputError :message="leave.errors.leave_date"
                    /></label>
                    <label class="grid gap-2 text-sm"
                        >Reason<textarea
                            v-model="leave.reason"
                            required
                            maxlength="2000"
                            rows="9"
                            class="rounded-md border bg-background p-3"
                            placeholder="Explain why you need leave" /><InputError :message="leave.errors.reason"
                    /></label>
                    <Button type="submit" :disabled="leave.processing">Submit Leave Request</Button>
                </form>
            </DialogContent></Dialog
        >
    </main>
</template>
