<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';

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
        sku: string | null;
        stock: number;
        additional_price: string;
    };
};

type RecommendedProduct = {
    id: number;
    name: string;
    slug: string;
    price: string;
    sold_count: number;
    category?: {
        id: number;
        name: string;
        slug: string;
    } | null;
    primary_image?: {
        id: number;
        image_path: string;
    } | null;
};

defineProps<{
    items: CartItem[];
    summary: {
        subtotal: number;
        total_items: number;
    };
    recommendedProducts: RecommendedProduct[];
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

const sizeLabel = (item: CartItem) => {
    return item.variant.size ?? item.variant.sku ?? 'Default';
};

const increaseQuantity = (item: CartItem) => {
    if (item.quantity >= item.variant.stock) {
        return;
    }

    router.patch(
        `/cart/items/${item.id}`,
        {
            quantity: item.quantity + 1,
        },
        {
            preserveScroll: true,
        },
    );
};

const decreaseQuantity = (item: CartItem) => {
    if (item.quantity <= 1) {
        return;
    }

    router.patch(
        `/cart/items/${item.id}`,
        {
            quantity: item.quantity - 1,
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
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Shopping Bag
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                            Your Bag
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Review your NEVERENDING pieces before checkout.
                        </p>
                    </div>

                    <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                        {{ summary.total_items }} Item(s)
                    </p>
                </div>
            </section>

            <section
                v-if="items.length"
                class="grid gap-10 lg:grid-cols-[1fr_420px]"
            >
                <div class="space-y-5">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="grid gap-5 border border-neutral-200 p-5 sm:grid-cols-[150px_1fr_auto]"
                    >
                        <Link
                            :href="`/products/${item.product.slug}`"
                            class="block overflow-hidden bg-neutral-100"
                        >
                            <img
                                :src="storageUrl(item.product.image_path)"
                                :alt="item.product.name"
                                class="aspect-[4/5] w-full object-cover transition duration-500 hover:scale-105"
                            />
                        </Link>

                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-neutral-500">
                                Size {{ sizeLabel(item) }}
                            </p>

                            <Link
                                :href="`/products/${item.product.slug}`"
                                class="mt-2 block text-xl font-black uppercase tracking-[-0.03em] hover:underline"
                            >
                                {{ item.product.name }}
                            </Link>

                            <p class="mt-2 text-sm font-medium text-neutral-600">
                                {{ formatPrice(item.unit_price) }}
                            </p>

                            <p class="mt-2 text-xs font-bold uppercase tracking-[0.18em] text-neutral-400">
                                Stock: {{ item.variant.stock }}
                            </p>

                            <button
                                type="button"
                                class="mt-5 text-xs font-black uppercase tracking-[0.2em] text-neutral-500 underline underline-offset-4 transition hover:text-neutral-950"
                                @click="removeItem(item)"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="flex flex-col justify-between gap-5 sm:items-end">
                            <p class="text-lg font-black">
                                {{ formatPrice(item.subtotal) }}
                            </p>

                            <div class="inline-flex w-fit items-center border border-neutral-300">
                                <button
                                    type="button"
                                    class="px-4 py-3 text-lg disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="item.quantity <= 1"
                                    @click="decreaseQuantity(item)"
                                >
                                    -
                                </button>

                                <span class="min-w-12 text-center font-black">
                                    {{ item.quantity }}
                                </span>

                                <button
                                    type="button"
                                    class="px-4 py-3 text-lg disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="item.quantity >= item.variant.stock"
                                    @click="increaseQuantity(item)"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="h-fit border border-neutral-200 bg-neutral-50 p-6 lg:sticky lg:top-28">
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        Summary
                    </h2>

                    <div class="mt-6 space-y-4 border-b border-neutral-200 pb-6 text-sm">
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
                            <span class="font-black">Calculated at checkout</span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between text-xl">
                        <span class="font-black uppercase tracking-[-0.03em]">Total</span>
                        <span class="font-black">{{ formatPrice(summary.subtotal) }}</span>
                    </div>

                    <Link
                        href="/checkout"
                        class="mt-8 flex w-full justify-center bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                    >
                        Checkout
                    </Link>

                    <Link
                        href="/products"
                        class="mt-3 flex w-full justify-center border border-neutral-950 bg-white px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                    >
                        Continue Shopping
                    </Link>

                    <div class="mt-6 grid gap-3 border-t border-neutral-200 pt-6 text-xs font-bold uppercase tracking-[0.18em] text-neutral-500">
                        <p>Manual transfer payment</p>
                        <p>Secure order processing</p>
                        <p>Daily wear for endless rotation</p>
                    </div>
                </aside>
            </section>

            <section v-else>
                <div class="border border-dashed border-neutral-300 p-14 text-center">
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                        Empty Bag
                    </p>

                    <h2 class="mt-4 text-4xl font-black uppercase tracking-[-0.04em]">
                        Your bag is empty
                    </h2>

                    <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-neutral-600">
                        Start your endless rotation by adding NEVERENDING pieces to your bag.
                    </p>

                    <Link
                        href="/products"
                        class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                    >
                        Shop Now
                    </Link>
                </div>

                <div
                    v-if="recommendedProducts.length"
                    class="mt-16"
                >
                    <div class="mb-10 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                Recommended Pieces
                            </p>

                            <h2 class="mt-3 text-4xl font-black uppercase tracking-[-0.04em] md:text-5xl">
                                Best Seller
                            </h2>

                            <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                                Explore pieces that belong in your daily rotation.
                            </p>
                        </div>

                        <Link
                            href="/products"
                            class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                        >
                            View All Products
                        </Link>
                    </div>

                    <div class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            v-for="product in recommendedProducts"
                            :key="product.id"
                            :href="`/products/${product.slug}`"
                            class="group"
                        >
                            <div class="overflow-hidden bg-neutral-100">
                                <img
                                    :src="storageUrl(product.primary_image?.image_path)"
                                    :alt="product.name"
                                    class="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-105"
                                />
                            </div>

                            <div class="mt-4">
                                <p
                                    v-if="product.category"
                                    class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-500"
                                >
                                    {{ product.category.name }}
                                </p>

                                <h3 class="mt-2 text-sm font-black uppercase tracking-tight md:text-base">
                                    {{ product.name }}
                                </h3>

                                <p class="mt-1 text-sm font-medium text-neutral-600">
                                    {{ formatPrice(product.price) }}
                                </p>

                                <p
                                    v-if="product.sold_count > 0"
                                    class="mt-2 text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-400"
                                >
                                    {{ product.sold_count }} sold
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>