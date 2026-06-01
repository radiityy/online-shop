<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';

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
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Order History
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                            My Orders
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Track your NEVERENDING orders, payment status, and shipping progress.
                        </p>
                    </div>

                    <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-500">
                        {{ orders.total }} Order(s)
                    </p>
                </div>
            </section>

            <section
                v-if="orders.data.length"
                class="space-y-5"
            >
                <article
                    v-for="order in orders.data"
                    :key="order.id"
                    class="border border-neutral-200 bg-white p-6 transition hover:border-neutral-950"
                >
                    <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <Link
                                    :href="`/orders/${order.order_code}`"
                                    class="text-2xl font-black uppercase tracking-[-0.04em] hover:underline"
                                >
                                    {{ order.order_code }}
                                </Link>

                                <span
                                    class="px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em]"
                                    :class="statusClass(order.payment_status)"
                                >
                                    Payment: {{ statusLabel(order.payment_status) }}
                                </span>

                                <span
                                    class="px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em]"
                                    :class="statusClass(order.order_status)"
                                >
                                    Order: {{ statusLabel(order.order_status) }}
                                </span>

                                <span
                                    class="px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em]"
                                    :class="statusClass(order.shipping_status)"
                                >
                                    Shipping: {{ statusLabel(order.shipping_status) }}
                                </span>
                            </div>

                            <div class="mt-5 grid gap-3 text-sm text-neutral-600 md:grid-cols-3">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                        Created
                                    </p>

                                    <p class="mt-1 font-medium text-neutral-700">
                                        {{ formatDate(order.created_at) }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                        Items
                                    </p>

                                    <p class="mt-1 font-medium text-neutral-700">
                                        {{ order.items_count }} item(s)
                                    </p>
                                </div>

                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                        Payment Method
                                    </p>

                                    <p class="mt-1 font-medium text-neutral-700">
                                        {{ order.payment?.payment_method?.replaceAll('_', ' ').toUpperCase() ?? 'MANUAL TRANSFER' }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="order.shipment?.tracking_number"
                                class="mt-5 border border-neutral-200 bg-neutral-50 p-5 text-sm leading-7 text-neutral-600"
                            >
                                <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-950">
                                    Tracking Info
                                </p>

                                <p class="mt-3">
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

                        <div class="lg:min-w-[260px] lg:text-right">
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-neutral-400">
                                Total
                            </p>

                            <p class="mt-2 text-3xl font-black tracking-[-0.04em]">
                                {{ formatPrice(order.total) }}
                            </p>

                            <div class="mt-6 grid gap-3">
                                <Link
                                    :href="`/orders/${order.order_code}`"
                                    class="inline-flex justify-center bg-neutral-950 px-7 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                                >
                                    View Detail
                                </Link>

                                <Link
                                    v-if="order.payment_status !== 'paid'"
                                    :href="`/orders/${order.order_code}`"
                                    class="inline-flex justify-center border border-neutral-950 bg-white px-7 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                                >
                                    Upload Proof
                                </Link>
                            </div>
                        </div>
                    </div>
                </article>

                <div
                    v-if="orders.links.length > 3"
                    class="mt-14 flex flex-wrap justify-center gap-2"
                >
                    <Link
                        v-for="link in orders.links"
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

            <section
                v-else
                class="border border-dashed border-neutral-300 p-14 text-center"
            >
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    No Orders
                </p>

                <h2 class="mt-4 text-4xl font-black uppercase tracking-[-0.04em]">
                    You have no orders yet
                </h2>

                <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-neutral-600">
                    Start your endless rotation by placing your first NEVERENDING order.
                </p>

                <Link
                    href="/products"
                    class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                >
                    Start Shopping
                </Link>
            </section>
        </main>
    </StoreLayout>
</template>