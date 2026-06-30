<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    message: string;
}>();

const page = usePage<SharedData>();
const isAuthenticated = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <Head title="Access denied" />

    <main class="flex min-h-svh items-center justify-center bg-background px-4 py-8 text-foreground">
        <section class="flex w-full max-w-md flex-col items-center gap-5 rounded-lg border bg-card p-6 text-center shadow-sm">
            <AppLogoIcon class="size-24" />
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Access Denied</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ message }}</p>
            </div>
            <Link
                v-if="isAuthenticated"
                href="/logout"
                method="post"
                as="button"
                class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
            >
                Log out
            </Link>
            <Link v-else href="/login" class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">
                Go to Login
            </Link>
        </section>
    </main>
</template>
