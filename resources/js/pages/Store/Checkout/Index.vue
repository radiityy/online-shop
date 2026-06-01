<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

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

defineProps<{
    items: CheckoutItem[];
    summary: {
        subtotal: number;
        shipping_cost: number;
        total: number;
        total_items: number;
    };
}>();

const form = useForm({
    recipient_name: '',
    phone: '',
    province: '',
    city: '',
    district: '',
    postal_code: '',
    full_address: '',
    notes: '',
    save_address: true,
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

const variantLabel = (item: CheckoutItem) => {
    const parts = [item.variant.size, item.variant.color].filter(Boolean);

    return parts.length ? parts.join(' / ') : item.variant.sku ?? 'Default';
};

const submitOrder = () => {
    form.post('/checkout', {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-950">
        <header class="sticky top-0 z-50 border-b border-neutral-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
                <Link href="/" class="text-xl font-bold tracking-tight">
                    NEVERENDING
                </Link>

                <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
                    <Link href="/" class="hover:text-neutral-500">Home</Link>
                    <Link href="/products" class="hover:text-neutral-500">Shop</Link>
                    <Link href="/cart" class="hover:text-neutral-500">Cart</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-12">
            <section class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                    Checkout
                </p>

                <h1 class="mt-3 text-4xl font-black tracking-tight md:text-5xl">
                    Complete Your Order
                </h1>

                <p class="mt-3 text-neutral-600">
                    Fill your shipping address and confirm your NEVERENDING order.
                </p>
            </section>

            <section class="grid gap-10 lg:grid-cols-[1fr_420px]">
                <form
                    class="rounded-3xl border border-neutral-200 p-6"
                    @submit.prevent="submitOrder"
                >
                    <h2 class="text-xl font-black">
                        Shipping Information
                    </h2>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-bold">Recipient Name</label>
                            <input
                                v-model="form.recipient_name"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            />
                            <p v-if="form.errors.recipient_name" class="mt-2 text-sm text-red-600">
                                {{ form.errors.recipient_name }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-bold">Phone</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            />
                            <p v-if="form.errors.phone" class="mt-2 text-sm text-red-600">
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-bold">Province</label>
                            <input
                                v-model="form.province"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            />
                            <p v-if="form.errors.province" class="mt-2 text-sm text-red-600">
                                {{ form.errors.province }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-bold">City</label>
                            <input
                                v-model="form.city"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            />
                            <p v-if="form.errors.city" class="mt-2 text-sm text-red-600">
                                {{ form.errors.city }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-bold">District</label>
                            <input
                                v-model="form.district"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            />
                            <p v-if="form.errors.district" class="mt-2 text-sm text-red-600">
                                {{ form.errors.district }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-bold">Postal Code</label>
                            <input
                                v-model="form.postal_code"
                                type="text"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            />
                            <p v-if="form.errors.postal_code" class="mt-2 text-sm text-red-600">
                                {{ form.errors.postal_code }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-bold">Full Address</label>
                            <textarea
                                v-model="form.full_address"
                                rows="4"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            ></textarea>
                            <p v-if="form.errors.full_address" class="mt-2 text-sm text-red-600">
                                {{ form.errors.full_address }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-bold">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                placeholder="Optional notes for admin..."
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-neutral-950"
                            ></textarea>
                        </div>

                        <label class="flex items-center gap-3 md:col-span-2">
                            <input
                                v-model="form.save_address"
                                type="checkbox"
                                class="rounded border-neutral-300"
                            />
                            <span class="text-sm font-medium">
                                Save this address as default
                            </span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-8 w-full rounded-full bg-neutral-950 px-8 py-4 text-sm font-bold uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                    >
                        {{ form.processing ? 'Processing...' : 'Place Order' }}
                    </button>
                </form>

                <aside class="h-fit rounded-3xl border border-neutral-200 bg-neutral-50 p-6">
                    <h2 class="text-xl font-black">
                        Order Summary
                    </h2>

                    <div class="mt-6 space-y-5">
                        <article
                            v-for="item in items"
                            :key="item.id"
                            class="flex gap-4"
                        >
                            <img
                                :src="storageUrl(item.product.image_path)"
                                :alt="item.product.name"
                                class="h-20 w-20 rounded-2xl bg-neutral-100 object-cover"
                            />

                            <div class="flex-1">
                                <h3 class="font-bold">
                                    {{ item.product.name }}
                                </h3>

                                <p class="mt-1 text-xs uppercase tracking-widest text-neutral-500">
                                    {{ variantLabel(item) }}
                                </p>

                                <p class="mt-1 text-sm text-neutral-600">
                                    {{ item.quantity }} x {{ formatPrice(item.unit_price) }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <div class="mt-6 border-t border-neutral-200 pt-6 text-sm">
                        <div class="flex justify-between py-2">
                            <span class="text-neutral-600">Items</span>
                            <span class="font-bold">{{ summary.total_items }}</span>
                        </div>

                        <div class="flex justify-between py-2">
                            <span class="text-neutral-600">Subtotal</span>
                            <span class="font-bold">{{ formatPrice(summary.subtotal) }}</span>
                        </div>

                        <div class="flex justify-between py-2">
                            <span class="text-neutral-600">Shipping</span>
                            <span class="font-bold">{{ formatPrice(summary.shipping_cost) }}</span>
                        </div>

                        <div class="mt-4 flex justify-between border-t border-neutral-200 pt-5 text-lg">
                            <span class="font-black">Total</span>
                            <span class="font-black">{{ formatPrice(summary.total) }}</span>
                        </div>
                    </div>

                    <p class="mt-5 rounded-2xl bg-white p-4 text-xs leading-6 text-neutral-500">
                        Payment method: manual transfer. Payment upload and admin confirmation will be added in the next step.
                    </p>
                </aside>
            </section>
        </main>
    </div>
</template>