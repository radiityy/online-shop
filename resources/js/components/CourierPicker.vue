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

const draftKey = ref<string | null>(null);

const optionTotal = (option: ShippingOption) => option.shipping_cost + option.insurance_fee;

const recommended = computed(() => {
    return props.options.find((option) => props.labels[option.key]?.includes('Cheapest'))
        ?? props.options[0]
        ?? null;
});

const others = computed(() => {
    return props.options.filter((option) => option.key !== recommended.value?.key);
});

const draftOption = computed(() => {
    return props.options.find((option) => option.key === draftKey.value) ?? null;
});

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
};

const dayRange = (duration: string): [number, number] | null => {
    if (!duration) {
        return null;
    }

    if (/same\s*day/i.test(duration)) {
        return [0, 0];
    }

    const numbers = duration.match(/\d+(\.\d+)?/g)?.map(Number);

    if (!numbers?.length) {
        return null;
    }

    const factor = /hour|jam/i.test(duration) ? 1 / 24 : 1;

    return [Math.min(...numbers) * factor, Math.max(...numbers) * factor];
};

const formatDay = (date: Date) => {
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' }).format(date);
};

const arrival = (duration: string) => {
    const range = dayRange(duration);

    if (!range) {
        return null;
    }

    const from = new Date();
    from.setDate(from.getDate() + Math.ceil(range[0]));

    const to = new Date();
    to.setDate(to.getDate() + Math.ceil(range[1]));

    const start = formatDay(from);
    const end = formatDay(to);

    return start === end ? start : `${start} – ${end}`;
};

const badgeClass = (label: string) => {
    return label === 'Cheapest'
        ? 'bg-neutral-950 text-white'
        : 'border border-neutral-950 text-neutral-950';
};

const pick = (option: ShippingOption) => {
    draftKey.value = option.key;
};

const confirm = () => {
    if (!draftOption.value) {
        return;
    }

    emit('select', draftOption.value);
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
            draftKey.value = props.selectedKey ?? recommended.value?.key ?? null;

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
                <header class="flex shrink-0 items-center gap-3 px-5 pb-3 pt-5 sm:px-6">
                    <button
                        type="button"
                        aria-label="Close"
                        class="-ml-2 flex h-10 w-10 shrink-0 items-center justify-center transition hover:opacity-60"
                        @click="emit('close')"
                    >
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5" />
                            <path d="M12 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <h2
                        id="courier-picker-title"
                        class="text-3xl font-black uppercase tracking-[-0.045em]"
                    >
                        Shipment Method
                    </h2>
                </header>

                <div
                    class="flex-1 overflow-y-auto pb-4"
                    role="radiogroup"
                    aria-label="Shipping options"
                >
                    <template v-if="recommended">
                        <p class="px-5 pb-1 pt-5 text-[11px] font-black uppercase tracking-[0.24em] text-neutral-400 sm:px-6">
                            Recommended for you
                        </p>

                        <div
                            role="radio"
                            tabindex="0"
                            :aria-checked="draftKey === recommended.key"
                            class="courier-row relative cursor-pointer px-5 py-6 outline-none transition sm:px-6"
                            :class="draftKey === recommended.key ? 'bg-neutral-50 is-active' : 'hover:bg-neutral-50/70'"
                            @click="pick(recommended)"
                            @keydown.enter.prevent="pick(recommended)"
                            @keydown.space.prevent="pick(recommended)"
                        >
                            <div class="flex items-center gap-4">
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 transition"
                                    :class="draftKey === recommended.key ? 'border-neutral-950' : 'border-neutral-300'"
                                >
                                    <span
                                        v-if="draftKey === recommended.key"
                                        class="h-2.5 w-2.5 rounded-full bg-neutral-950"
                                    ></span>
                                </span>

                                <CourierLogo
                                    :code="recommended.courier_code"
                                    :name="recommended.courier_name"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-lg font-black uppercase tracking-[-0.03em]">
                                            {{ recommended.courier_name }}
                                        </p>

                                        <span
                                            v-for="label in labels[recommended.key] ?? []"
                                            :key="label"
                                            class="px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em]"
                                            :class="badgeClass(label)"
                                        >
                                            {{ label }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-sm text-neutral-500">
                                        {{ recommended.service_name }}<span v-if="recommended.duration"> ({{ recommended.duration }})</span>
                                    </p>

                                    <p
                                        v-if="arrival(recommended.duration)"
                                        class="mt-2 flex items-center gap-1.5 text-xs text-neutral-500"
                                    >
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 7h11v9H3z" />
                                            <path d="M14 10h4l3 3v3h-7" />
                                            <circle cx="7" cy="18" r="1.6" />
                                            <circle cx="17" cy="18" r="1.6" />
                                        </svg>
                                        Arrives <span class="font-black text-neutral-950">{{ arrival(recommended.duration) }}</span>
                                    </p>
                                </div>

                                <p class="shrink-0 text-xl font-black tracking-[-0.02em]">
                                    {{ formatPrice(optionTotal(recommended)) }}
                                </p>
                            </div>

                            <div
                                v-if="draftKey === recommended.key && recommended.insurance_fee > 0"
                                class="ml-10 mt-5 flex items-start gap-3"
                            >
                                <svg viewBox="0 0 24 24" class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z" />
                                    <path d="M9 12l2 2 4-4" />
                                </svg>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black">Shipping insurance included</p>

                                    <p class="mt-0.5 text-xs leading-5 text-neutral-500">
                                        Without insurance, loss refunds are limited to 10x shipping fee.
                                    </p>
                                </div>

                                <p class="shrink-0 text-sm font-black">
                                    +{{ formatPrice(recommended.insurance_fee) }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <template v-if="others.length">
                        <p class="px-5 pb-1 pt-7 text-[11px] font-black uppercase tracking-[0.24em] text-neutral-400 sm:px-6">
                            Other options
                        </p>

                        <div
                            v-for="option in others"
                            :key="option.key"
                            role="radio"
                            tabindex="0"
                            :aria-checked="draftKey === option.key"
                            class="courier-row relative cursor-pointer border-t border-neutral-100 px-5 py-4 outline-none transition first:border-t-0 sm:px-6"
                            :class="draftKey === option.key ? 'bg-neutral-50 is-active' : 'hover:bg-neutral-50/70'"
                            @click="pick(option)"
                            @keydown.enter.prevent="pick(option)"
                            @keydown.space.prevent="pick(option)"
                        >
                            <div class="flex items-center gap-4">
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 transition"
                                    :class="draftKey === option.key ? 'border-neutral-950' : 'border-neutral-300'"
                                >
                                    <span
                                        v-if="draftKey === option.key"
                                        class="h-2.5 w-2.5 rounded-full bg-neutral-950"
                                    ></span>
                                </span>

                                <CourierLogo
                                    :code="option.courier_code"
                                    :name="option.courier_name"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-black uppercase tracking-[-0.02em]">
                                            {{ option.courier_name }}
                                        </p>

                                        <span
                                            v-for="label in labels[option.key] ?? []"
                                            :key="label"
                                            class="px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em]"
                                            :class="badgeClass(label)"
                                        >
                                            {{ label }}
                                        </span>
                                    </div>

                                    <p class="mt-0.5 text-sm text-neutral-500">
                                        {{ option.service_name }}<span v-if="option.duration"> ({{ option.duration }})</span>
                                        <span v-if="arrival(option.duration)"> · arrives <span class="font-black text-neutral-950">{{ arrival(option.duration) }}</span></span>
                                    </p>
                                </div>

                                <p class="shrink-0 text-sm font-black">
                                    {{ formatPrice(optionTotal(option)) }}
                                </p>
                            </div>

                            <div
                                v-if="draftKey === option.key && option.insurance_fee > 0"
                                class="ml-10 mt-4 flex items-start gap-3"
                            >
                                <svg viewBox="0 0 24 24" class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z" />
                                    <path d="M9 12l2 2 4-4" />
                                </svg>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black">Shipping insurance included</p>

                                    <p class="mt-0.5 text-xs leading-5 text-neutral-500">
                                        Without insurance, loss refunds are limited to 10x shipping fee.
                                    </p>
                                </div>

                                <p class="shrink-0 text-sm font-black">
                                    +{{ formatPrice(option.insurance_fee) }}
                                </p>
                            </div>
                        </div>
                    </template>
                </div>

                <footer class="flex shrink-0 items-center gap-5 border-t border-neutral-200 px-5 py-4 sm:px-6">
                    <div class="shrink-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-neutral-400">
                            Shipping + insurance
                        </p>

                        <p class="text-xl font-black tracking-[-0.02em]">
                            {{ draftOption ? formatPrice(optionTotal(draftOption)) : '-' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="!draftOption"
                        class="flex-1 bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
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

.courier-row.is-active::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 3px;
    background: #0a0a0a;
}
</style>