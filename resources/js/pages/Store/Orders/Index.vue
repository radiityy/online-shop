<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

type Payment = {
    id: number;
    order_id: number;
    payment_method: string;
    amount: string;
    status: string;
    proof_image: string | null;
};

type Shipment = {
    id: number;
    order_id: number;
    courier: string | null;
    service: string | null;
    tracking_number: string | null;
    status: string;
};

type Order = {
    id: number;
    order_code: string;
    subtotal: string;
    shipping_cost: string;
    total: string;
    payment_status: string;
    order_status: string;
    shipping_status: string;
    created_at: string;
    items_count: number;
    payment?: Payment | null;
    shipment?: Shipment | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedOrders = {
    data: Order[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

defineProps<{
    orders: PaginatedOrders;
}>();

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
                    <Link href="/orders" class="text-neutral-950 underline underline-offset-4">Orders</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-12">
            <section class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-500">
                    Order History
                </p>

                <h1 class="mt-3 text-4xl font-black tracking-tight md:text-5xl">
                    My Orders
                </h1>

                <p class="mt-3 text-neutral-600">
                    Track your NEVERENDING orders, payment status, and shipping progress.
                </p>
            </section>

            <section v-if="orders.data.length" class="space-y-5">
                <article
                    v-for="order in orders.data"
                    :key="order.id"
                    class="rounded-3xl border border-neutral-200 p-6 transition hover:border-neutral-950"
                >
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <Link
                                    :href="`/orders/${order.order_code}`"
                                    class="text-xl font-black hover:underline"
                                >
                                    {{ order.order_code }}
                                </Link>

                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(order.payment_status)">
                                    Payment: {{ statusLabel(order.payment_status) }}
                                </span>

                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(order.order_status)">
                                    Order: {{ statusLabel(order.order_status) }}
                                </span>

                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(order.shipping_status)">
                                    Shipping: {{ statusLabel(order.shipping_status) }}
                                </span>
                            </div>

                            <p class="mt-3 text-sm text-neutral-500">
                                Created at {{ formatDate(order.created_at) }}
                            </p>

                            <p class="mt-1 text-sm text-neutral-500">
                                {{ order.items_count }} item(s)
                            </p>

                            <div
                                v-if="order.shipment?.tracking_number"
                                class="mt-3 rounded-2xl bg-neutral-50 p-4 text-sm text-neutral-700"
                            >
                                <p>
                                    Courier:
                                    <span class="font-bold text-neutral-950">
                                        {{ order.shipment.courier ?? '-' }}
                                    </span>
                                    <span v-if="order.shipment.service">
                                        / {{ order.shipment.service }}
                                    </span>
                                </p>

                                <p>
                                    Tracking Number:
                                    <span class="font-bold text-neutral-950">
                                        {{ order.shipment.tracking_number }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="text-left lg:text-right">
                            <p class="text-sm text-neutral-500">
                                Total
                            </p>

                            <p class="mt-1 text-2xl font-black">
                                {{ formatPrice(order.total) }}
                            </p>

                            <Link
                                :href="`/orders/${order.order_code}`"
                                class="mt-5 inline-flex rounded-full bg-neutral-950 px-6 py-3 text-sm font-bold text-white hover:bg-neutral-800"
                            >
                                View Detail
                            </Link>
                        </div>
                    </div>
                </article>

                <div
                    v-if="orders.links.length > 3"
                    class="mt-10 flex flex-wrap justify-center gap-2"
                >
                    <Link
                        v-for="link in orders.links"
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

            <section
                v-else
                class="rounded-3xl border border-dashed border-neutral-300 p-12 text-center"
            >
                <h2 class="text-2xl font-black">
                    No orders yet
                </h2>

                <p class="mt-3 text-neutral-500">
                    You have not placed any orders yet.
                </p>

                <Link
                    href="/products"
                    class="mt-8 inline-flex rounded-full bg-neutral-950 px-7 py-3 text-sm font-bold text-white hover:bg-neutral-800"
                >
                    Start Shopping
                </Link>
            </section>
        </main>
    </div>
</template>