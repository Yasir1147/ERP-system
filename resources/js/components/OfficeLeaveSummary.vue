<script setup lang="ts">
defineProps<{ rows: { id: number; date: string; staffName: string; label: string; reason: string }[] }>();
</script>
<template>
    <section v-if="rows.length" class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
        <h3 class="font-semibold">Leave in Selected Date Range</h3>
        <p class="mb-3 text-sm text-muted-foreground">Leave days are separate from worked days and hours.</p>
        <div v-for="leave in rows" :key="leave.id" class="flex flex-wrap justify-between gap-2 border-t border-amber-200 py-3 text-sm">
            <span>{{ leave.date }} · {{ leave.staffName }}</span>
            <span class="font-medium text-amber-800">{{ leave.label }}</span>
        </div>
    </section>
</template>
