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
    weight: number;
    category?: {
        id: number;
        name: string;
        slug: string;
    };
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
</script>

<template>
    <StoreLayout>
        <main>
            <section class="relative">
                <div
                    v-if="banners.length"
                   class="relative min-h-[760px] overflow-hidden bg-neutral-950 sm:min-h-[780px] lg:min-h-[720px] xl:min-h-[740px]"
                >
                    <img
                        :src="storageUrl(banners[0].image_path)"
                        :alt="banners[0].title"
                        class="absolute inset-0 h-full w-full object-cover"
                    />

                    <div class="absolute inset-0 bg-black/35"></div>

                    <div class="absolute inset-x-0 top-[390px] z-10 px-6 sm:top-[315px] sm:px-10 md:top-[300px] lg:top-[250px] lg:px-[4.5vw] xl:top-[335px]">
                        <div class="mx-auto max-w-[1840px]">
                            <div class="max-w-[1120px] text-white">
                                <p class="mb-7 text-[11px] font-black uppercase tracking-[0.42em] text-white/85 md:text-xs">
                                    Neverending Daily Wear
                                </p>

                                <h1 class="text-[68px] font-black uppercase leading-[0.92] tracking-[-0.045em] sm:text-[82px] md:text-[100px] lg:text-[116px] xl:text-[128px]">
                                    {{ banners[0].title }}
                                </h1>

                                <p
                                    v-if="banners[0].subtitle"
                                    class="mt-7 max-w-xl text-sm leading-7 text-white/80 md:text-base"
                                >
                                    {{ banners[0].subtitle }}
                                </p>

                                <Link
                                    :href="banners[0].link_url || '/products'"
                                    class="mt-9 inline-flex rounded-full border border-white bg-white px-9 py-4 text-[11px] font-black uppercase tracking-[0.26em] text-neutral-950 transition duration-300 hover:bg-white/10 hover:text-white hover:backdrop-blur-sm"
                                >
                                    Shop Now
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="relative flex min-h-[700px] items-center justify-start bg-neutral-100 px-6 sm:px-10 lg:min-h-[720px] lg:px-[7.5vw] xl:min-h-[760px]"
                >
                    <div class="mx-auto w-full max-w-[1840px]">
                        <p class="text-[11px] font-black uppercase tracking-[0.42em] text-neutral-500 md:text-xs">
                            Neverending Daily Wear
                        </p>

                        <h1 class="mt-7 text-[68px] font-black uppercase leading-[0.92] tracking-[-0.045em] sm:text-[82px] md:text-[100px] lg:text-[116px]">
                            New Arrival
                        </h1>

                        <p class="mt-6 text-neutral-600">
                            Add active banner from admin panel.
                        </p>

                        <Link
                            href="/admin"
                            class="mt-9 inline-flex rounded-full bg-neutral-950 px-9 py-4 text-[11px] font-black uppercase tracking-[0.26em] text-white transition hover:bg-neutral-800"
                        >
                            Add Banner
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-[1840px] px-6 py-20 sm:px-10 lg:px-[7.5vw]">
                <div class="mb-10 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.28em] text-neutral-500">
                            New Arrivals
                        </p>

                        <h2 class="mt-3 text-4xl font-black uppercase tracking-[-0.03em] md:text-5xl">
                            Fresh Rotation
                        </h2>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
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
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-3xl border border-dashed border-neutral-300 p-12 text-center text-neutral-500"
                >
                    Belum ada produk aktif. Tambahkan produk dari admin panel.
                </div>
            </section>

            <section class="bg-neutral-950 px-6 py-20 text-white sm:px-10 lg:px-[7.5vw]">
                <div class="mx-auto grid max-w-[1840px] gap-10 md:grid-cols-[1fr_1.2fr] md:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-white/50">
                            About Neverending
                        </p>

                        <h2 class="mt-4 text-4xl font-black uppercase leading-tight tracking-[-0.03em] md:text-6xl">
                            Daily wear for endless rotation.
                        </h2>
                    </div>

                    <div>
                        <p class="text-base leading-8 text-white/70">
                            NEVERENDING menghadirkan pakaian clean, modern, dan mudah dipakai untuk aktivitas sehari-hari.
                            Setiap koleksi dirancang sebagai bagian dari rotasi outfit yang tidak cepat hilang oleh tren.
                        </p>

                        <Link
                            href="/products"
                            class="mt-8 inline-flex rounded-full border border-white bg-white px-8 py-4 text-[11px] font-black uppercase tracking-[0.25em] text-neutral-950 transition duration-300 hover:bg-white/10 hover:text-white hover:backdrop-blur-sm"
                        >
                            Explore Collection
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>