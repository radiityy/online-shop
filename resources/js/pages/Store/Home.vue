<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

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

                <div class="flex items-center gap-4 text-sm font-medium">
                    <Link href="/login" class="hover:text-neutral-500">Login</Link>
                    <Link
                        href="/register"
                        class="rounded-full bg-neutral-950 px-5 py-2 text-white hover:bg-neutral-800"
                    >
                        Register
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-7xl px-6 py-8">
                <div
                    v-if="banners.length"
                    class="relative overflow-hidden rounded-3xl bg-neutral-100"
                >
                    <img
                        :src="storageUrl(banners[0].image_path)"
                        :alt="banners[0].title"
                        class="h-[520px] w-full object-cover"
                    />

                    <div class="absolute inset-0 bg-black/35"></div>

                    <div class="absolute inset-0 flex items-center">
                        <div class="max-w-2xl px-8 text-white md:px-16">
                            <p class="mb-4 text-sm font-semibold uppercase tracking-[0.3em]">
                                New Collection
                            </p>

                            <h1 class="text-5xl font-black tracking-tight md:text-7xl">
                                {{ banners[0].title }}
                            </h1>

                            <p
                                v-if="banners[0].subtitle"
                                class="mt-5 max-w-xl text-lg text-white/85"
                            >
                                {{ banners[0].subtitle }}
                            </p>

                            <Link
                                :href="banners[0].link_url || '/products'"
                                class="mt-8 inline-flex rounded-full bg-white px-7 py-3 text-sm font-bold text-neutral-950 hover:bg-neutral-200"
                            >
                                Shop Now
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex h-[520px] items-center justify-center rounded-3xl bg-neutral-100 text-center"
                >
                    <div>
                        <h1 class="text-5xl font-black">NEW ARRIVAL</h1>
                        <p class="mt-4 text-neutral-600">
                            Add active banner from admin panel.
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-14">
                <div class="mb-8 flex items-end justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                            Latest Products
                        </p>
                        <h2 class="mt-2 text-3xl font-black">
                            Fresh From The Store
                        </h2>
                    </div>

                    <Link href="/products" class="text-sm font-bold underline">
                        View All
                    </Link>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="product in products"
                        :key="product.id"
                        :href="`/products/${product.slug}`"
                        class="group"
                    >
                        <div class="overflow-hidden rounded-2xl bg-neutral-100">
                            <img
                                :src="storageUrl(product.primary_image?.image_path)"
                                :alt="product.name"
                                class="aspect-[4/5] w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                        </div>

                        <div class="mt-4">
                            <p
                                v-if="product.category"
                                class="text-xs font-semibold uppercase tracking-widest text-neutral-500"
                            >
                                {{ product.category.name }}
                            </p>

                            <h3 class="mt-1 font-bold">
                                {{ product.name }}
                            </h3>

                            <p class="mt-1 text-sm text-neutral-600">
                                {{ formatPrice(product.price) }}
                            </p>
                        </div>
                    </Link>
                </div>

                <div
                    v-if="!products.length"
                    class="rounded-2xl border border-dashed border-neutral-300 p-10 text-center text-neutral-500"
                >
                    Belum ada produk aktif. Tambahkan produk dari admin panel.
                </div>
            </section>
        </main>
    </div>
</template>