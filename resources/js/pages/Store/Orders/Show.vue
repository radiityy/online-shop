<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { ref } from 'vue';

type OrderItem = {
    id: number;
    product_name: string;
    variant_name: string | null;
    sku: string | null;
    price: string;
    quantity: number;
    subtotal: string;
    product?: {
        id: number;
        slug: string;
        primary_image?: {
            image_path: string;
        } | null;
    } | null;
};

type Order = {
    id: number;
    order_code: string;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    postal_code: string | null;
    full_address: string;
    subtotal: string;
    shipping_cost: string;
    total: string;
    payment_status: string;
    order_status: string;
    shipping_status: string;
    notes: string | null;
    created_at: string;
    items: OrderItem[];
    payment?: {
        payment_method: string;
        amount: string;
        status: string;
        proof_image: string | null;
    } | null;
    shipment?: {
        courier: string | null;
        service: string | null;
        tracking_number: string | null;
        status: string;
    } | null;
};

const props = defineProps<{
    order: Order;
}>();

const selectedProofName = ref<string | null>(null);

const form = useForm<{
    proof_image: File | null;
}>({
    proof_image: null,
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

const formatDate = (date: string) => {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
};

const statusLabel = (status: string) => {
    return status.replaceAll('_', ' ').toUpperCase();
};

const sizeLabel = (item: OrderItem) => {
    if (item.variant_name) {
        return item.variant_name.split('/')[0]?.trim() || item.variant_name;
    }

    return item.sku ?? 'Default';
};

const statusClass = (status: string) => {
    if (['paid', 'completed', 'delivered'].includes(status)) {
        return 'bg-green-100 text-green-700';
    }

    if (['processing', 'shipped'].includes(status)) {
        return 'bg-blue-100 text-blue-700';
    }

    if (['packed', 'pending', 'not_shipped'].includes(status)) {
        return 'bg-yellow-100 text-yellow-700';
    }

    if (['failed', 'expired', 'cancelled', 'refunded', 'returned'].includes(status)) {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-neutral-100 text-neutral-700';
};

const handleProofChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    form.proof_image = file;
    selectedProofName.value = file?.name ?? null;
};

const uploadProof = () => {
    form.post(`/orders/${props.order.order_code}/payment-proof`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('proof_image');
            selectedProofName.value = null;
        },
    });
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Order Detail
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-4xl font-black uppercase tracking-[-0.045em] md:text-6xl">
                            {{ order.order_code }}
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Track your NEVERENDING order, upload payment proof, and check shipping progress.
                        </p>

                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.18em] text-neutral-400">
                            Created at {{ formatDate(order.created_at) }}
                        </p>
                    </div>

                    <Link
                        href="/orders"
                        class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                    >
                        Back To Orders
                    </Link>
                </div>
            </section>

            <section class="grid gap-10 lg:grid-cols-[1fr_430px]">
                <div class="space-y-6">
                    <section class="border border-neutral-200 p-6 md:p-8">
                        <div class="mb-8">
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                Ordered Items
                            </p>

                            <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                                Your Pieces
                            </h2>
                        </div>

                        <div class="space-y-5">
                            <article
                                v-for="item in order.items"
                                :key="item.id"
                                class="grid gap-5 border-b border-neutral-200 pb-5 last:border-b-0 last:pb-0 sm:grid-cols-[110px_1fr_auto]"
                            >
                                <Link
                                    v-if="item.product"
                                    :href="`/products/${item.product.slug}`"
                                    class="block overflow-hidden bg-neutral-100"
                                >
                                    <img
                                        :src="storageUrl(item.product?.primary_image?.image_path)"
                                        :alt="item.product_name"
                                        class="aspect-[4/5] w-full object-cover transition duration-500 hover:scale-105"
                                    />
                                </Link>

                                <div
                                    v-else
                                    class="block aspect-[4/5] bg-neutral-100"
                                ></div>

                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-500">
                                        Size {{ sizeLabel(item) }}
                                    </p>

                                    <h3 class="mt-2 text-xl font-black uppercase tracking-[-0.03em]">
                                        {{ item.product_name }}
                                    </h3>

                                    <p class="mt-2 text-sm text-neutral-500">
                                        {{ item.quantity }} x {{ formatPrice(item.price) }}
                                    </p>
                                </div>

                                <p class="font-black sm:text-right">
                                    {{ formatPrice(item.subtotal) }}
                                </p>
                            </article>
                        </div>
                    </section>

                    <section class="border border-neutral-200 p-6 md:p-8">
                        <div class="mb-6">
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                Shipping Address
                            </p>

                            <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                                Delivery Details
                            </h2>
                        </div>

                        <div class="text-sm leading-7 text-neutral-600">
                            <p class="font-black uppercase tracking-[0.1em] text-neutral-950">
                                {{ order.recipient_name }}
                            </p>

                            <p class="mt-1">
                                {{ order.phone }}
                            </p>

                            <p class="mt-4">
                                {{ order.full_address }}
                            </p>

                            <p>
                                {{ order.district }}, {{ order.city }}, {{ order.province }}
                                <span v-if="order.postal_code">, {{ order.postal_code }}</span>
                            </p>

                            <p
                                v-if="order.notes"
                                class="mt-4 border-l-2 border-neutral-950 pl-4"
                            >
                                Notes: {{ order.notes }}
                            </p>
                        </div>
                    </section>

                    <section class="border border-neutral-200 p-6 md:p-8">
                        <div class="mb-6">
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                Payment Proof
                            </p>

                            <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                                Manual Transfer
                            </h2>
                        </div>

                        <div class="bg-neutral-100 p-5 text-sm leading-7 text-neutral-700">
                            <p class="font-black uppercase tracking-[0.18em] text-neutral-950">
                                Transfer Information
                            </p>

                            <p class="mt-3">Bank: BCA</p>
                            <p>No. Rekening: 1234567890</p>
                            <p>Atas Nama: NEVERENDING</p>

                            <p class="mt-3 font-black text-neutral-950">
                                Total: {{ formatPrice(order.total) }}
                            </p>
                        </div>

                        <div
                            v-if="order.payment?.proof_image"
                            class="mt-6 border border-neutral-200 p-4"
                        >
                            <p class="mb-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                                Current Proof
                            </p>

                            <img
                                :src="storageUrl(order.payment.proof_image)"
                                alt="Payment proof"
                                class="max-h-96 w-full bg-neutral-100 object-contain"
                            />

                            <p class="mt-4 text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Proof Status:
                                <span class="text-neutral-950">
                                    {{ statusLabel(order.payment.status) }}
                                </span>
                            </p>
                        </div>

                        <form
                            v-if="order.payment_status !== 'paid'"
                            class="mt-6"
                            @submit.prevent="uploadProof"
                        >
                            <label class="block text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Upload New Proof
                            </label>

                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm"
                                @change="handleProofChange"
                            />

                            <p
                                v-if="selectedProofName"
                                class="mt-2 text-sm text-neutral-500"
                            >
                                Selected: {{ selectedProofName }}
                            </p>

                            <p
                                v-if="form.errors.proof_image"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.proof_image }}
                            </p>

                            <button
                                type="submit"
                                :disabled="form.processing || !form.proof_image"
                                class="mt-5 w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                            >
                                {{ form.processing ? 'Uploading...' : 'Upload Payment Proof' }}
                            </button>
                        </form>

                        <p
                            v-else
                            class="mt-6 bg-green-50 p-4 text-sm font-bold text-green-700"
                        >
                            Payment has been confirmed by admin.
                        </p>
                    </section>
                </div>

                <aside class="h-fit border border-neutral-200 bg-neutral-50 p-6 lg:sticky lg:top-28">
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        Order Summary
                    </h2>

                    <div class="mt-6 grid gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-neutral-500">Payment</span>
                            <span
                                class="px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em]"
                                :class="statusClass(order.payment_status)"
                            >
                                {{ statusLabel(order.payment_status) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-neutral-500">Order</span>
                            <span
                                class="px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em]"
                                :class="statusClass(order.order_status)"
                            >
                                {{ statusLabel(order.order_status) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-neutral-500">Shipping</span>
                            <span
                                class="px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em]"
                                :class="statusClass(order.shipping_status)"
                            >
                                {{ statusLabel(order.shipping_status) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="order.shipment?.tracking_number"
                        class="mt-6 border border-neutral-200 bg-white p-5 text-sm leading-7"
                    >
                        <p class="font-black uppercase tracking-[0.18em] text-neutral-950">
                            Tracking Info
                        </p>

                        <p class="mt-3 text-neutral-600">
                            Courier:
                            <span class="font-bold text-neutral-950">
                                {{ order.shipment.courier ?? '-' }}
                            </span>
                        </p>

                        <p class="text-neutral-600">
                            Service:
                            <span class="font-bold text-neutral-950">
                                {{ order.shipment.service ?? '-' }}
                            </span>
                        </p>

                        <p class="text-neutral-600">
                            Resi:
                            <span class="font-bold text-neutral-950">
                                {{ order.shipment.tracking_number }}
                            </span>
                        </p>
                    </div>

                    <div class="mt-6 space-y-4 border-y border-neutral-200 py-6 text-sm">
                        <div class="flex justify-between">
                            <span class="text-neutral-500">Subtotal</span>
                            <span class="font-black">{{ formatPrice(order.subtotal) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-500">Shipping</span>
                            <span class="font-black">{{ formatPrice(order.shipping_cost) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between text-xl">
                        <span class="font-black uppercase tracking-[-0.03em]">
                            Total
                        </span>

                        <span class="font-black">
                            {{ formatPrice(order.total) }}
                        </span>
                    </div>

                    <Link
                        href="/products"
                        class="mt-8 flex w-full justify-center bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                    >
                        Continue Shopping
                    </Link>

                    <Link
                        href="/orders"
                        class="mt-3 flex w-full justify-center border border-neutral-950 bg-white px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                    >
                        My Orders
                    </Link>
                </aside>
            </section>
        </main>
    </StoreLayout>
</template>