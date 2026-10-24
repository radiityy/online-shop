<script setup lang="ts">
import PaymentLogo from '@/components/PaymentLogo.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type PaymentMethod = {
    key: string;
    label: string;
    payment_type: string;
    bank: string | null;
    group: 'virtual_account' | 'other';
    fee_flat: number;
    fee_percent: number;
};

const props = defineProps<{
    open: boolean;
    methods: PaymentMethod[];
    selectedKey: string | null;
    fees: Record<string, number>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', key: string): void;
}>();

const hints: Record<string, string> = {
    qris: 'OVO, DANA, ShopeePay & more',
    gopay: 'GoPay app',
};

const draft = ref<string | null>(null);
const view = ref<'main' | 'va'>('main');
const othersOpen = ref(true);

const vaMethods = computed(() => props.methods.filter((method) => method.group === 'virtual_account'));
const otherMethods = computed(() => props.methods.filter((method) => method.group === 'other'));

const draftMethod = computed(() => props.methods.find((method) => method.key === draft.value) ?? null);
const draftIsVa = computed(() => draftMethod.value?.group === 'virtual_account');

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
};

const feeLabel = (key: string) => {
    const fee = props.fees[key] ?? 0;

    return fee > 0 ? `Fee ${formatPrice(fee)}` : 'No fee';
};

const otherSubtitle = (method: PaymentMethod) => {
    return [hints[method.key], feeLabel(method.key)].filter(Boolean).join(' • ');
};

const pickBank = (key: string) => {
    draft.value = key;
    view.value = 'main';
};

const confirm = () => {
    if (!draft.value) {
        return;
    }

    emit('select', draft.value);
    emit('close');
};

const onKeydown = (event: KeyboardEvent) => {
    if (props.open && event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            draft.value = props.selectedKey;
            view.value = 'main';
            othersOpen.value = true;
        }
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/60 sm:items-center sm:p-6"
        @click.self="emit('close')"
    >
        <div class="max-h-[90vh] w-full max-w-md overflow-y-auto bg-white px-6 pb-6 pt-5">
            <div class="flex items-center justify-between">
                <button
                    v-if="view === 'va'"
                    type="button"
                    class="flex h-8 w-8 items-center justify-center transition hover:opacity-60"
                    aria-label="Back"
                    @click="view = 'main'"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 6l-6 6 6 6" />
                    </svg>
                </button>
                <span v-else class="h-8 w-8"></span>

                <h3 class="text-base font-semibold">
                    {{ view === 'va' ? 'Virtual Account' : 'Payment Method' }}
                </h3>

                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center transition hover:opacity-60"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>

            <div v-if="view === 'main'" class="mt-4">
                <button
                    v-if="vaMethods.length"
                    type="button"
                    class="flex w-full items-center gap-4 border-b border-neutral-200 py-4 text-left transition hover:bg-neutral-50"
                    @click="view = 'va'"
                >
                    <PaymentLogo
                        v-if="draftIsVa && draftMethod"
                        :method-key="draftMethod.key"
                        :label="draftMethod.label"
                    />

                    <span
                        v-else
                        class="flex h-10 w-20 shrink-0 items-center justify-center bg-neutral-100 text-neutral-500"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 10l9-6 9 6M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-medium text-neutral-950">Virtual Account</p>
                        <p class="mt-0.5 text-sm text-neutral-500">
                            {{ draftIsVa && draftMethod ? `${draftMethod.label} • ${feeLabel(draftMethod.key)}` : 'Choose your bank' }}
                        </p>
                    </div>

                    <span
                        v-if="draftIsVa"
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-neutral-950 text-white"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12.5l4.5 4.5L19 7.5" />
                        </svg>
                    </span>

                    <svg
                        v-else
                        class="h-4 w-4 shrink-0 text-neutral-500"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </button>

                <div v-if="otherMethods.length" class="mt-6">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between text-left"
                        @click="othersOpen = !othersOpen"
                    >
                        <span class="text-sm font-semibold text-neutral-500">Other Methods</span>

                        <svg
                            class="h-4 w-4 text-neutral-500 transition"
                            :class="othersOpen ? '' : 'rotate-180'"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M6 15l6-6 6 6" />
                        </svg>
                    </button>

                    <div v-if="othersOpen" class="mt-2">
                        <button
                            v-for="method in otherMethods"
                            :key="method.key"
                            type="button"
                            class="flex w-full items-center gap-4 border-b border-neutral-200 py-4 text-left transition hover:bg-neutral-50"
                            @click="draft = method.key"
                        >
                            <PaymentLogo :method-key="method.key" :label="method.label" />

                            <div class="min-w-0 flex-1">
                                <p class="text-[15px] font-medium text-neutral-950">{{ method.label }}</p>
                                <p class="mt-0.5 text-sm text-neutral-500">{{ otherSubtitle(method) }}</p>
                            </div>

                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition"
                                :class="draft === method.key ? 'border-neutral-950 bg-neutral-950 text-white' : 'border-neutral-300'"
                            >
                                <svg
                                    v-if="draft === method.key"
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M5 12.5l4.5 4.5L19 7.5" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <p
                    v-if="!methods.length"
                    class="mt-6 text-sm text-neutral-500"
                >
                    No payment method is available right now.
                </p>

                <button
                    type="button"
                    :disabled="!draft"
                    class="mt-8 w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                    @click="confirm"
                >
                    Confirm
                </button>
            </div>

            <div v-else class="mt-4">
                <button
                    v-for="method in vaMethods"
                    :key="method.key"
                    type="button"
                    class="flex w-full items-center gap-4 border-b border-neutral-200 py-4 text-left transition hover:bg-neutral-50"
                    @click="pickBank(method.key)"
                >
                    <PaymentLogo :method-key="method.key" :label="method.label" />

                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-medium text-neutral-950">{{ method.label }}</p>
                        <p class="mt-0.5 text-sm text-neutral-500">Virtual Account • {{ feeLabel(method.key) }}</p>
                    </div>

                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition"
                        :class="draft === method.key ? 'border-neutral-950 bg-neutral-950 text-white' : 'border-neutral-300'"
                    >
                        <svg
                            v-if="draft === method.key"
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M5 12.5l4.5 4.5L19 7.5" />
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>