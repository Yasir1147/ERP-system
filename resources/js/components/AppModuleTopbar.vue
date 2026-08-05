<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';
import { adminNavigation, findActiveModule } from '@/navigation/adminNavigation';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const activeModule = computed(() => findActiveModule(page.url));
</script>

<template>
    <header class="fixed inset-x-0 top-0 z-50 flex h-16 border-b border-border bg-zinc-100 shadow-sm dark:bg-zinc-900">
        <div
            class="flex h-full w-[156px] shrink-0 items-center gap-2 border-r border-border px-3 transition-[width] md:w-[--sidebar-width] group-has-[[data-collapsible=icon]]/sidebar-wrapper:md:w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4)_+2px)]"
        >
            <SidebarTrigger class="shrink-0" />
            <Link href="/dashboard" class="min-w-0 flex-1 overflow-hidden">
                <AppLogo />
            </Link>
        </div>

        <nav aria-label="Main modules" class="min-w-0 flex-1 overflow-x-auto [scrollbar-width:thin]">
            <div class="flex h-full min-w-max items-stretch px-1 sm:px-2">
                <Link
                    v-for="item in adminNavigation"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="activeModule.title === item.title ? 'page' : undefined"
                    class="group relative flex h-16 min-w-[76px] flex-col items-center justify-center gap-1 px-3 py-2 text-xs font-medium text-zinc-600 transition-colors hover:bg-white/70 hover:text-zinc-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white sm:min-w-[88px]"
                    :class="activeModule.title === item.title ? 'bg-white/80 text-zinc-950 dark:bg-zinc-800 dark:text-white' : ''"
                >
                    <component :is="item.icon" class="size-5 shrink-0" aria-hidden="true" />
                    <span class="max-w-24 truncate">{{ item.title }}</span>
                    <span
                        class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-foreground transition-opacity"
                        :class="activeModule.title === item.title ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
                    />
                </Link>
            </div>
        </nav>
    </header>
</template>
