<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        code: string;
        name: string;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);

const failed = ref(false);

watch(
    () => props.code,
    () => {
        failed.value = false;
    },
);

const src = computed(() => `/images/couriers/${encodeURIComponent(props.code.toLowerCase())}.svg`);

const initials = computed(() => {
    const words = props.name
        .replace(/[^A-Za-z0-9 ]/g, '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    if (words.length === 1) {
        return words[0].slice(0, 2).toUpperCase();
    }

    return (words[0][0] + words[1][0]).toUpperCase();
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center border border-neutral-200 bg-white"
        :class="size === 'sm' ? 'h-10 w-10' : 'h-12 w-12'"
    >
        <img
            v-if="!failed"
            :src="src"
            :alt="name"
            class="h-full w-full object-contain p-1.5"
            @error="failed = true"
        />

        <span
            v-else
            class="text-[11px] font-black tracking-[0.08em] text-neutral-950"
        >
            {{ initials }}
        </span>
    </div>
</template>