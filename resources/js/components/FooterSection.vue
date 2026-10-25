<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    title: string;
}>();

const open = ref(false);
</script>

<template>
    <div class="border-t border-white/10 md:border-t-0">
        <button
            type="button"
            class="flex w-full items-center justify-between py-4 text-left md:pointer-events-none md:cursor-default md:py-0"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="text-sm font-black uppercase tracking-[0.25em] max-md:text-xs max-md:tracking-[0.18em]">
                {{ title }}
            </span>

            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 transition-transform md:hidden"
                :class="open ? 'rotate-180' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <div class="pb-6 md:mt-5 md:block md:pb-0" :class="open ? 'block' : 'hidden'">
            <slot />
        </div>
    </div>
</template>