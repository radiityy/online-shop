<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Category = {
    id: number;
    name: string;
    slug: string;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    price: string;
    weight: number;
    category?: Category;
    primary_image?: {
        id: number;
        image_path: string;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProducts = {
    data: Product[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    categories: Category[];
    products: PaginatedProducts;
    filters: {
        search: string | null;
        category: string | null;
    };
}>();

const search = ref(props.filters.search ?? '');
const selectedCategory = ref(props.filters.category ?? '');

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

const applyFilters = () => {
    router.get(
        '/products',
        {
            search: search.value || undefined,
            category: selectedCategory.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    search.value = '';
    selectedCategory.value = '';

    router.get(
        '/products',
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
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
                    <Link href="/products" class="text-neutral-950 underline underline-offset-4">Shop</Link>
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

        <main class="mx-auto max-w-7xl px-6 py-12">
            <section class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                    Product Catalog
                </p>

                <div class="mt-3 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-4xl font-black tracking-tight md:text-5xl">
                            Shop Collection
                        </h1>
                        <p class="mt-3 max-w-xl text-neutral-600">
                            Browse our latest products and daily wear collection.
                        </p>
                    </div>

                    <p class="text-sm text-neutral-500">
                        {{ products.total }} product(s)
                    </p>
                </div>
            </section>

            <section class="mb-10 rounded-3xl border border-neutral-200 bg-neutral-50 p-5">
                <div class="grid gap-4 md:grid-cols-[1fr_240px_auto_auto]">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search product..."
                        class="rounded-2xl border border-neutral-300 bg-white px-4 py-3 text-sm outline-none focus:border-neutral-950"
                        @keyup.enter="applyFilters"
                    />

                    <select
                        v-model="selectedCategory"
                        class="rounded-2xl border border-neutral-300 bg-white px-4 py-3 text-sm outline-none focus:border-neutral-950"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.slug"
                        >
                            {{ category.name }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="rounded-2xl bg-neutral-950 px-6 py-3 text-sm font-bold text-white hover:bg-neutral-800"
                        @click="applyFilters"
                    >
                        Apply
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl border border-neutral-300 bg-white px-6 py-3 text-sm font-bold hover:bg-neutral-100"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </section>

            <section>
                <div
                    v-if="products.data.length"
                    class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Link
                        v-for="product in products.data"
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

                            <h2 class="mt-1 font-bold">
                                {{ product.name }}
                            </h2>

                            <p class="mt-1 text-sm text-neutral-600">
                                {{ formatPrice(product.price) }}
                            </p>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="rounded-3xl border border-dashed border-neutral-300 p-12 text-center"
                >
                    <h2 class="text-xl font-bold">No products found</h2>
                    <p class="mt-2 text-neutral-500">
                        Try another keyword or category.
                    </p>
                </div>

                <div
                    v-if="products.links.length > 3"
                    class="mt-12 flex flex-wrap justify-center gap-2"
                >
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        preserve-scroll
                        :class="[
                            'rounded-full border px-4 py-2 text-sm',
                            link.active
                                ? 'border-neutral-950 bg-neutral-950 text-white'
                                : 'border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-100',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </section>
        </main>
    </div>
</template>