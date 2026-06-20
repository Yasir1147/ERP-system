<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    message: string;
}>();
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
            <Link href="/login" class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">
                Go to Login
            </Link>
        </section>
    </main>
</template>
