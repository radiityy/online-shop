<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

export type Area = {
    id: string;
    name: string;
    province: string;
    city: string;
    district: string;
    postal_code: string | null;
};

const props = withDefaults(
    defineProps<{
        initialName?: string | null;
        error?: string;
        placeholder?: string;
    }>(),
    {
        initialName: null,
        error: undefined,
        placeholder: 'Type district or city (min. 3 letters)',
    },
);

const emit = defineEmits<{
    (e: 'select', area: Area): void;
    (e: 'clear'): void;
}>();

const query = ref(props.initialName ?? '');
const results = ref<Area[]>([]);
const isOpen = ref(false);
const isLoading = ref(false);
const message = ref<string | null>(null);
const hasSelection = ref(Boolean(props.initialName));

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;
let requestId = 0;

const search = async (term: string) => {
    controller?.abort();
    controller = new AbortController();

    const current = ++requestId;

    isLoading.value = true;
    message.value = null;

    try {
        const response = await fetch(`/shipping/areas?q=${encodeURIComponent(term)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        const payload = await response.json().catch(() => ({}));

        if (current !== requestId) {
            return;
        }

        if (!response.ok) {
            results.value = [];
            message.value = payload.message ?? 'Area search failed. Please try again.';
            isOpen.value = true;

            return;
        }

        results.value = Array.isArray(payload.areas) ? payload.areas : [];
        message.value = results.value.length ? null : 'No area found. Try another district or city name.';
        isOpen.value = true;
    } catch (error) {
        if ((error as DOMException).name === 'AbortError' || current !== requestId) {
            return;
        }

        results.value = [];
        message.value = 'Area search failed. Please try again.';
        isOpen.value = true;
    } finally {
        if (current === requestId) {
            isLoading.value = false;
        }
    }
};

const onInput = (event: Event) => {
    query.value = (event.target as HTMLInputElement).value;

    if (hasSelection.value) {
        hasSelection.value = false;
        emit('clear');
    }

    if (timer) {
        clearTimeout(timer);
    }

    const term = query.value.trim();

    if (term.length < 3) {
        controller?.abort();
        requestId++;
        results.value = [];
        message.value = null;
        isLoading.value = false;
        isOpen.value = false;

        return;
    }

    timer = setTimeout(() => search(term), 500);
};

const pick = (area: Area) => {
    hasSelection.value = true;
    query.value = area.name;
    isOpen.value = false;
    results.value = [];
    message.value = null;

    emit('select', area);
};

const onBlur = () => {
    setTimeout(() => {
        isOpen.value = false;
    }, 150);
};

const onFocus = () => {
    if (results.value.length || message.value) {
        isOpen.value = true;
    }
};

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }

    controller?.abort();
});
</script>

<template>
    <div class="relative">
        <input
            :value="query"
            type="text"
            autocomplete="off"
            class="w-full border bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
            :class="error ? 'border-red-400' : 'border-neutral-300'"
            :placeholder="placeholder"
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
        />

        <p
            v-if="isLoading"
            class="mt-2 text-xs font-bold uppercase tracking-[0.16em] text-neutral-500"
        >
            Searching...
        </p>

        <p
            v-else-if="error"
            class="mt-2 text-sm text-red-600"
        >
            {{ error }}
        </p>

        <div
            v-if="isOpen && (results.length || message)"
            class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-y-auto border border-neutral-950 bg-white shadow-xl"
        >
            <p
                v-if="message"
                class="px-4 py-3 text-sm text-neutral-600"
            >
                {{ message }}
            </p>

            <button
                v-for="area in results"
                :key="area.id"
                type="button"
                class="block w-full border-b border-neutral-100 px-4 py-3 text-left text-sm text-neutral-800 transition last:border-b-0 hover:bg-neutral-100"
                @mousedown.prevent="pick(area)"
            >
                {{ area.name }}
            </button>
        </div>
    </div>
</template>