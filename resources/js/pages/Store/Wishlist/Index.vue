<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';

type WishlistProduct = {
    id: number;
    name: string;
    slug: string;
    price: number | string;
    sale_price: number | string | null;
    final_price?: number | string | null;
    is_on_sale?: boolean;
    is_active: boolean;
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

type WishlistItem = {
    id: number;
    product: WishlistProduct;
    created_at: string;
};

defineProps<{
    wishlistItems: WishlistItem[];
}>();

const storageUrl = (path: string | null | undefined) => {
    if (!path) {
        return '/placeholder-product.jpg';
    }

    return `/storage/${path}`;
};

const formatCurrency = (value: number | string | null | undefined) => {
    const numberValue = Number(value ?? 0);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(numberValue);
};

const finalPrice = (product: WishlistProduct) => {
    return product.final_price ?? product.sale_price ?? product.price;
};

const isProductOnSale = (product: WishlistProduct) => {
    const originalPrice = Number(product.price);
    const currentPrice = Number(finalPrice(product));

    return Boolean(product.is_on_sale || product.sale_price) && currentPrice < originalPrice;
};

const removeFromWishlist = (slug: string) => {
    router.delete(`/wishlist/${slug}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1400px] px-6 pb-24 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                        Wishlist
                    </p>

                    <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.06em] md:text-7xl">
                        Saved Pieces
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                        Keep your favorite NEVERENDING pieces in one place before adding them to your rotation.
                    </p>
                </div>

                <Link
                    href="/products"
                    class="w-fit border border-neutral-950 px-7 py-4 text-xs font-black uppercase tracking-[0.22em] transition hover:bg-neutral-950 hover:text-white"
                >
                    Continue Shopping
                </Link>
            </section>

            <section
                v-if="wishlistItems.length"
                class="grid gap-x-3 gap-y-9 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-12 lg:grid-cols-4"
            >
                <article
                    v-for="item in wishlistItems"
                    :key="item.id"
                    class="group"
                >
                    <Link
                        :href="`/products/${item.product.slug}`"
                        class="block"
                    >
                        <div class="relative overflow-hidden bg-neutral-100">
                            <img
                                :src="storageUrl(item.product.primary_image?.image_path)"
                                :alt="item.product.name"
                                class="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-105"
                            />

                            <div
                                v-if="!item.product.is_active"
                                class="absolute left-3 top-3 bg-neutral-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-white"
                            >
                                Unavailable
                            </div>

                            <div
                                v-else-if="isProductOnSale(item.product)"
                                class="absolute left-3 top-3 bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-white"
                            >
                                Sale
                            </div>
                        </div>
                    </Link>

                    <div class="mt-4">
                        <p
                            v-if="item.product.category"
                            class="text-[10px] font-black uppercase tracking-[0.18em] text-neutral-500 sm:text-[11px]"
                        >
                            {{ item.product.category.name }}
                        </p>

                        <div class="mt-2 flex items-start justify-between gap-4">
                            <Link
                                :href="`/products/${item.product.slug}`"
                                class="line-clamp-2 min-h-[2.5rem] text-xs font-black uppercase leading-5 tracking-tight transition hover:text-neutral-500 sm:text-sm md:text-base"
                            >
                                {{ item.product.name }}
                            </Link>

                            <button
                                type="button"
                                class="shrink-0 text-[11px] font-black uppercase tracking-[0.16em] text-neutral-400 transition hover:text-red-600"
                                aria-label="Remove from wishlist"
                                @click="removeFromWishlist(item.product.slug)"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="mt-1">
                            <p
                                v-if="isProductOnSale(item.product)"
                                class="text-[11px] font-semibold text-neutral-400 line-through sm:text-xs"
                            >
                                {{ formatCurrency(item.product.price) }}
                            </p>

                            <p
                                :class="[
                                    isProductOnSale(item.product)
                                        ? 'text-xs font-semibold text-red-600 sm:text-sm'
                                        : 'text-xs font-semibold text-neutral-600 sm:text-sm',
                                ]"
                            >
                                {{ formatCurrency(finalPrice(item.product)) }}
                            </p>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2">
                            <Link
                                :href="`/products/${item.product.slug}`"
                                class="bg-neutral-950 px-5 py-3 text-center text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-neutral-800"
                            >
                                View Product
                            </Link>

                            <button
                                type="button"
                                class="border border-neutral-200 px-5 py-3 text-[11px] font-black uppercase tracking-[0.18em] text-neutral-600 transition hover:border-red-600 hover:text-red-600"
                                @click="removeFromWishlist(item.product.slug)"
                            >
                                Remove From Wishlist
                            </button>
                        </div>
                    </div>
                </article>
            </section>

            <section
                v-else
                class="border border-dashed border-neutral-300 px-6 py-24 text-center"
            >
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400">
                    Empty Wishlist
                </p>

                <h2 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">
                    No saved pieces yet.
                </h2>

                <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-neutral-500">
                    Save the products you like and come back to them anytime.
                </p>

                <Link
                    href="/products"
                    class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                >
                    Browse Products
                </Link>
            </section>
        </main>
    </StoreLayout>
</template>