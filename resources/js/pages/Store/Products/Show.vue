<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Category = {
    id: number;
    name: string;
    slug: string;
};

type ProductImage = {
    id: number;
    product_id: number;
    image_path: string;
    is_primary: boolean;
    sort_order: number;
};

type ProductVariant = {
    id: number;
    product_id: number;
    size: string | null;
    color: string | null;
    sku: string | null;
    stock: number;
    additional_price: string;
    is_active: boolean;
};

type Product = {
    id: number;
    category_id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string;
    weight: number;
    category?: Category;
    images: ProductImage[];
    variants: ProductVariant[];
};

type RelatedProduct = {
    id: number;
    name: string;
    slug: string;
    price: string;
    category?: Category;
    primary_image?: {
        id: number;
        product_id: number;
        image_path: string;
    } | null;
};

const props = defineProps<{
    product: Product;
    relatedProducts: RelatedProduct[];
}>();

const selectedImage = ref<ProductImage | null>(props.product.images[0] ?? null);
const selectedVariantId = ref<number | null>(props.product.variants[0]?.id ?? null);
const quantity = ref(1);

const selectedVariant = computed(() => {
    return props.product.variants.find((variant) => variant.id === selectedVariantId.value) ?? null;
});

const finalPrice = computed(() => {
    const basePrice = Number(props.product.price);
    const additionalPrice = Number(selectedVariant.value?.additional_price ?? 0);

    return basePrice + additionalPrice;
});

const availableStock = computed(() => {
    return selectedVariant.value?.stock ?? 0;
});

const canAddToCart = computed(() => {
    return selectedVariant.value !== null && availableStock.value > 0 && quantity.value > 0;
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

const variantLabel = (variant: ProductVariant) => {
    const parts = [variant.size, variant.color].filter(Boolean);

    return parts.length ? parts.join(' / ') : variant.sku ?? 'Default';
};

const increaseQuantity = () => {
    if (quantity.value < availableStock.value) {
        quantity.value++;
    }
};

const decreaseQuantity = () => {
    if (quantity.value > 1) {
        quantity.value--;
    }
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
            <div class="mb-8 text-sm text-neutral-500">
                <Link href="/" class="hover:text-neutral-950">Home</Link>
                <span class="mx-2">/</span>
                <Link href="/products" class="hover:text-neutral-950">Shop</Link>
                <span class="mx-2">/</span>
                <span class="text-neutral-950">{{ product.name }}</span>
            </div>

            <section class="grid gap-12 lg:grid-cols-2">
                <div>
                    <div class="overflow-hidden rounded-3xl bg-neutral-100">
                        <img
                            :src="storageUrl(selectedImage?.image_path)"
                            :alt="product.name"
                            class="aspect-[4/5] w-full object-cover"
                        />
                    </div>

                    <div
                        v-if="product.images.length > 1"
                        class="mt-5 grid grid-cols-4 gap-4"
                    >
                        <button
                            v-for="image in product.images"
                            :key="image.id"
                            type="button"
                            class="overflow-hidden rounded-2xl border bg-neutral-100"
                            :class="selectedImage?.id === image.id ? 'border-neutral-950' : 'border-transparent'"
                            @click="selectedImage = image"
                        >
                            <img
                                :src="storageUrl(image.image_path)"
                                :alt="product.name"
                                class="aspect-square w-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <div class="lg:pt-6">
                    <p
                        v-if="product.category"
                        class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500"
                    >
                        {{ product.category.name }}
                    </p>

                    <h1 class="mt-3 text-4xl font-black tracking-tight md:text-5xl">
                        {{ product.name }}
                    </h1>

                    <p class="mt-5 text-2xl font-bold">
                        {{ formatPrice(finalPrice) }}
                    </p>

                    <div
                        v-if="product.description"
                        class="mt-8 border-y border-neutral-200 py-8 text-neutral-700"
                    >
                        <p class="leading-7">
                            {{ product.description }}
                        </p>
                    </div>

                    <div class="mt-8">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="font-bold">Select Variant</p>
                            <p
                                v-if="selectedVariant"
                                class="text-sm text-neutral-500"
                            >
                                Stock: {{ availableStock }}
                            </p>
                        </div>

                        <div
                            v-if="product.variants.length"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                        >
                            <button
                                v-for="variant in product.variants"
                                :key="variant.id"
                                type="button"
                                :disabled="variant.stock <= 0"
                                class="rounded-2xl border px-4 py-3 text-sm font-semibold transition"
                                :class="[
                                    selectedVariantId === variant.id
                                        ? 'border-neutral-950 bg-neutral-950 text-white'
                                        : 'border-neutral-300 bg-white text-neutral-950 hover:border-neutral-950',
                                    variant.stock <= 0 ? 'cursor-not-allowed opacity-40' : '',
                                ]"
                                @click="selectedVariantId = variant.id"
                            >
                                {{ variantLabel(variant) }}
                            </button>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-neutral-300 p-5 text-sm text-neutral-500"
                        >
                            No active variants available.
                        </div>
                    </div>

                    <div class="mt-8">
                        <p class="mb-3 font-bold">Quantity</p>

                        <div class="inline-flex items-center rounded-full border border-neutral-300">
                            <button
                                type="button"
                                class="px-5 py-3 text-lg"
                                @click="decreaseQuantity"
                            >
                                -
                            </button>

                            <span class="min-w-12 text-center font-bold">
                                {{ quantity }}
                            </span>

                            <button
                                type="button"
                                class="px-5 py-3 text-lg"
                                @click="increaseQuantity"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="!canAddToCart"
                        class="mt-8 w-full rounded-full bg-neutral-950 px-8 py-4 text-sm font-bold uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                    >
                        Add To Cart
                    </button>

                    <p class="mt-4 text-center text-xs text-neutral-500">
                        Cart functionality will be connected in the next step.
                    </p>
                </div>
            </section>

            <section
                v-if="relatedProducts.length"
                class="mt-20"
            >
                <div class="mb-8 flex items-end justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                            You May Also Like
                        </p>
                        <h2 class="mt-2 text-3xl font-black">
                            Related Products
                        </h2>
                    </div>

                    <Link href="/products" class="text-sm font-bold underline">
                        View All
                    </Link>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="item in relatedProducts"
                        :key="item.id"
                        :href="`/products/${item.slug}`"
                        class="group"
                    >
                        <div class="overflow-hidden rounded-2xl bg-neutral-100">
                            <img
                                :src="storageUrl(item.primary_image?.image_path)"
                                :alt="item.name"
                                class="aspect-[4/5] w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                        </div>

                        <div class="mt-4">
                            <p
                                v-if="item.category"
                                class="text-xs font-semibold uppercase tracking-widest text-neutral-500"
                            >
                                {{ item.category.name }}
                            </p>

                            <h3 class="mt-1 font-bold">
                                {{ item.name }}
                            </h3>

                            <p class="mt-1 text-sm text-neutral-600">
                                {{ formatPrice(item.price) }}
                            </p>
                        </div>
                    </Link>
                </div>
            </section>
        </main>
    </div>
</template>