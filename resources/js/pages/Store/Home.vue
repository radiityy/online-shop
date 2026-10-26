<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';

type Banner = {
    id: number;
    title: string;
    subtitle: string | null;
    image_path: string;
    link_url: string | null;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    price: string;
    sale_price?: string | number | null;
    final_price?: string | number;
    is_on_sale?: boolean;
    stock_total?: number | null;
    weight: number;
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
    banners: Banner[];
    products: Product[];
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

const discountPercent = (product: Product) => {
    const originalPrice = Number(product.price);
    const salePrice = Number(product.final_price ?? product.sale_price ?? product.price);

    if (!product.is_on_sale || originalPrice <= 0 || salePrice >= originalPrice) {
        return 0;
    }

    return Math.round(((originalPrice - salePrice) / originalPrice) * 100);
};
</script>

<template>
    <StoreLayout transparent-header>
        <main>
            <section class="relative">
                <div
                    v-if="banners.length"
                    class="relative h-[82svh] min-h-[560px] max-h-[720px] overflow-hidden bg-neutral-950 sm:h-auto sm:max-h-none sm:min-h-[780px] lg:min-h-[720px] xl:min-h-[740px]"
                >
                    <img
                        :src="storageUrl(banners[0].image_path)"
                        :alt="banners[0].title"
                        class="absolute inset-0 h-full w-full object-cover"
                    />

                    <div class="absolute inset-0 bg-black/20 sm:bg-black/35"></div>

                    <div class="absolute inset-x-0 top-0 h-36 bg-gradient-to-b from-black/60 to-transparent sm:hidden"></div>

                    <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black/80 via-black/40 to-transparent sm:h-1/2 sm:from-black/60 sm:via-black/20"></div>

                    <div class="absolute inset-x-0 bottom-0 z-10 px-6 pb-10 sm:px-10 sm:pb-14 lg:px-[4.5vw] lg:pb-16">
                        <div class="mx-auto max-w-[1840px]">
                            <div class="max-w-[1120px] text-white">
                                <p class="mb-3 translate-x-[5px] text-[10px] font-black uppercase tracking-[0.36em] text-white/85 sm:mb-1 sm:translate-x-[6px] sm:text-[11px] sm:tracking-[0.42em] md:translate-x-[8px] md:text-xs">
                                    Neverending Daily Wear
                                </p>

                                <h1 class="text-[52px] font-black uppercase leading-[0.92] tracking-[-0.045em] sm:text-[82px] md:text-[100px] lg:text-[116px] xl:text-[128px]">
                                    {{ banners[0].title }}
                                </h1>

                                <p
                                    v-if="banners[0].subtitle"
                                    class="mt-3 max-w-xl text-sm leading-6 text-white/80 sm:mt-4 sm:leading-7 md:text-base"
                                >
                                    {{ banners[0].subtitle }}
                                </p>

                                <Link
                                    :href="banners[0].link_url || '/products'"
                                    class="group relative mt-5 inline-flex items-center overflow-hidden rounded-full border border-white/70 bg-white/10 px-8 py-3.5 text-[11px] font-black uppercase tracking-[0.26em] text-white backdrop-blur-sm transition-colors duration-300 hover:border-white hover:text-neutral-950 motion-reduce:transition-none sm:mt-4 sm:px-9 sm:py-4"
                                >
                                    <span
                                        class="absolute inset-0 -translate-x-full bg-white transition-transform duration-500 ease-out group-hover:translate-x-0 motion-reduce:transition-none"
                                    ></span>

                                    <span class="relative z-10">Shop Now</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="relative flex min-h-[560px] items-center justify-start bg-neutral-950 px-6 text-white sm:min-h-[700px] sm:px-10 lg:min-h-[720px] lg:px-[7.5vw] xl:min-h-[760px]"
                >
                    <div class="mx-auto w-full max-w-[1840px]">
                        <p class="text-[11px] font-black uppercase tracking-[0.42em] text-white/85 md:text-xs">
                            Neverending Daily Wear
                        </p>

                        <h1 class="mt-4 text-[52px] font-black uppercase leading-[0.92] tracking-[-0.045em] sm:mt-3 sm:text-[82px] md:text-[100px] lg:text-[116px]">
                            New Arrival
                        </h1>

                        <p class="mt-3 max-w-xl text-white/80 sm:mt-4">
                            Clean pieces for your everyday movement. Built to stay in your rotation.
                        </p>

                        <Link
                            href="/products"
                            class="group relative mt-5 inline-flex items-center overflow-hidden rounded-full border border-white/70 bg-white/10 px-8 py-3.5 text-[11px] font-black uppercase tracking-[0.26em] text-white backdrop-blur-sm transition-colors duration-300 hover:border-white hover:text-neutral-950 motion-reduce:transition-none sm:mt-4 sm:px-9 sm:py-4"
                        >
                            <span
                                class="absolute inset-0 -translate-x-full bg-white transition-transform duration-500 ease-out group-hover:translate-x-0 motion-reduce:transition-none"
                            ></span>

                            <span class="relative z-10">Shop Now</span>
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-[1840px] px-6 py-12 sm:px-10 sm:pb-20 sm:pt-10 lg:px-[7.5vw]">
                <div class="mb-6 flex flex-col justify-between gap-4 sm:mb-8 sm:gap-6 md:flex-row md:items-end">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-neutral-500 sm:text-xs">
                            New Arrivals
                        </p>

                        <h2 class="mt-2 text-3xl font-black uppercase tracking-[-0.03em] sm:mt-1 sm:text-4xl md:text-5xl">
                            Fresh Rotation
                        </h2>

                        <p class="mt-2 hidden max-w-xl text-sm leading-7 text-neutral-600 sm:block md:text-base">
                            Clean pieces for your everyday movement. Built to stay in your rotation.
                        </p>
                    </div>

                    <Link
                        href="/products"
                        class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                    >
                        View All Products
                    </Link>
                </div>

                <div
                    v-if="products.length"
                    class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Link
                        v-for="product in products"
                        :key="product.id"
                        :href="`/products/${product.slug}`"
                        class="group"
                    >
                        <div class="relative overflow-hidden bg-neutral-100">
                            <img
                                :src="storageUrl(product.primary_image?.image_path)"
                                :alt="product.name"
                                class="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-105"
                                :class="Number(product.stock_total ?? 0) <= 0 ? 'opacity-60 grayscale' : ''"
                            />

                            <div
                                v-if="Number(product.stock_total ?? 0) <= 0"
                                class="absolute left-3 top-3 bg-neutral-950 px-3 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-white"
                            >
                                Sold Out
                            </div>

                            <div
                                v-else-if="product.is_on_sale"
                                class="absolute left-3 top-3 bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-white"
                            >
                                -{{ discountPercent(product) }}%
                            </div>
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

                            <div class="mt-1">
                                <p
                                    v-if="product.is_on_sale"
                                    class="text-xs font-medium text-neutral-400 line-through"
                                >
                                    {{ formatPrice(product.price) }}
                                </p>

                                <p class="text-sm font-medium text-neutral-600">
                                    {{ formatPrice(product.final_price ?? product.price) }}
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-3xl border border-dashed border-neutral-300 p-12 text-center text-neutral-500"
                >
                    No products are available right now.
                </div>
            </section>
        </main>
    </StoreLayout>
</template>