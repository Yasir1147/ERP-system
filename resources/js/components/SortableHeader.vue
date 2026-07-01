<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';

defineProps<{
    label: string;
    column: string;
    sortKey: string;
    sortDirection: 'asc' | 'desc';
    align?: 'left' | 'right' | 'center';
}>();

defineEmits<{
    sort: [column: string];
}>();
</script>

<template>
    <button
        type="button"
        class="inline-flex w-full items-center gap-1 hover:text-foreground"
        :class="{
            'justify-end text-right': align === 'right',
            'justify-center text-center': align === 'center',
            'justify-start text-left': !align || align === 'left',
        }"
        @click="$emit('sort', column)"
    >
        <span class="truncate">{{ label }}</span>
        <ArrowUp v-if="sortKey === column && sortDirection === 'asc'" class="size-3 shrink-0" />
        <ArrowDown v-else-if="sortKey === column && sortDirection === 'desc'" class="size-3 shrink-0" />
        <ArrowUpDown v-else class="size-3 shrink-0 opacity-60" />
    </button>
</template>
