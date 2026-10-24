<script setup lang="ts">
import CourierLogo from '@/components/CourierLogo.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type ShippingOption = {
    key: string;
    courier_code: string;
    courier_name: string;
    service_code: string;
    service_name: string;
    description: string;
    duration: string;
    shipping_cost: number;
    insurance_fee: number;
    total: number;
};

const props = defineProps<{
    open: boolean;
    options: ShippingOption[];
    selectedKey: string | null;
    labels: Record<string, string[]>;
}>();

const emit = defineEmits<{
    close: [];
    select: [option: ShippingOption];
}>();

const pendingKey = ref<string | null>(props.selectedKey);

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
};

const optionTotal = (option: ShippingOption) => option.shipping_cost + option.insurance_fee;

const sortedOptions = computed(() => {
    return [...props.options].sort((a, b) => optionTotal(a) - optionTotal(b));
});

const recommended = computed(() => sortedOptions.value[0] ?? null);

const others = computed(() => sortedOptions.value.slice(1));

const pendingOption = computed(() => {
    return props.options.find((option) => option.key === pendingKey.value) ?? null;
});

const isPending = (option: ShippingOption) => option.key === pendingKey.value;

const pick = (option: ShippingOption) => {
    pendingKey.value = option.key;
};

const confirm = () => {
    if (pendingOption.value) {
        emit('select', pendingOption.value);
    }

    emit('close');
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            pendingKey.value = props.selectedKey;
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', onKeydown);

            return;
        }

        document.body.style.overflow = '';
        window.removeEventListener('keydown', onKeydown);
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[150] flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-6"
            @click.self="emit('close')"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="courier-picker-title"
                class="courier-picker-panel flex max-h-[92dvh] w-full flex-col bg-white shadow-2xl sm:max-w-xl"
            >
                <header class="flex shrink-0 items-center gap-3 px-5 py-5 sm:px-6">
                    <button
                        type="button"
                        aria-label="Back"
                        class="flex h-9 w-9 items-center justify-center transition hover:opacity-60"
                        @click="emit('close')"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M15 6l-6 6 6 6" />
                        </svg>
                    </button>

                    <h2
                        id="courier-picker-title"
                        class="text-xl font-black uppercase tracking-[-0.03em]"
                    >
                        Shipment Method
                    </h2>
                </header>

                <div
                    role="radiogroup"
                    aria-labelledby="courier-picker-title"
                    class="flex-1 overflow-y-auto px-5 pb-4 sm:px-6"
                >
                    <template v-if="recommended">
                        <p class="text-[11px] font-black uppercase tracking-[0.3em] text-neutral-500">
                            Recommended for you
                        </p>

                        <div class="mt-3">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="isPending(recommended)"
                                class="flex w-full items-center gap-3 border px-4 py-4 text-left transition sm:gap-4"
                                :class="isPending(recommended)
                                    ? 'border-neutral-950 bg-neutral-50'
                                    : 'border-neutral-200 hover:border-neutral-950'"
                                @click="pick(recommended)"
                            >
                                <span
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                    :class="isPending(recommended) ? 'border-neutral-950' : 'border-neutral-300'"
                                >
                                    <span
                                        v-if="isPending(recommended)"
                                        class="h-2.5 w-2.5 rounded-full bg-neutral-950"
                                    ></span>
                                </span>

                                <CourierLogo
                                    :code="recommended.courier_code"
                                    :name="recommended.courier_name"
                                    size="sm"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-black uppercase tracking-[-0.02em]">
                                            {{ recommended.courier_name }}
                                        </p>

                                        <span
                                            v-for="label in labels[recommended.key] ?? []"
                                            :key="label"
                                            class="bg-neutral-950 px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-white"
                                        >
                                            {{ label }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-sm text-neutral-600">
                                        {{ recommended.service_name }}<span v-if="recommended.duration"> ({{ recommended.duration }})</span>
                                    </p>
                                </div>

                                <p class="shrink-0 text-sm font-black">
                                    {{ formatPrice(optionTotal(recommended)) }}
                                </p>
                            </button>

                            <div
                                v-if="isPending(recommended) && recommended.insurance_fee > 0"
                                class="flex items-start gap-3 px-4 pb-1 pt-4"
                            >
                                <input
                                    type="checkbox"
                                    checked
                                    disabled
                                    class="mt-0.5 h-4 w-4 rounded-none border-neutral-300 text-neutral-950"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between gap-4 text-sm">
                                        <span class="font-bold">Shipping insurance required</span>
                                        <span class="shrink-0 font-bold">+ {{ formatPrice(recommended.insurance_fee) }}</span>
                                    </div>

                                    <p class="mt-1 text-xs leading-5 text-neutral-500">
                                        Without insurance, loss refunds are limited to 10x shipping fee.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-if="others.length">
                        <p class="mt-8 text-[11px] font-black uppercase tracking-[0.3em] text-neutral-500">
                            Other options
                        </p>

                        <div class="mt-3 space-y-3">
                            <div
                                v-for="option in others"
                                :key="option.key"
                            >
                                <button
                                    type="button"
                                    role="radio"
                                    :aria-checked="isPending(option)"
                                    class="flex w-full items-center gap-3 border px-4 py-4 text-left transition sm:gap-4"
                                    :class="isPending(option)
                                        ? 'border-neutral-950 bg-neutral-50'
                                        : 'border-neutral-200 hover:border-neutral-950'"
                                    @click="pick(option)"
                                >
                                    <span
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                        :class="isPending(option) ? 'border-neutral-950' : 'border-neutral-300'"
                                    >
                                        <span
                                            v-if="isPending(option)"
                                            class="h-2.5 w-2.5 rounded-full bg-neutral-950"
                                        ></span>
                                    </span>

                                    <CourierLogo
                                        :code="option.courier_code"
                                        :name="option.courier_name"
                                        size="sm"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-black uppercase tracking-[-0.02em]">
                                                {{ option.courier_name }}
                                            </p>

                                            <span
                                                v-for="label in labels[option.key] ?? []"
                                                :key="label"
                                                class="bg-neutral-950 px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-white"
                                            >
                                                {{ label }}
                                            </span>
                                        </div>

                                        <p class="mt-1 text-sm text-neutral-600">
                                            {{ option.service_name }}<span v-if="option.duration"> ({{ option.duration }})</span>
                                        </p>
                                    </div>

                                    <p class="shrink-0 text-sm font-black">
                                        {{ formatPrice(optionTotal(option)) }}
                                    </p>
                                </button>

                                <div
                                    v-if="isPending(option) && option.insurance_fee > 0"
                                    class="flex items-start gap-3 px-4 pb-1 pt-4"
                                >
                                    <input
                                        type="checkbox"
                                        checked
                                        disabled
                                        class="mt-0.5 h-4 w-4 rounded-none border-neutral-300 text-neutral-950"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex justify-between gap-4 text-sm">
                                            <span class="font-bold">Shipping insurance required</span>
                                            <span class="shrink-0 font-bold">+ {{ formatPrice(option.insurance_fee) }}</span>
                                        </div>

                                        <p class="mt-1 text-xs leading-5 text-neutral-500">
                                            Without insurance, loss refunds are limited to 10x shipping fee.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <footer class="shrink-0 border-t border-neutral-200 px-5 py-4 sm:px-6">
                    <button
                        type="button"
                        :disabled="!pendingOption"
                        class="w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                        @click="confirm"
                    >
                        Confirm
                    </button>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<style>
@keyframes courier-picker-in {
    from {
        opacity: 0;
        transform: translateY(24px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.courier-picker-panel {
    animation: courier-picker-in 0.2s ease-out;
}
</style>