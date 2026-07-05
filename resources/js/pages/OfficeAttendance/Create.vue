<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    staff: {
        code: string;
        name: string;
        designation: string | null;
        staffTypeLabel: string;
        status: string;
    };
    workModes: Record<string, string>;
    today: string;
    existingRecord: {
        workMode: string;
        workModeLabel: string;
        checkInTime: string | null;
        checkOutTime: string | null;
        hasOpenSession: boolean;
        sessionCount: number;
        latestCheckInTime: string | null;
        latestCheckOutTime: string | null;
        note: string | null;
        submittedAt: string | null;
    } | null;
}>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const form = useForm({
    work_mode: props.existingRecord?.workMode ?? 'office',
    attendance_action: 'save',
    note: props.existingRecord?.note ?? '',
});

const hasAnySession = computed(() => (props.existingRecord?.sessionCount ?? 0) > 0 || Boolean(props.existingRecord?.checkInTime));
const hasOpenSession = computed(() => Boolean(props.existingRecord?.hasOpenSession));
const canCheckIn = computed(() => !hasOpenSession.value && !form.processing);
const canCheckOut = computed(() => hasOpenSession.value && !form.processing);
const attendanceStateLabel = computed(() => {
    if (hasOpenSession.value) return 'Waiting for checkout';
    if (hasAnySession.value) return 'Checked out';

    return 'Ready for check-in';
});
const checkInButtonLabel = computed(() => (hasAnySession.value ? 'Check In Again' : 'Check In'));

const submitAttendance = (action: 'save' | 'check_in' | 'check_out' = 'save') => {
    form.attendance_action = action;
    form.post('/office-attendance/mark', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Office Attendance" />

    <main class="min-h-svh bg-background px-4 py-10">
        <div class="mx-auto max-w-xl">
            <div class="mb-8 text-center">
                <AppLogoIcon class="mx-auto size-24" />
                <h1 class="mt-3 text-2xl font-semibold tracking-normal">Office Attendance</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ staff.code }} - {{ staff.name }}</p>
                <p class="text-xs text-muted-foreground">{{ staff.designation || staff.staffTypeLabel }}</p>
            </div>

            <form class="rounded-lg border border-sidebar-border/70 bg-card p-5 shadow-sm" @submit.prevent="submitAttendance('save')">
                <div v-if="successMessage" class="mb-4 rounded-md border border-green-600/20 bg-green-600/10 px-3 py-2 text-sm font-medium text-green-700">
                    {{ successMessage }}
                </div>

                <div v-if="existingRecord" class="mb-4 rounded-md border bg-muted/30 px-3 py-2 text-sm">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span class="font-medium">Today marked:</span>
                            {{ existingRecord.workModeLabel }}
                            <span v-if="existingRecord.submittedAt" class="text-muted-foreground">at {{ existingRecord.submittedAt }}</span>
                        </div>
                        <span
                            class="w-fit rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-orange-500/10 text-orange-700': hasOpenSession,
                                'bg-green-600/10 text-green-700': hasAnySession && !hasOpenSession,
                                'bg-muted text-muted-foreground': !hasAnySession,
                            }"
                        >
                            {{ attendanceStateLabel }}
                        </span>
                    </div>
                    <div class="mt-2 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                        <span>First Check In: <strong class="text-foreground">{{ existingRecord.checkInTime || '-' }}</strong></span>
                        <span>Last Check Out: <strong class="text-foreground">{{ existingRecord.checkOutTime || '-' }}</strong></span>
                        <span>Latest Check In: <strong class="text-foreground">{{ existingRecord.latestCheckInTime || '-' }}</strong></span>
                        <span>Sessions: <strong class="text-foreground">{{ existingRecord.sessionCount || 0 }}</strong></span>
                    </div>
                </div>

                <div class="grid gap-5">
                    <div class="grid gap-2">
                        <Label>Work Mode</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                v-for="(label, mode) in workModes"
                                :key="mode"
                                type="button"
                                class="h-12"
                                :variant="form.work_mode === mode ? 'default' : 'outline'"
                                :disabled="form.processing || hasOpenSession"
                                @click="form.work_mode = String(mode)"
                            >
                                {{ label }}
                            </Button>
                        </div>
                        <InputError :message="form.errors.work_mode" />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <Button type="button" variant="outline" class="h-11" :disabled="!canCheckIn" @click="submitAttendance('check_in')">{{ checkInButtonLabel }}</Button>
                        <Button type="button" variant="outline" class="h-11" :disabled="!canCheckOut" @click="submitAttendance('check_out')">Check Out</Button>
                        <InputError class="sm:col-span-2" :message="form.errors.attendance_action" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="office-attendance-note">Note</Label>
                        <textarea
                            id="office-attendance-note"
                            v-model="form.note"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Optional daily note"
                        />
                        <InputError :message="form.errors.note" />
                    </div>

                    <Button type="submit" class="h-11 w-full" :disabled="form.processing">
                        {{ existingRecord ? 'Update Today Attendance' : 'Submit Today Attendance' }}
                    </Button>

                    <div v-if="form.recentlySuccessful" class="flex items-center justify-center gap-2 rounded-md border border-green-600/30 bg-green-600/10 px-3 py-2 text-sm text-green-600">
                        <CheckCircle2 class="size-4" />
                        Office attendance saved.
                    </div>
                </div>
            </form>
        </div>
    </main>
</template>
