<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { computed, onMounted, ref } from 'vue';

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
    postal_code: string | null;
    full_address: string;
    is_default: boolean;
};

type CheckoutForm = {
    address_id: number | null;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    postal_code: string;
    full_address: string;
    notes: string;
    save_address: boolean;
};

const props = defineProps<{
    items: CheckoutItem[];
    summary: {
        subtotal: number;
        shipping_cost: number;
        total: number;
        total_items: number;
    };
    addresses: Address[];
    defaultAddress: Address | null;
}>();

const selectedAddressId = ref<number | null>(props.defaultAddress?.id ?? null);
const useManualAddress = ref(props.addresses.length === 0);

const form = useForm<CheckoutForm>({
    address_id: props.defaultAddress?.id ?? null,
    recipient_name: props.defaultAddress?.recipient_name ?? '',
    phone: props.defaultAddress?.phone ?? '',
    province: props.defaultAddress?.province ?? '',
    city: props.defaultAddress?.city ?? '',
    district: props.defaultAddress?.district ?? '',
    postal_code: props.defaultAddress?.postal_code ?? '',
    full_address: props.defaultAddress?.full_address ?? '',
    notes: '',
    save_address: props.addresses.length === 0,
});

const selectedAddress = computed(() => {
    return props.addresses.find((address) => address.id === selectedAddressId.value) ?? null;
});

const shouldShowAddressForm = computed(() => {
    return useManualAddress.value || props.addresses.length === 0;
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

const fillAddress = (address: Address) => {
    selectedAddressId.value = address.id;
    useManualAddress.value = false;

    form.address_id = address.id;
    form.recipient_name = address.recipient_name;
    form.phone = address.phone;
    form.province = address.province;
    form.city = address.city;
    form.district = address.district;
    form.postal_code = address.postal_code ?? '';
    form.full_address = address.full_address;
    form.save_address = false;

    form.clearErrors();
};

const useNewAddress = () => {
    selectedAddressId.value = null;
    useManualAddress.value = true;

    form.address_id = null;
    form.recipient_name = '';
    form.phone = '';
    form.province = '';
    form.city = '';
    form.district = '';
    form.postal_code = '';
    form.full_address = '';
    form.save_address = true;

    form.clearErrors();
};

const submitOrder = () => {
    form.post('/checkout', {
        preserveScroll: true,
    });
};

onMounted(() => {
    if (props.defaultAddress) {
        fillAddress(props.defaultAddress);
    } else {
        useNewAddress();
    }
});
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
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
                            Choose your saved address or add a new delivery address for your NEVERENDING order.
                        </p>
                    </div>

                    <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                        {{ summary.total_items }} Item(s)
                    </p>
                </div>
            </section>

            <section class="grid gap-10 lg:grid-cols-[1fr_430px]">
                <form
                    class="border border-neutral-200 p-6 md:p-8"
                    @submit.prevent="submitOrder"
                >
                    <div class="mb-8">
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                            Shipping Information
                        </p>

                        <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                            Delivery Details
                        </h2>
                    </div>

                    <div
                        v-if="addresses.length"
                        class="mb-8"
                    >
                        <div class="mb-4 flex items-center justify-between gap-4">
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                                Saved Addresses
                            </p>

                            <Link
                                href="/account/addresses"
                                class="text-xs font-black uppercase tracking-[0.18em] underline underline-offset-4"
                            >
                                Manage
                            </Link>
                        </div>

                        <div class="grid gap-4">
                            <button
                                v-for="address in addresses"
                                :key="address.id"
                                type="button"
                                class="border p-5 text-left transition"
                                :class="selectedAddressId === address.id && !useManualAddress
                                    ? 'border-neutral-950 bg-neutral-950 text-white'
                                    : 'border-neutral-200 bg-white text-neutral-950 hover:border-neutral-950'"
                                @click="fillAddress(address)"
                            >
                                <div class="flex flex-wrap items-center gap-3">
                                    <p class="font-black uppercase tracking-[-0.02em]">
                                        {{ address.recipient_name }}
                                    </p>

                                    <span
                                        v-if="address.is_default"
                                        class="px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em]"
                                        :class="selectedAddressId === address.id && !useManualAddress
                                            ? 'bg-white text-neutral-950'
                                            : 'bg-neutral-950 text-white'"
                                    >
                                        Default
                                    </span>
                                </div>

                                <p class="mt-2 text-sm opacity-80">
                                    {{ address.phone }}
                                </p>

                                <p class="mt-3 text-sm leading-6 opacity-80">
                                    {{ address.full_address }}
                                </p>

                                <p class="text-sm leading-6 opacity-80">
                                    {{ address.district }}, {{ address.city }}, {{ address.province }}
                                    <span v-if="address.postal_code">, {{ address.postal_code }}</span>
                                </p>
                            </button>

                            <button
                                type="button"
                                class="border border-dashed border-neutral-300 bg-neutral-50 p-5 text-left transition hover:border-neutral-950"
                                :class="useManualAddress ? 'border-neutral-950 bg-white' : ''"
                                @click="useNewAddress"
                            >
                                <p class="text-sm font-black uppercase tracking-[0.18em]">
                                    Use New Address
                                </p>

                                <p class="mt-2 text-sm text-neutral-500">
                                    Fill a new delivery address manually.
                                </p>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="selectedAddress && !shouldShowAddressForm"
                        class="mb-8 border border-neutral-200 bg-neutral-50 p-5"
                    >
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                            Selected Address
                        </p>

                        <div class="mt-4 text-sm leading-7 text-neutral-600">
                            <p class="font-black uppercase tracking-[0.1em] text-neutral-950">
                                {{ selectedAddress.recipient_name }}
                            </p>

                            <p class="mt-1">
                                {{ selectedAddress.phone }}
                            </p>

                            <p class="mt-4">
                                {{ selectedAddress.full_address }}
                            </p>

                            <p>
                                {{ selectedAddress.district }},
                                {{ selectedAddress.city }},
                                {{ selectedAddress.province }}
                                <span v-if="selectedAddress.postal_code">
                                    , {{ selectedAddress.postal_code }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="shouldShowAddressForm"
                        class="grid gap-5 md:grid-cols-2"
                    >
                        <div class="md:col-span-2">
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                                {{ addresses.length ? 'New Address Form' : 'Add Your First Address' }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Recipient Name
                            </label>

                            <input
                                v-model="form.recipient_name"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Full name"
                            />

                            <p
                                v-if="form.errors.recipient_name"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.recipient_name }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Phone
                            </label>

                            <input
                                v-model="form.phone"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="08xxxxxxxxxx"
                            />

                            <p
                                v-if="form.errors.phone"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Province
                            </label>

                            <input
                                v-model="form.province"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Banten"
                            />

                            <p
                                v-if="form.errors.province"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.province }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                City
                            </label>

                            <input
                                v-model="form.city"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Tangerang"
                            />

                            <p
                                v-if="form.errors.city"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.city }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                District
                            </label>

                            <input
                                v-model="form.district"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Karawaci"
                            />

                            <p
                                v-if="form.errors.district"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.district }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Postal Code
                            </label>

                            <input
                                v-model="form.postal_code"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="151xx"
                            />

                            <p
                                v-if="form.errors.postal_code"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.postal_code }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Full Address
                            </label>

                            <textarea
                                v-model="form.full_address"
                                rows="4"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Street, house number, building detail..."
                            ></textarea>

                            <p
                                v-if="form.errors.full_address"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.full_address }}
                            </p>
                        </div>

                        <label
                            class="flex items-center gap-3 border border-neutral-200 bg-neutral-50 p-4 md:col-span-2"
                        >
                            <input
                                v-model="form.save_address"
                                type="checkbox"
                                class="h-4 w-4 rounded-none border-neutral-300 text-neutral-950"
                            />

                            <span class="text-sm font-bold text-neutral-700">
                                Save this address
                            </span>
                        </label>
                    </div>

                    <div class="mt-8">
                        <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                            Notes
                        </label>

                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                            placeholder="Optional notes for admin..."
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-8 w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                    >
                        {{ form.processing ? 'Processing...' : 'Place Order' }}
                    </button>
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
                            <span class="text-neutral-500">Items</span>
                            <span class="font-black">{{ summary.total_items }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-500">Subtotal</span>
                            <span class="font-black">{{ formatPrice(summary.subtotal) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-500">Shipping</span>
                            <span class="font-black">{{ formatPrice(summary.shipping_cost) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between text-xl">
                        <span class="font-black uppercase tracking-[-0.03em]">
                            Total
                        </span>

                        <span class="font-black">
                            {{ formatPrice(summary.total) }}
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
                        <p>Shipping by JNE / J&T / SiCepat / Anteraja</p>
                        <p>Daily wear for endless rotation</p>
                    </div>
                </aside>
            </section>
        </main>
    </StoreLayout>
</template>