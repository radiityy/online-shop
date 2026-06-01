<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';

type CartItem = {
    id: number;
    quantity: number;
    unit_price: number;
    subtotal: number;
    product: {
        id: number;
        name: string;
        slug: string;
        price: string;
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
    items: CartItem[];
    summary: {
        subtotal: number;
        total_items: number;
    };
}>();

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

const variantLabel = (item: CartItem) => {
    const parts = [item.variant.size, item.variant.color].filter(Boolean);

    return parts.length ? parts.join(' / ') : item.variant.sku ?? 'Default';
};

const updateQuantity = (item: CartItem, quantity: number) => {
    if (quantity < 1 || quantity > item.variant.stock) {
        return;
    }

    router.patch(
        `/cart/items/${item.id}`,
        {
            quantity,
        },
        {
            preserveScroll: true,
        },
    );
};

const removeItem = (item: CartItem) => {
    router.delete(`/cart/items/${item.id}`, {
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
                    <Link href="/cart" class="text-neutral-950 underline underline-offset-4">Cart</Link>
                </nav>

                <div class="flex items-center gap-4 text-sm font-medium">
                    <Link href="/products" class="rounded-full bg-neutral-950 px-5 py-2 text-white hover:bg-neutral-800">
                        Continue Shopping
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-12">
            <section class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                    Shopping Cart
                </p>

                <h1 class="mt-3 text-4xl font-black tracking-tight md:text-5xl">
                    Your Cart
                </h1>

                <p class="mt-3 text-neutral-600">
                    Review your selected items before checkout.
                </p>
            </section>

            <section
                v-if="items.length"
                class="grid gap-10 lg:grid-cols-[1fr_380px]"
            >
                <div class="space-y-5">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="grid gap-5 rounded-3xl border border-neutral-200 p-5 md:grid-cols-[140px_1fr_auto]"
                    >
                        <Link :href="`/products/${item.product.slug}`">
                            <img
                                :src="storageUrl(item.product.image_path)"
                                :alt="item.product.name"
                                class="aspect-square w-full rounded-2xl bg-neutral-100 object-cover"
                            />
                        </Link>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">
                                {{ variantLabel(item) }}
                            </p>

                            <Link
                                :href="`/products/${item.product.slug}`"
                                class="mt-1 block text-xl font-bold hover:text-neutral-600"
                            >
                                {{ item.product.name }}
                            </Link>

                            <p class="mt-2 text-sm text-neutral-500">
                                SKU: {{ item.variant.sku ?? '-' }}
                            </p>

                            <p class="mt-3 font-semibold">
                                {{ formatPrice(item.unit_price) }}
                            </p>

                            <p class="mt-1 text-sm text-neutral-500">
                                Stock: {{ item.variant.stock }}
                            </p>
                        </div>

                        <div class="flex flex-col items-start justify-between gap-5 md:items-end">
                            <div class="inline-flex items-center rounded-full border border-neutral-300">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-lg"
                                    @click="updateQuantity(item, item.quantity - 1)"
                                >
                                    -
                                </button>

                                <span class="min-w-10 text-center font-bold">
                                    {{ item.quantity }}
                                </span>

                                <button
                                    type="button"
                                    class="px-4 py-2 text-lg"
                                    @click="updateQuantity(item, item.quantity + 1)"
                                >
                                    +
                                </button>
                            </div>

                            <div class="text-left md:text-right">
                                <p class="font-bold">
                                    {{ formatPrice(item.subtotal) }}
                                </p>

                                <button
                                    type="button"
                                    class="mt-3 text-sm font-semibold text-red-600 hover:underline"
                                    @click="removeItem(item)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="h-fit rounded-3xl border border-neutral-200 bg-neutral-50 p-6">
                    <h2 class="text-xl font-black">
                        Order Summary
                    </h2>

                    <div class="mt-6 space-y-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Total Items</span>
                            <span class="font-bold">{{ summary.total_items }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-600">Subtotal</span>
                            <span class="font-bold">{{ formatPrice(summary.subtotal) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-600">Shipping</span>
                            <span class="font-bold">Calculated at checkout</span>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-neutral-200 pt-6">
                        <div class="flex justify-between text-lg">
                            <span class="font-bold">Total</span>
                            <span class="font-black">{{ formatPrice(summary.subtotal) }}</span>
                        </div>
                    </div>

                    <Link
                        href="/checkout"
                        class="mt-6 flex w-full justify-center rounded-full bg-neutral-950 px-6 py-4 text-sm font-bold uppercase tracking-[0.2em] text-white hover:bg-neutral-800"
                    >
                        Checkout
                    </Link>

                    <p class="mt-4 text-center text-xs text-neutral-500">
                        Checkout will be connected in the next step.
                    </p>
                </aside>
            </section>

            <section
                v-else
                class="rounded-3xl border border-dashed border-neutral-300 p-12 text-center"
            >
                <h2 class="text-2xl font-black">
                    Your cart is empty
                </h2>

                <p class="mt-3 text-neutral-500">
                    Start adding NEVERENDING products to your cart.
                </p>

                <Link
                    href="/products"
                    class="mt-8 inline-flex rounded-full bg-neutral-950 px-7 py-3 text-sm font-bold text-white hover:bg-neutral-800"
                >
                    Shop Now
                </Link>
            </section>
        </main>
    </div>
</template>