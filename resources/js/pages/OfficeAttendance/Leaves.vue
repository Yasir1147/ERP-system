<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
const props = defineProps<{
    leaveTemplate: string;
    leaves: {
        data: {
            id: number;
            office_staff: { code: string; name: string };
            leave_date: string;
            reason: string;
            status: string;
            email_status: string;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const page = usePage<{ flash: { success?: string } }>();
const form = useForm({ status: '' });
const templateForm = useForm({ template: props.leaveTemplate });
const review = (id: number, status: string) => {
    form.status = status;
    form.put(`/office-leave-requests/${id}`, { preserveScroll: true });
};
</script>
<template>
    <Head title="Office Leave Requests" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Office Staff', href: '/office-staff' },
            { title: 'Leave Requests', href: '/office-leave-requests' },
        ]"
    >
        <div class="space-y-4 p-4">
            <h1 class="text-2xl font-semibold">Office Leave Requests</h1>
            <p v-if="page.props.flash?.success" role="status" class="rounded-lg border bg-muted p-3">{{ page.props.flash.success }}</p>
            <p class="text-sm text-muted-foreground">Requests are emailed to info@almohafiz.com. Delivery uses Settings &gt; Mail.</p>
            <details class="rounded-xl border bg-card p-4">
                <summary class="cursor-pointer font-semibold">Default Leave Message</summary>
                <form class="mt-4 space-y-3" @submit.prevent="templateForm.put('/office-leave-template', { preserveScroll: true })">
                    <p class="text-sm text-muted-foreground">
                        [Your Name], [Leave Date], and [Return Date] fill automatically. Replace manager and colleague placeholders here, or let staff
                        edit them when applying.
                    </p>
                    <label class="grid gap-2 text-sm"
                        >Message template<textarea
                            v-model="templateForm.template"
                            rows="10"
                            maxlength="2000"
                            required
                            class="w-full rounded-md border bg-background p-3"
                        />
                    </label>
                    <InputError :message="templateForm.errors.template" />
                    <Button :disabled="templateForm.processing">Save Default Message</Button>
                </form>
            </details>
            <InputError :message="form.errors.status" />
            <p v-if="!leaves.data.length" class="rounded-xl border p-6">No leave requests yet.</p>
            <section v-for="leave in leaves.data" :key="leave.id" class="space-y-3 rounded-xl border bg-card p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <h2 class="font-semibold">{{ leave.office_staff.code }} - {{ leave.office_staff.name }}</h2>
                    <span class="text-sm capitalize">{{ leave.leave_date.slice(0, 10) }} · {{ leave.status }}</span>
                </div>
                <p class="whitespace-pre-wrap break-words">{{ leave.reason }}</p>
                <p class="text-xs text-muted-foreground">Email: {{ leave.email_status }}</p>
                <div class="flex flex-wrap gap-2">
                    <template v-if="leave.status === 'pending'"
                        ><Button :disabled="form.processing" @click="review(leave.id, 'approved')">Approve</Button
                        ><Button variant="outline" :disabled="form.processing" @click="review(leave.id, 'rejected')">Reject</Button></template
                    ><Button
                        v-if="leave.email_status !== 'sent'"
                        variant="outline"
                        :disabled="form.processing"
                        @click="form.post(`/office-leave-requests/${leave.id}/email`, { preserveScroll: true })"
                        >Retry Email</Button
                    >
                </div>
            </section>
            <div class="flex gap-4">
                <Link v-if="leaves.prev_page_url" :href="leaves.prev_page_url">Previous</Link
                ><Link v-if="leaves.next_page_url" :href="leaves.next_page_url">Next</Link>
            </div>
        </div>
    </AppLayout>
</template>
