<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    settings: {
        mail_enabled: boolean;
        mail_host: string;
        mail_port: number;
        mail_username: string;
        mail_encryption: string;
        mail_from_address: string;
        mail_from_name: string;
        password_configured: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mail settings',
        href: '/settings/mail',
    },
];

const form = useForm({
    mail_enabled: props.settings.mail_enabled,
    mail_host: props.settings.mail_host,
    mail_port: String(props.settings.mail_port || 587),
    mail_username: props.settings.mail_username,
    mail_password: '',
    mail_encryption: props.settings.mail_encryption || 'tls',
    mail_from_address: props.settings.mail_from_address,
    mail_from_name: props.settings.mail_from_name,
});

const submit = () => {
    form.put(route('mail.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('mail_password'),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Mail settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Mail settings" description="Configure SMTP for fine ticket email notifications" />

                <form class="space-y-6" @submit.prevent="submit">
                    <label class="flex items-center gap-3 text-sm font-medium">
                        <input v-model="form.mail_enabled" type="checkbox" class="size-4 rounded border-input" />
                        Enable fine ticket emails
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="mail-host">SMTP Host</Label>
                            <Input id="mail-host" v-model="form.mail_host" type="text" placeholder="smtp.gmail.com" />
                            <InputError :message="form.errors.mail_host" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail-port">SMTP Port</Label>
                            <Input id="mail-port" v-model="form.mail_port" type="number" min="1" max="65535" placeholder="587" />
                            <InputError :message="form.errors.mail_port" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="mail-username">SMTP Username</Label>
                            <Input id="mail-username" v-model="form.mail_username" type="text" placeholder="email@example.com" />
                            <InputError :message="form.errors.mail_username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail-password">SMTP Password</Label>
                            <Input id="mail-password" v-model="form.mail_password" type="password" placeholder="Leave blank to keep current password" />
                            <p class="text-xs text-muted-foreground">
                                {{ settings.password_configured ? 'Password is already configured.' : 'No password configured yet.' }}
                            </p>
                            <InputError :message="form.errors.mail_password" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail-encryption">Encryption</Label>
                        <select
                            id="mail-encryption"
                            v-model="form.mail_encryption"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                        <InputError :message="form.errors.mail_encryption" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="mail-from-address">From Email</Label>
                            <Input id="mail-from-address" v-model="form.mail_from_address" type="email" placeholder="noreply@example.com" />
                            <InputError :message="form.errors.mail_from_address" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail-from-name">From Name</Label>
                            <Input id="mail-from-name" v-model="form.mail_from_name" type="text" placeholder="Al Mohafiz" />
                            <InputError :message="form.errors.mail_from_name" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save Mail Settings</Button>
                        <TransitionRoot :show="form.recentlySuccessful" enter="transition ease-in-out" enter-from="opacity-0" leave="transition ease-in-out" leave-to="opacity-0">
                            <p class="text-sm text-neutral-600">Saved.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
