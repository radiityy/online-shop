<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
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

const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

const sortedVariants = computed(() => {
    return [...props.product.variants].sort((a, b) => {
        const sizeA = (a.size ?? '').toUpperCase();
        const sizeB = (b.size ?? '').toUpperCase();

        const indexA = sizeOrder.indexOf(sizeA);
        const indexB = sizeOrder.indexOf(sizeB);

        if (indexA === -1 && indexB === -1) {
            return sizeA.localeCompare(sizeB);
        }

        if (indexA === -1) {
            return 1;
        }

        if (indexB === -1) {
            return -1;
        }

        return indexA - indexB;
    });
});

const selectedVariantId = ref<number | null>(sortedVariants.value[0]?.id ?? null);
const quantity = ref(1);
const activeTab = ref<'description' | 'sizeGuide'>('description');

const weight = ref<number | null>(null);
const height = ref<number | null>(null);
const suggestedSize = ref<string | null>(null);

const form = useForm<{
    product_variant_id: number | null;
    quantity: number;
    buy_now: boolean;
}>({
    product_variant_id: selectedVariantId.value,
    quantity: quantity.value,
    buy_now: false,
});

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

const canAddToBag = computed(() => {
    return selectedVariant.value !== null && availableStock.value > 0 && quantity.value > 0;
});

const availableSizes = computed(() => {
    return sortedVariants.value
        .map((variant) => variant.size)
        .filter(Boolean);
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

const sizeLabel = (variant: ProductVariant) => {
    return variant.size ?? variant.sku ?? 'Default';
};

const selectVariant = (variant: ProductVariant) => {
    if (variant.stock <= 0) {
        return;
    }

    selectedVariantId.value = variant.id;

    if (quantity.value > variant.stock) {
        quantity.value = variant.stock;
    }

    if (quantity.value < 1) {
        quantity.value = 1;
    }
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

const submitBag = (buyNow = false) => {
    if (!canAddToBag.value || form.processing) {
        return;
    }

    form.clearErrors();

    form.product_variant_id = selectedVariantId.value;
    form.quantity = quantity.value;
    form.buy_now = buyNow;

    form.post('/cart', {
        preserveScroll: true,
    });
};

const checkSize = () => {
    if (!weight.value || !height.value) {
        suggestedSize.value = null;
        return;
    }

    const w = Number(weight.value);
    const h = Number(height.value);

    if (h <= 165 && w <= 58) {
        suggestedSize.value = 'S';
    } else if (h <= 172 && w <= 68) {
        suggestedSize.value = 'M';
    } else if (h <= 180 && w <= 78) {
        suggestedSize.value = 'L';
    } else if (h <= 188 && w <= 90) {
        suggestedSize.value = 'XL';
    } else {
        suggestedSize.value = 'XXL';
    }
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <div class="mb-10 text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                <Link href="/" class="hover:text-neutral-950">
                    Home
                </Link>
                <span class="mx-2">/</span>
                <Link href="/products" class="hover:text-neutral-950">
                    Shop
                </Link>
                <span class="mx-2">/</span>
                <span class="text-neutral-950">
                    {{ product.name }}
                </span>
            </div>

            <section class="grid gap-12 lg:grid-cols-[1.1fr_0.9fr] xl:gap-16">
                <div>
                    <div class="overflow-hidden bg-neutral-100">
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
                            class="overflow-hidden border bg-neutral-100"
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

                <div class="lg:sticky lg:top-28 lg:h-fit">
                    <p
                        v-if="product.category"
                        class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500"
                    >
                        {{ product.category.name }}
                    </p>

                    <h1 class="mt-4 text-4xl font-black uppercase leading-none tracking-[-0.045em] md:text-6xl">
                        {{ product.name }}
                    </h1>

                    <p class="mt-6 text-2xl font-black">
                        {{ formatPrice(finalPrice) }}
                    </p>

                    <div class="mt-8">
                        <div class="mb-4 flex items-center justify-between">
                            <p class="text-sm font-black uppercase tracking-[0.22em]">
                                Select Size
                            </p>

                            <p
                                v-if="selectedVariant"
                                class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-500"
                            >
                                Stock: {{ availableStock }}
                            </p>
                        </div>

                        <div
                            v-if="product.variants.length"
                            class="grid grid-cols-4 gap-3 sm:grid-cols-5"
                        >
                            <button
                                v-for="variant in sortedVariants"
                                :key="variant.id"
                                type="button"
                                :disabled="variant.stock <= 0"
                                class="border px-4 py-4 text-sm font-black uppercase transition"
                                :class="[
                                    selectedVariantId === variant.id
                                        ? 'border-neutral-950 bg-neutral-950 text-white'
                                        : 'border-neutral-300 bg-white text-neutral-950 hover:border-neutral-950',
                                    variant.stock <= 0 ? 'cursor-not-allowed opacity-35' : '',
                                ]"
                                @click="selectVariant(variant)"
                            >
                                {{ sizeLabel(variant) }}
                            </button>
                        </div>

                        <div
                            v-else
                            class="border border-dashed border-neutral-300 p-5 text-sm text-neutral-500"
                        >
                            No active sizes available.
                        </div>

                        <p
                            v-if="form.errors.product_variant_id"
                            class="mt-3 text-sm font-medium text-red-600"
                        >
                            {{ form.errors.product_variant_id }}
                        </p>
                    </div>

                    <div class="mt-8">
                        <p class="mb-4 text-sm font-black uppercase tracking-[0.22em]">
                            Quantity
                        </p>

                        <div class="inline-flex items-center border border-neutral-300">
                            <button
                                type="button"
                                class="px-5 py-3 text-lg disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="quantity <= 1"
                                @click="decreaseQuantity"
                            >
                                -
                            </button>

                            <span class="min-w-12 text-center font-black">
                                {{ quantity }}
                            </span>

                            <button
                                type="button"
                                class="px-5 py-3 text-lg disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="quantity >= availableStock"
                                @click="increaseQuantity"
                            >
                                +
                            </button>
                        </div>

                        <p
                            v-if="form.errors.quantity"
                            class="mt-3 text-sm font-medium text-red-600"
                        >
                            {{ form.errors.quantity }}
                        </p>
                    </div>

                    <div class="mt-8 grid gap-3">
                        <button
                            type="button"
                            :disabled="!canAddToBag || form.processing"
                            class="w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                            @click="submitBag(false)"
                        >
                            {{ form.processing && !form.buy_now ? 'Adding...' : 'Add To Bag' }}
                        </button>

                        <button
                            type="button"
                            :disabled="!canAddToBag || form.processing"
                            class="w-full border border-neutral-950 bg-white px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white disabled:cursor-not-allowed disabled:border-neutral-300 disabled:text-neutral-300"
                            @click="submitBag(true)"
                        >
                            {{ form.processing && form.buy_now ? 'Processing...' : 'Buy Now' }}
                        </button>
                    </div>

                    <p
                        v-if="!selectedVariant"
                        class="mt-4 text-center text-xs text-neutral-500"
                    >
                        Please select an available size before adding this product to bag.
                    </p>

                    <div class="mt-8 border-t border-neutral-200 pt-6">
                        <div class="flex gap-10 border-b border-neutral-200">
                            <button
                                type="button"
                                class="pb-4 text-xs font-black uppercase tracking-[0.22em]"
                                :class="activeTab === 'description' ? 'border-b border-neutral-950 text-neutral-950' : 'text-neutral-400'"
                                @click="activeTab = 'description'"
                            >
                                Keterangan
                            </button>

                            <button
                                type="button"
                                class="pb-4 text-xs font-black uppercase tracking-[0.22em]"
                                :class="activeTab === 'sizeGuide' ? 'border-b border-neutral-950 text-neutral-950' : 'text-neutral-400'"
                                @click="activeTab = 'sizeGuide'"
                            >
                                Size Guide
                            </button>
                        </div>

                        <div v-if="activeTab === 'description'" class="py-6">
                            <p
                                v-if="product.description"
                                class="max-w-xl text-sm leading-7 text-neutral-600 md:text-base"
                            >
                                {{ product.description }}
                            </p>

                            <p
                                v-else
                                class="max-w-xl text-sm leading-7 text-neutral-600 md:text-base"
                            >
                                Clean daily wear piece from NEVERENDING. Designed for everyday movement and endless rotation.
                            </p>

                            <div class="mt-6 grid gap-3 text-xs font-bold uppercase tracking-[0.18em] text-neutral-500">
                                <p>Manual transfer payment</p>
                                <p>Shipping by JNE / J&T / SiCepat / Anteraja</p>
                                <p>Daily wear for endless rotation</p>
                            </div>
                        </div>

                        <div v-if="activeTab === 'sizeGuide'" class="py-6">
                            <div class="bg-neutral-100 p-6">
                                <p class="text-lg font-medium">
                                    Enter your weight & height to check the right size.
                                </p>

                                <div class="mt-6 grid gap-4 md:grid-cols-[1fr_1fr_auto]">
                                    <div>
                                        <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                            Weight (KG)
                                        </label>

                                        <input
                                            v-model.number="weight"
                                            type="number"
                                            min="1"
                                            placeholder="Exp: 65"
                                            class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none focus:border-neutral-950"
                                        />
                                    </div>

                                    <div>
                                        <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                            Height (CM)
                                        </label>

                                        <input
                                            v-model.number="height"
                                            type="number"
                                            min="1"
                                            placeholder="Exp: 175"
                                            class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none focus:border-neutral-950"
                                        />
                                    </div>

                                    <button
                                        type="button"
                                        class="self-end bg-neutral-950 px-10 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                                        @click="checkSize"
                                    >
                                        Check
                                    </button>
                                </div>

                                <div
                                    v-if="suggestedSize"
                                    class="mt-6 border border-neutral-950 bg-white p-5"
                                >
                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-neutral-500">
                                        Suggested Size
                                    </p>

                                    <p class="mt-2 text-3xl font-black uppercase">
                                        {{ suggestedSize }}
                                    </p>

                                    <p
                                        v-if="!availableSizes.includes(suggestedSize)"
                                        class="mt-2 text-sm text-neutral-500"
                                    >
                                        Size {{ suggestedSize }} may not be available for this product. Please check available sizes above.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 overflow-hidden border border-neutral-200">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-neutral-950 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-xs font-black uppercase tracking-[0.18em]">Size</th>
                                            <th class="px-4 py-3 text-xs font-black uppercase tracking-[0.18em]">Weight</th>
                                            <th class="px-4 py-3 text-xs font-black uppercase tracking-[0.18em]">Height</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr class="border-t border-neutral-200">
                                            <td class="px-4 py-3 font-bold">S</td>
                                            <td class="px-4 py-3 text-neutral-600">45 - 58 kg</td>
                                            <td class="px-4 py-3 text-neutral-600">150 - 165 cm</td>
                                        </tr>

                                        <tr class="border-t border-neutral-200">
                                            <td class="px-4 py-3 font-bold">M</td>
                                            <td class="px-4 py-3 text-neutral-600">59 - 68 kg</td>
                                            <td class="px-4 py-3 text-neutral-600">166 - 172 cm</td>
                                        </tr>

                                        <tr class="border-t border-neutral-200">
                                            <td class="px-4 py-3 font-bold">L</td>
                                            <td class="px-4 py-3 text-neutral-600">69 - 78 kg</td>
                                            <td class="px-4 py-3 text-neutral-600">173 - 180 cm</td>
                                        </tr>

                                        <tr class="border-t border-neutral-200">
                                            <td class="px-4 py-3 font-bold">XL</td>
                                            <td class="px-4 py-3 text-neutral-600">79 - 90 kg</td>
                                            <td class="px-4 py-3 text-neutral-600">181 - 188 cm</td>
                                        </tr>

                                        <tr class="border-t border-neutral-200">
                                            <td class="px-4 py-3 font-bold">XXL</td>
                                            <td class="px-4 py-3 text-neutral-600">90+ kg</td>
                                            <td class="px-4 py-3 text-neutral-600">188+ cm</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="relatedProducts.length"
                class="mt-24"
            >
                <div class="mb-10 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                            You May Also Like
                        </p>

                        <h2 class="mt-3 text-4xl font-black uppercase tracking-[-0.04em] md:text-5xl">
                            Related Products
                        </h2>
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
                        v-for="item in relatedProducts"
                        :key="item.id"
                        :href="`/products/${item.slug}`"
                        class="group"
                    >
                        <div class="overflow-hidden bg-neutral-100">
                            <img
                                :src="storageUrl(item.primary_image?.image_path)"
                                :alt="item.name"
                                class="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-105"
                            />
                        </div>

                        <div class="mt-4">
                            <p
                                v-if="item.category"
                                class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-500"
                            >
                                {{ item.category.name }}
                            </p>

                            <h3 class="mt-2 text-sm font-black uppercase tracking-tight md:text-base">
                                {{ item.name }}
                            </h3>

                            <p class="mt-1 text-sm font-medium text-neutral-600">
                                {{ formatPrice(item.price) }}
                            </p>
                        </div>
                    </Link>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>