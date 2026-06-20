<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const logoFailed = ref(false);
</script>

<template>
    <img
        v-if="!logoFailed"
        src="/al-mohafiz-logo.png"
        alt="Al Mohafiz"
        :class="className"
        class="object-contain"
        v-bind="$attrs"
        @error="logoFailed = true"
    />
    <div v-else :class="className" class="flex items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white" v-bind="$attrs">
        AM
    </div>
</template>
