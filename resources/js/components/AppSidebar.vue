<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { findActiveModule, pathMatches } from '@/navigation/adminNavigation';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const activeModule = computed(() => findActiveModule(page.url));
const contextualItems = computed(() => activeModule.value.items ?? [{ title: activeModule.value.title, href: activeModule.value.href }]);
const activeContextHref = computed(
    () =>
        contextualItems.value.filter((item) => pathMatches(page.url, item.href)).sort((first, second) => second.href.length - first.href.length)[0]
            ?.href,
);
const isNestedItemActive = (href: string) => pathMatches(page.url, href);
const isContextGroupActive = (href: string, items = contextualItems.value) =>
    pathMatches(page.url, href) || items.some((item) => item.href === href && item.items?.some((child) => isNestedItemActive(child.href)));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="top-16 h-[calc(100svh-4rem)]">
        <SidebarContent>
            <SidebarGroup>
                <SidebarGroupLabel>{{ activeModule.title }}</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <template v-for="item in contextualItems" :key="item.href">
                            <Collapsible v-if="item.items?.length" as-child :default-open="isContextGroupActive(item.href)" class="group/collapsible">
                                <SidebarMenuItem>
                                    <CollapsibleTrigger as-child>
                                        <SidebarMenuButton :tooltip="item.title" :is-active="isContextGroupActive(item.href)">
                                            <component :is="item.icon ?? activeModule.icon" class="size-4" aria-hidden="true" />
                                            <span>{{ item.title }}</span>
                                            <ChevronRight
                                                class="ml-auto size-3.5 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                                aria-hidden="true"
                                            />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            <SidebarMenuSubItem v-for="child in item.items" :key="child.href">
                                                <SidebarMenuSubButton as-child :is-active="isNestedItemActive(child.href)">
                                                    <Link :href="child.href">{{ child.title }}</Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>

                            <SidebarMenuItem v-else>
                                <SidebarMenuButton as-child :tooltip="item.title" :is-active="activeContextHref === item.href">
                                    <Link :href="item.href">
                                        <component :is="activeModule.icon" class="size-4" aria-hidden="true" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </template>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
