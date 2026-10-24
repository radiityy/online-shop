<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AddressPicker from '@/components/AddressPicker.vue';
import CourierLogo from '@/components/CourierLogo.vue';
import CourierPicker from '@/components/CourierPicker.vue';
import CheckoutLayout from '@/layouts/CheckoutLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type CheckoutItem = {
    id: number;
    quantity: number;
    unit_price: number;
    subtotal: number;
    product: {
        id: number;
        name: string;
        slug: string;
        image_path: string | null;
    };
    variant: {
        id: number;
        size: string | null;
        color: string | null;
        sku: string | null;
        stock: number;
        additional_price: string;
    };
};

type Address = {
    id: number;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    area_id: string | null;
    area_name: string | null;
    postal_code: string | null;
    full_address: string;
    is_default: boolean;
};

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

type CheckoutForm = {
    address_id: number | null;
    notes: string;
    shipping_courier_code: string;
    shipping_service_code: string;
    agree_terms: boolean;
};

const props = defineProps<{
    items: CheckoutItem[];
    summary: {
        subtotal: number;
        total_items: number;
        total_weight: number;
    };
    addresses: Address[];
    defaultAddress: Address | null;
}>();

const isPickerOpen = ref(false);
const isCourierOpen = ref(false);
const pickedAddress = ref<Address | null>(null);

const address = computed<Address | null>(() => {
    const picked = pickedAddress.value;

    if (picked) {
        return props.addresses.find((item) => item.id === picked.id) ?? picked;
    }

    if (props.defaultAddress?.area_id) {
        return props.defaultAddress;
    }

    return props.addresses.find((item) => item.area_id) ?? props.defaultAddress ?? null;
});

const addressUsable = computed(() => Boolean(address.value?.area_id));

const options = ref<ShippingOption[]>([]);
const selectedKey = ref<string | null>(null);
const isLoadingRates = ref(Boolean(address.value?.area_id));
const ratesError = ref<string | null>(null);

let ratesController: AbortController | null = null;
let ratesRequestId = 0;

const form = useForm<CheckoutForm>({
    address_id: address.value?.id ?? null,
    notes: '',
    shipping_courier_code: '',
    shipping_service_code: '',
    agree_terms: false,
});

const optionTotal = (option: ShippingOption) => option.shipping_cost + option.insurance_fee;

const durationDays = (duration: string | null | undefined) => {
    if (!duration) {
        return Number.POSITIVE_INFINITY;
    }

    if (/same\s*day/i.test(duration)) {
        return 0;
    }

    const numbers = duration.match(/\d+(\.\d+)?/g);

    if (!numbers) {
        return Number.POSITIVE_INFINITY;
    }

    const smallest = Math.min(...numbers.map(Number));

    return /hour|jam/i.test(duration) ? smallest / 24 : smallest;
};

const compare = (a: number, b: number) => {
    if (a === b) {
        return 0;
    }

    return a < b ? -1 : 1;
};

const cheapestOption = (list: ShippingOption[]) => {
    return [...list].sort((a, b) => {
        return compare(optionTotal(a), optionTotal(b))
            || compare(durationDays(a.duration), durationDays(b.duration));
    })[0] ?? null;
};

const fastestOption = (list: ShippingOption[]) => {
    return [...list].sort((a, b) => {
        return compare(durationDays(a.duration), durationDays(b.duration))
            || compare(optionTotal(a), optionTotal(b));
    })[0] ?? null;
};

const optionLabels = computed<Record<string, string[]>>(() => {
    const labels: Record<string, string[]> = {};
    const list = options.value;

    if (list.length < 2) {
        return labels;
    }

    const cheapest = cheapestOption(list);
    const fastest = fastestOption(list);

    if (cheapest && list.some((item) => optionTotal(item) > optionTotal(cheapest))) {
        labels[cheapest.key] = [...(labels[cheapest.key] ?? []), 'Cheapest'];
    }

    if (
        fastest
        && Number.isFinite(durationDays(fastest.duration))
        && list.some((item) => durationDays(item.duration) > durationDays(fastest.duration))
    ) {
        labels[fastest.key] = [...(labels[fastest.key] ?? []), 'Fastest'];
    }

    return labels;
});

const selectedOption = computed(() => {
    return options.value.find((option) => option.key === selectedKey.value) ?? null;
});

const canChangeCourier = computed(() => options.value.length > 1);

const shippingCost = computed(() => selectedOption.value?.shipping_cost ?? null);

const insuranceFee = computed(() => selectedOption.value?.insurance_fee ?? 0);

const grandTotal = computed(() => {
    if (!selectedOption.value) {
        return props.summary.subtotal;
    }

    return props.summary.subtotal + selectedOption.value.shipping_cost + selectedOption.value.insurance_fee;
});

const weightLabel = computed(() => {
    return (props.summary.total_weight / 1000).toFixed(1).replace(/\.0$/, '');
});

const shippingError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.shipping_option ?? errors.shipping_courier_code ?? errors.shipping_service_code ?? null;
});

const canSubmit = computed(() => {
    return addressUsable.value
        && selectedOption.value !== null
        && form.agree_terms
        && !form.processing
        && !isLoadingRates.value;
});

const storageUrl = (path: string | null | undefined) => {
    if (!path) return '/placeholder-product.jpg';

    return `/storage/${path}`;
};

const formatPrice = (price: string | number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(price));
};

const sizeLabel = (item: CheckoutItem) => {
    return item.variant.size ?? item.variant.sku ?? 'Default';
};

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const resetShipping = () => {
    options.value = [];
    selectedKey.value = null;
    ratesError.value = null;
    form.shipping_courier_code = '';
    form.shipping_service_code = '';
};

const selectOption = (option: ShippingOption) => {
    selectedKey.value = option.key;
    form.shipping_courier_code = option.courier_code;
    form.shipping_service_code = option.service_code;
    form.clearErrors('shipping_courier_code', 'shipping_service_code');
};

const fetchRates = async (addressId: number) => {
    ratesController?.abort();
    ratesController = new AbortController();

    const current = ++ratesRequestId;

    resetShipping();
    isLoadingRates.value = true;

    try {
        const response = await fetch('/shipping/rates', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            signal: ratesController.signal,
            body: JSON.stringify({ address_id: addressId }),
        });

        const data = await response.json().catch(() => ({}));

        if (current !== ratesRequestId) {
            return;
        }

        if (!response.ok) {
            ratesError.value = data.message ?? 'Shipping rates could not be loaded. Please try again.';

            return;
        }

        options.value = Array.isArray(data.options) ? data.options : [];

        if (!options.value.length) {
            ratesError.value = 'No shipping option is available for this address.';

            return;
        }

        selectOption(cheapestOption(options.value) ?? options.value[0]);
    } catch (error) {
        if ((error as DOMException).name === 'AbortError' || current !== ratesRequestId) {
            return;
        }

        ratesError.value = 'Shipping rates could not be loaded. Please try again.';
    } finally {
        if (current === ratesRequestId) {
            isLoadingRates.value = false;
        }
    }
};

const retryRates = () => {
    if (address.value?.area_id) {
        fetchRates(address.value.id);
    }
};

const openPicker = () => {
    isPickerOpen.value = true;
};

const openCourierPicker = () => {
    if (canChangeCourier.value) {
        isCourierOpen.value = true;
    }
};

const onPickAddress = (item: Address) => {
    pickedAddress.value = item;
};

watch(
    () => `${address.value?.id ?? ''}:${address.value?.area_id ?? ''}`,
    () => {
        const current = address.value;

        form.address_id = current?.id ?? null;
        form.clearErrors('address_id');

        if (current?.area_id) {
            fetchRates(current.id);

            return;
        }

        ratesController?.abort();
        ratesRequestId++;
        resetShipping();
        isLoadingRates.value = false;
    },
);

const submitOrder = () => {
    if (!canSubmit.value) {
        return;
    }

    form.post('/checkout', {
        preserveScroll: true,
    });
};

onMounted(() => {
    if (address.value?.area_id) {
        fetchRates(address.value.id);
    }
});

onBeforeUnmount(() => {
    ratesController?.abort();
});
</script>

<template>
    <CheckoutLayout>
        <div class="mx-auto max-w-[1840px] px-6 pb-32 pt-10 sm:px-10 md:pt-14 lg:px-[4.5vw] lg:pb-20">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Checkout
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                            Complete Order
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Confirm your delivery address, pick a shipping method, and place your NEVERENDING order.
                        </p>
                    </div>

                    <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                        {{ summary.total_items }} Item(s)
                    </p>
                </div>
            </section>

            <section class="grid gap-12 lg:grid-cols-[1fr_430px] lg:gap-16">
                <form @submit.prevent="submitOrder">
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-xl font-black uppercase tracking-[-0.03em]">
                                Delivery Address
                            </h2>

                            <button
                                v-if="address"
                                type="button"
                                class="text-xs font-black uppercase tracking-[0.18em] underline underline-offset-4 transition hover:opacity-60"
                                @click="openPicker"
                            >
                                Change
                            </button>
                        </div>

                        <div
                            v-if="address"
                            class="mt-4 border px-5 py-5"
                            :class="addressUsable ? 'border-neutral-200' : 'border-red-300 bg-red-50'"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="font-black uppercase tracking-[0.1em] text-neutral-950">
                                    {{ address.recipient_name }}
                                </p>

                                <span
                                    v-if="address.is_default"
                                    class="bg-neutral-950 px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-white"
                                >
                                    Default
                                </span>
                            </div>

                            <p class="mt-1 text-sm text-neutral-500">
                                {{ address.phone }}
                            </p>

                            <p class="mt-3 text-sm leading-6 text-neutral-700">
                                {{ address.full_address }}
                            </p>

                            <p class="text-sm leading-6 text-neutral-500">
                                {{ address.area_name ?? `${address.district}, ${address.city}, ${address.province}` }}
                                <span v-if="address.postal_code">, {{ address.postal_code }}</span>
                            </p>

                            <p
                                v-if="!addressUsable"
                                class="mt-3 text-xs font-bold leading-6 text-red-700"
                            >
                                This address has no delivery area yet. Choose Change, edit the address, and select its area.
                            </p>
                        </div>

                        <button
                            v-else
                            type="button"
                            class="mt-4 w-full border border-dashed border-neutral-300 px-5 py-6 text-left transition hover:border-neutral-950"
                            @click="openPicker"
                        >
                            <span class="block text-xs font-black uppercase tracking-[0.22em] text-neutral-950">
                                Add Address
                            </span>

                            <span class="mt-1 block text-sm text-neutral-500">
                                Add a delivery address to continue your order.
                            </span>
                        </button>

                        <p
                            v-if="form.errors.address_id"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ form.errors.address_id }}
                        </p>
                    </div>

                    <div class="mt-12">
                        <h2 class="text-xl font-black uppercase tracking-[-0.03em]">
                            Shipment Method
                        </h2>

                        <p
                            v-if="!addressUsable"
                            class="mt-4 border border-dashed border-neutral-300 px-5 py-5 text-sm text-neutral-500"
                        >
                            Choose a delivery address with an area to see shipping methods.
                        </p>

                        <div
                            v-else-if="isLoadingRates"
                            class="mt-4 flex min-h-[5rem] animate-pulse items-center border border-neutral-200 px-5 text-xs font-bold uppercase tracking-[0.16em] text-neutral-500"
                        >
                            Calculating shipping...
                        </div>

                        <div
                            v-else-if="ratesError"
                            class="mt-4 border border-red-200 bg-red-50 p-4"
                        >
                            <p class="text-sm text-red-700">
                                {{ ratesError }}
                            </p>

                            <button
                                type="button"
                                class="mt-3 text-xs font-black uppercase tracking-[0.18em] text-red-700 underline underline-offset-4 transition hover:opacity-60"
                                @click="retryRates"
                            >
                                Try again
                            </button>
                        </div>

                        <div v-else-if="selectedOption">
                            <component
                                :is="canChangeCourier ? 'button' : 'div'"
                                :type="canChangeCourier ? 'button' : undefined"
                                class="mt-4 flex min-h-[5rem] w-full items-center gap-4 border border-neutral-200 px-5 py-4 text-left transition"
                                :class="canChangeCourier ? 'hover:border-neutral-950' : ''"
                                @click="openCourierPicker"
                            >
                                <CourierLogo
                                    :code="selectedOption.courier_code"
                                    :name="selectedOption.courier_name"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-black uppercase tracking-[-0.02em]">
                                            {{ selectedOption.courier_name }}
                                        </p>

                                        <span
                                            v-for="label in optionLabels[selectedOption.key] ?? []"
                                            :key="label"
                                            class="bg-neutral-950 px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-white"
                                        >
                                            {{ label }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-sm text-neutral-500">
                                        {{ selectedOption.service_name }}<span v-if="selectedOption.duration"> ({{ selectedOption.duration }})</span>
                                    </p>
                                </div>

                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-black">
                                        {{ formatPrice(optionTotal(selectedOption)) }}
                                    </p>

                                    <p
                                        v-if="selectedOption.insurance_fee > 0"
                                        class="mt-1 text-[11px] text-neutral-500"
                                    >
                                        incl. {{ formatPrice(selectedOption.insurance_fee) }} insurance
                                    </p>
                                </div>

                                <svg
                                    v-if="canChangeCourier"
                                    class="h-4 w-4 shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M9 6l6 6-6 6" />
                                </svg>
                            </component>
                        </div>

                        <p
                            v-if="shippingError"
                            class="mt-3 text-sm text-red-600"
                        >
                            {{ shippingError }}
                        </p>
                    </div>

                    <div class="mt-12">
                        <label
                            for="checkout-notes"
                            class="text-xl font-black uppercase tracking-[-0.03em]"
                        >
                            Message for Delivery
                            <span class="text-xs font-bold normal-case tracking-normal text-neutral-500">(Optional)</span>
                        </label>

                        <textarea
                            id="checkout-notes"
                            v-model="form.notes"
                            rows="2"
                            maxlength="500"
                            class="mt-4 w-full resize-none border-0 border-b border-neutral-300 bg-transparent px-0 py-3 text-sm outline-none ring-0 transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-0"
                            placeholder="Optional message for the courier..."
                        ></textarea>

                        <p
                            v-if="form.errors.notes"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ form.errors.notes }}
                        </p>

                        <label class="mt-8 flex cursor-pointer items-start gap-3">
                            <input
                                v-model="form.agree_terms"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded-none border-neutral-300 text-neutral-950 focus:ring-neutral-950"
                            />

                            <span class="text-sm font-bold text-neutral-700">
                                I agree to the Terms &amp; Conditions
                            </span>
                        </label>

                        <p
                            v-if="form.errors.agree_terms"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ form.errors.agree_terms }}
                        </p>

                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            class="mt-8 hidden w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300 lg:block"
                        >
                            {{ form.processing ? 'Processing...' : 'Place Order' }}
                        </button>

                        <p class="mt-4 text-xs text-neutral-500 lg:text-center">
                            Secure Payment | Your data is sent over an encrypted connection.
                        </p>
                    </div>

                    <div class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-4 border-t border-neutral-200 bg-white px-6 py-4 lg:hidden">
                        <div class="shrink-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-neutral-500">
                                Total
                            </p>

                            <p class="text-base font-black">
                                {{ formatPrice(grandTotal) }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            class="flex-1 bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                        >
                            {{ form.processing ? 'Processing...' : 'Place Order' }}
                        </button>
                    </div>
                </form>

                <aside class="h-fit border border-neutral-200 bg-neutral-50 p-6 lg:sticky lg:top-28">
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        Order Summary
                    </h2>

                    <div class="mt-6 space-y-5">
                        <article
                            v-for="item in items"
                            :key="item.id"
                            class="flex gap-4 border-b border-neutral-200 pb-5 last:border-b-0 last:pb-0"
                        >
                            <Link
                                :href="`/products/${item.product.slug}`"
                                class="block h-24 w-20 shrink-0 overflow-hidden bg-neutral-100"
                            >
                                <img
                                    :src="storageUrl(item.product.image_path)"
                                    :alt="item.product.name"
                                    class="h-full w-full object-cover transition duration-500 hover:scale-105"
                                />
                            </Link>

                            <div class="flex-1">
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-500">
                                    Size {{ sizeLabel(item) }}
                                </p>

                                <Link
                                    :href="`/products/${item.product.slug}`"
                                    class="mt-1 block text-sm font-black uppercase tracking-tight hover:underline"
                                >
                                    {{ item.product.name }}
                                </Link>

                                <p class="mt-2 text-sm text-neutral-500">
                                    {{ item.quantity }} x {{ formatPrice(item.unit_price) }}
                                </p>
                            </div>

                            <p class="text-sm font-black">
                                {{ formatPrice(item.subtotal) }}
                            </p>
                        </article>
                    </div>

                    <div class="mt-6 space-y-4 border-y border-neutral-200 py-6 text-sm">
                        <div class="flex justify-between">
                            <span class="text-neutral-500">Subtotal ({{ summary.total_items }} items)</span>
                            <span class="font-black">{{ formatPrice(summary.subtotal) }}</span>
                        </div>

                        <div>
                            <div class="flex justify-between">
                                <span class="text-neutral-500">Shipping ({{ weightLabel }} kg)</span>
                                <span
                                    v-if="shippingCost !== null"
                                    class="font-black"
                                >
                                    {{ formatPrice(shippingCost) }}
                                </span>
                                <span
                                    v-else
                                    class="text-neutral-400"
                                >
                                    -
                                </span>
                            </div>

                            <p class="mt-2 text-xs leading-5 text-neutral-500">
                                Shipping might be charged by volumetric weight, based on parcel size rather than actual weight.
                            </p>
                        </div>

                        <div
                            v-if="selectedOption"
                            class="flex justify-between"
                        >
                            <span class="text-neutral-500">Shipment Insurance Fee</span>
                            <span class="font-black">{{ formatPrice(insuranceFee) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between text-xl">
                        <span class="font-black uppercase tracking-[-0.03em]">
                            Total
                        </span>

                        <span class="font-black">
                            {{ formatPrice(grandTotal) }}
                        </span>
                    </div>

                    <div class="mt-6 bg-white p-5 text-sm leading-7 text-neutral-600">
                        <p class="font-black uppercase tracking-[0.18em] text-neutral-950">
                            Manual Transfer
                        </p>

                        <p class="mt-2">
                            Payment proof can be uploaded after your order has been created.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-3 border-t border-neutral-200 pt-6 text-xs font-bold uppercase tracking-[0.18em] text-neutral-500">
                        <p>Manual transfer payment</p>
                        <p>Daily wear for endless rotation</p>
                    </div>
                </aside>
            </section>
        </div>

        <AddressPicker
            :open="isPickerOpen"
            :addresses="addresses"
            :selected-id="address?.id ?? null"
            @close="isPickerOpen = false"
            @select="onPickAddress"
        />

        <CourierPicker
            :open="isCourierOpen"
            :options="options"
            :selected-key="selectedKey"
            :labels="optionLabels"
            @close="isCourierOpen = false"
            @select="selectOption"
        />
    </CheckoutLayout>
</template>