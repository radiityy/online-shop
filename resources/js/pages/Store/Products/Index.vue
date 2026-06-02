<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { computed, ref, watch } from 'vue';

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
    sale_price?: string | number | null;
    final_price?: string | number;
    is_on_sale?: boolean;
    weight: number;
    stock_total?: number | null;
    category?: Category | null;
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

const activeCategory = computed(() => {
    return props.categories.find((category) => category.slug === selectedCategory.value) ?? null;
});

const hasFilters = computed(() => {
    return search.value.trim() !== '' || selectedCategory.value !== '';
});

const pageTitle = computed(() => {
    if (activeCategory.value) {
        return activeCategory.value.name;
    }

    if (search.value.trim() !== '') {
        return 'Search Results';
    }

    return 'All Products';
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
        selectedCategory.value = filters.category ?? '';
    },
    {
        deep: true,
    },
);

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

const applyFilters = () => {
    const keyword = search.value.trim();

    router.get(
        '/products',
        {
            search: keyword || undefined,
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
                            {{ pageTitle }}
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Explore NEVERENDING daily wear pieces built for endless rotation.
                        </p>

                        <div
                            v-if="hasFilters"
                            class="mt-5 flex flex-wrap gap-2"
                        >
                            <span
                                v-if="search.trim()"
                                class="border border-neutral-200 bg-neutral-50 px-3 py-2 text-[11px] font-black uppercase tracking-[0.16em] text-neutral-600"
                            >
                                Search: {{ search }}
                            </span>

                            <span
                                v-if="activeCategory"
                                class="border border-neutral-200 bg-neutral-50 px-3 py-2 text-[11px] font-black uppercase tracking-[0.16em] text-neutral-600"
                            >
                                Category: {{ activeCategory.name }}
                            </span>
                        </div>
                    </div>

                    <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                        {{ products.total }} Product(s)
                    </p>
                </div>
            </section>

            <section class="mb-12 border-y border-neutral-200 py-6">
                <div class="grid gap-3 md:grid-cols-[1fr_260px_auto_auto] md:gap-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search product..."
                        class="rounded-none border border-neutral-300 bg-white px-4 py-3 text-sm outline-none transition duration-200 focus:border-neutral-950 focus:ring-1 focus:ring-neutral-950"
                        @keyup.enter="applyFilters"
                    />

                    <select
                        v-model="selectedCategory"
                        class="rounded-none border border-neutral-300 bg-white px-4 py-3 text-sm outline-none transition duration-200 focus:border-neutral-950 focus:ring-1 focus:ring-neutral-950"
                        @change="applyFilters"
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
                        class="bg-neutral-950 px-7 py-3 text-xs font-black uppercase tracking-[0.18em] text-white transition duration-200 hover:bg-neutral-800"
                        @click="applyFilters"
                    >
                        Search
                    </button>

                    <button
                        type="button"
                        class="border border-neutral-300 bg-white px-7 py-3 text-xs font-black uppercase tracking-[0.18em] transition duration-200 hover:border-neutral-950 hover:bg-neutral-50"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </section>

            <section>
                <div
                    v-if="products.data.length"
                    class="grid grid-cols-2 gap-x-3 gap-y-9 sm:gap-x-6 sm:gap-y-12 lg:grid-cols-4"
                >
                    <Link
                        v-for="product in products.data"
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
                                class="text-[10px] font-black uppercase tracking-[0.18em] text-neutral-500 sm:text-[11px]"
                            >
                                {{ product.category.name }}
                            </p>

                            <h2 class="mt-2 line-clamp-2 min-h-[2.5rem] text-xs font-black uppercase leading-5 tracking-tight sm:text-sm md:text-base">
                                {{ product.name }}
                            </h2>

                            <div class="mt-1">
                                <p
                                    v-if="product.is_on_sale"
                                    class="text-[11px] font-semibold text-neutral-400 line-through sm:text-xs"
                                >
                                    {{ formatPrice(product.price) }}
                                </p>

                                <p class="text-xs font-semibold text-neutral-600 sm:text-sm">
                                    {{ formatPrice(product.final_price ?? product.price) }}
                                </p>
                            </div>

                            <p
                                v-if="Number(product.stock_total ?? 0) > 0"
                                class="mt-2 text-[10px] font-black uppercase tracking-[0.14em] text-neutral-400 sm:text-[11px]"
                            >
                                {{ product.stock_total }} in stock
                            </p>

                            <p
                                v-else
                                class="mt-2 text-[10px] font-black uppercase tracking-[0.14em] text-red-500 sm:text-[11px]"
                            >
                                Out of stock
                            </p>
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="border border-dashed border-neutral-300 p-14 text-center"
                >
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                        No Products
                    </p>

                    <h2 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">
                        No products found
                    </h2>

                    <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-neutral-500">
                        Try another keyword or category.
                    </p>

                    <button
                        v-if="hasFilters"
                        type="button"
                        class="mt-7 inline-flex border border-neutral-950 bg-white px-7 py-3 text-xs font-black uppercase tracking-[0.2em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                        @click="resetFilters"
                    >
                        Reset Filters
                    </button>
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