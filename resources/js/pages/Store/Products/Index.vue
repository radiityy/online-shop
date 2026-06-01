<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
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
            preserveScroll: true,
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
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Shop
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                            All Products
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Explore NEVERENDING daily wear pieces built for endless rotation.
                        </p>
                    </div>

                    <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                        {{ products.total }} Product(s)
                    </p>
                </div>
            </section>

            <section class="mb-12 border-y border-neutral-200 py-6">
                <div class="grid gap-4 md:grid-cols-[1fr_260px_auto_auto]">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search product..."
                        class="rounded-none border border-neutral-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-neutral-950"
                        @keyup.enter="applyFilters"
                    />

                    <select
                        v-model="selectedCategory"
                        class="rounded-none border border-neutral-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-neutral-950"
                    >
                        <option value="">
                            All categories
                        </option>

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
                        class="bg-neutral-950 px-7 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                        @click="applyFilters"
                    >
                        Apply
                    </button>

                    <button
                        type="button"
                        class="border border-neutral-300 bg-white px-7 py-3 text-xs font-black uppercase tracking-[0.2em] transition hover:bg-neutral-100"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </section>

            <section>
                <div
                    v-if="products.data.length"
                    class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Link
                        v-for="product in products.data"
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

                            <h2 class="mt-2 text-sm font-black uppercase tracking-tight md:text-base">
                                {{ product.name }}
                            </h2>

                            <p class="mt-1 text-sm font-medium text-neutral-600">
                                {{ formatPrice(product.price) }}
                            </p>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="border border-dashed border-neutral-300 p-14 text-center"
                >
                    <h2 class="text-2xl font-black uppercase tracking-tight">
                        No products found
                    </h2>

                    <p class="mt-3 text-neutral-500">
                        Try another keyword or category.
                    </p>
                </div>

                <div
                    v-if="products.links.length > 3"
                    class="mt-14 flex flex-wrap justify-center gap-2"
                >
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        preserve-scroll
                        :class="[
                            'border px-4 py-2 text-sm',
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
    </StoreLayout>
</template>