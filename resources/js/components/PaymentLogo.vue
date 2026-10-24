<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        methodKey: string;
        label: string;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);

const attempt = ref(0);

const sources = computed(() => [
    `/images/payments/${props.methodKey}.svg`,
    `/images/payments/${props.methodKey}.png`,
]);

const src = computed(() => sources.value[attempt.value] ?? null);

const initials = computed(() => props.methodKey.split('_')[0].slice(0, 5).toUpperCase());

watch(
    () => props.methodKey,
    () => {
        attempt.value = 0;
    },
);

const onError = () => {
    attempt.value += 1;
};
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center"
        :class="size === 'sm' ? 'h-8 w-14' : 'h-10 w-20'"
    >
        <img
            v-if="src"
            :key="src"
            :src="src"
            :alt="label"
            class="max-h-full max-w-full object-contain"
            @error="onError"
        />

        <span
            v-else
            class="flex h-full w-full items-center justify-center bg-neutral-100 text-[11px] font-black uppercase tracking-[0.12em] text-neutral-600"
        >
            {{ initials }}
        </span>
    </div>
</template>