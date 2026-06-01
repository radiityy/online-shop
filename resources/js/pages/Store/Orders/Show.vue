<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
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

const statusLabel = (status: string) => {
    return status.replaceAll('_', ' ').toUpperCase();
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
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-12">
            <section class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                    Order Detail
                </p>

                <h1 class="mt-3 text-4xl font-black tracking-tight md:text-5xl">
                    {{ order.order_code }}
                </h1>

                <p class="mt-3 text-neutral-600">
                    Thank you for shopping with NEVERENDING. Please complete your payment.
                </p>
            </section>

            <section class="grid gap-10 lg:grid-cols-[1fr_420px]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-neutral-200 p-6">
                        <h2 class="text-xl font-black">
                            Ordered Items
                        </h2>

                        <div class="mt-6 space-y-5">
                            <article
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex gap-4"
                            >
                                <img
                                    :src="storageUrl(item.product?.primary_image?.image_path)"
                                    :alt="item.product_name"
                                    class="h-24 w-24 rounded-2xl bg-neutral-100 object-cover"
                                />

                                <div class="flex-1">
                                    <h3 class="font-bold">
                                        {{ item.product_name }}
                                    </h3>

                                    <p class="mt-1 text-xs uppercase tracking-widest text-neutral-500">
                                        {{ item.variant_name ?? item.sku ?? 'Default' }}
                                    </p>

                                    <p class="mt-2 text-sm text-neutral-600">
                                        {{ item.quantity }} x {{ formatPrice(item.price) }}
                                    </p>
                                </div>

                                <p class="font-bold">
                                    {{ formatPrice(item.subtotal) }}
                                </p>
                            </article>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-neutral-200 p-6">
                        <h2 class="text-xl font-black">
                            Shipping Address
                        </h2>

                        <div class="mt-5 text-sm leading-7 text-neutral-700">
                            <p class="font-bold text-neutral-950">
                                {{ order.recipient_name }}
                            </p>
                            <p>{{ order.phone }}</p>
                            <p>{{ order.full_address }}</p>
                            <p>
                                {{ order.district }}, {{ order.city }}, {{ order.province }}
                                <span v-if="order.postal_code">, {{ order.postal_code }}</span>
                            </p>
                            <p v-if="order.notes" class="mt-3">
                                Notes: {{ order.notes }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-neutral-200 p-6">
                        <h2 class="text-xl font-black">
                            Upload Payment Proof
                        </h2>

                        <div class="mt-5 rounded-2xl bg-neutral-50 p-5 text-sm leading-7 text-neutral-700">
                            <p class="font-bold text-neutral-950">
                                Manual Transfer
                            </p>
                            <p>Bank: BCA</p>
                            <p>No. Rekening: 1234567890</p>
                            <p>Atas Nama: NEVERENDING</p>
                            <p class="mt-2 font-bold text-neutral-950">
                                Total: {{ formatPrice(order.total) }}
                            </p>
                        </div>

                        <div
                            v-if="order.payment?.proof_image"
                            class="mt-5 rounded-2xl border border-neutral-200 p-4"
                        >
                            <p class="mb-3 text-sm font-bold">
                                Current Payment Proof
                            </p>

                            <img
                                :src="storageUrl(order.payment.proof_image)"
                                alt="Payment proof"
                                class="max-h-80 rounded-2xl object-contain"
                            />

                            <p class="mt-3 text-sm text-neutral-500">
                                Status: {{ statusLabel(order.payment.status) }}
                            </p>
                        </div>

                        <form
                            v-if="order.payment_status !== 'paid'"
                            class="mt-5"
                            @submit.prevent="uploadProof"
                        >
                            <label class="block text-sm font-bold">
                                Upload New Proof
                            </label>

                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                class="mt-2 w-full rounded-2xl border border-neutral-300 px-4 py-3 text-sm"
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
                                class="mt-5 w-full rounded-full bg-neutral-950 px-6 py-4 text-sm font-bold uppercase tracking-[0.2em] text-white hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                            >
                                {{ form.processing ? 'Uploading...' : 'Upload Payment Proof' }}
                            </button>
                        </form>

                        <p
                            v-else
                            class="mt-5 rounded-2xl bg-green-50 p-4 text-sm font-medium text-green-700"
                        >
                            Payment has been confirmed by admin.
                        </p>
                    </div>
                </div>

                <aside class="h-fit rounded-3xl border border-neutral-200 bg-neutral-50 p-6">
                    <h2 class="text-xl font-black">
                        Payment Summary
                    </h2>

                    <div class="mt-6 space-y-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-neutral-600">Payment Status</span>
                            <span class="font-bold">{{ statusLabel(order.payment_status) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-600">Order Status</span>
                            <span class="font-bold">{{ statusLabel(order.order_status) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-neutral-600">Shipping Status</span>
                            <span class="font-bold">{{ statusLabel(order.shipping_status) }}</span>
                        </div>

                        <div class="border-t border-neutral-200 pt-4">
                            <div class="flex justify-between py-2">
                                <span class="text-neutral-600">Subtotal</span>
                                <span class="font-bold">{{ formatPrice(order.subtotal) }}</span>
                            </div>

                            <div class="flex justify-between py-2">
                                <span class="text-neutral-600">Shipping</span>
                                <span class="font-bold">{{ formatPrice(order.shipping_cost) }}</span>
                            </div>

                            <div class="mt-4 flex justify-between border-t border-neutral-200 pt-5 text-lg">
                                <span class="font-black">Total</span>
                                <span class="font-black">{{ formatPrice(order.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <Link
                        href="/products"
                        class="mt-6 inline-flex w-full justify-center rounded-full bg-neutral-950 px-6 py-4 text-sm font-bold uppercase tracking-[0.2em] text-white hover:bg-neutral-800"
                    >
                        Continue Shopping
                    </Link>
                </aside>
            </section>
        </main>
    </div>
</template>