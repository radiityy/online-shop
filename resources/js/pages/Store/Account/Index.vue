<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';

type Account = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

type Stats = {
    total_orders: number;
    pending_payment_orders: number;
    paid_orders: number;
    completed_orders: number;
    total_spent: number;
};

type Address = {
    id: number;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    postal_code: string | null;
    full_address: string;
    is_default: boolean;
};

type RecentOrder = {
    id: number;
    order_code: string;
    total: string;
    payment_status: string;
    order_status: string;
    shipping_status: string;
    created_at: string;
    items_count: number;
    shipment?: {
        courier: string | null;
        service: string | null;
        tracking_number: string | null;
        status: string;
    } | null;
};

defineProps<{
    account: Account;
    stats: Stats;
    defaultAddress: Address | null;
    recentOrders: RecentOrder[];
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
                    Account
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                            My Account
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Manage your NEVERENDING profile, orders, and delivery addresses.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Link
                            href="/orders"
                            class="border border-neutral-950 bg-white px-6 py-3 text-xs font-black uppercase tracking-[0.2em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                        >
                            My Orders
                        </Link>

                        <Link
                            href="/account/addresses"
                            class="bg-neutral-950 px-6 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                        >
                            My Addresses
                        </Link>
                    </div>
                </div>
            </section>

            <section class="grid gap-10 lg:grid-cols-[420px_1fr]">
                <aside class="space-y-6">
                    <section class="border border-neutral-200 bg-neutral-50 p-6">
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                            Profile
                        </p>

                        <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                            {{ account.name }}
                        </h2>

                        <p class="mt-3 text-sm font-medium text-neutral-600">
                            {{ account.email }}
                        </p>

                        <p class="mt-5 text-xs font-bold uppercase tracking-[0.18em] text-neutral-400">
                            Member since {{ formatDate(account.created_at) }}
                        </p>
                    </section>

                    <section class="border border-neutral-200 bg-white p-6">
                        <div class="mb-5 flex items-center justify-between gap-4">
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                Default Address
                            </p>

                            <Link
                                href="/account/addresses"
                                class="text-xs font-black uppercase tracking-[0.18em] underline underline-offset-4"
                            >
                                Manage
                            </Link>
                        </div>

                        <div v-if="defaultAddress" class="text-sm leading-7 text-neutral-600">
                            <p class="font-black uppercase tracking-[0.1em] text-neutral-950">
                                {{ defaultAddress.recipient_name }}
                            </p>

                            <p class="mt-1">
                                {{ defaultAddress.phone }}
                            </p>

                            <p class="mt-4">
                                {{ defaultAddress.full_address }}
                            </p>

                            <p>
                                {{ defaultAddress.district }}, {{ defaultAddress.city }}, {{ defaultAddress.province }}
                                <span v-if="defaultAddress.postal_code">, {{ defaultAddress.postal_code }}</span>
                            </p>
                        </div>

                        <div v-else>
                            <p class="text-sm leading-7 text-neutral-600">
                                You have not added a delivery address yet.
                            </p>

                            <Link
                                href="/account/addresses"
                                class="mt-5 inline-flex bg-neutral-950 px-6 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                            >
                                Add Address
                            </Link>
                        </div>
                    </section>
                </aside>

                <div class="space-y-10">
                    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="border border-neutral-200 bg-white p-6">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                                Total Orders
                            </p>

                            <p class="mt-4 text-4xl font-black tracking-[-0.05em]">
                                {{ stats.total_orders }}
                            </p>
                        </div>

                        <div class="border border-neutral-200 bg-white p-6">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                                Pending Payment
                            </p>

                            <p class="mt-4 text-4xl font-black tracking-[-0.05em]">
                                {{ stats.pending_payment_orders }}
                            </p>
                        </div>

                        <div class="border border-neutral-200 bg-white p-6">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                                Paid Orders
                            </p>

                            <p class="mt-4 text-4xl font-black tracking-[-0.05em]">
                                {{ stats.paid_orders }}
                            </p>
                        </div>

                        <div class="border border-neutral-200 bg-white p-6">
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                                Total Spent
                            </p>

                            <p class="mt-4 text-2xl font-black tracking-[-0.05em]">
                                {{ formatPrice(stats.total_spent) }}
                            </p>
                        </div>
                    </section>

                    <section class="border border-neutral-200 p-6 md:p-8">
                        <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                    Recent Orders
                                </p>

                                <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                                    Latest Activity
                                </h2>
                            </div>

                            <Link
                                href="/orders"
                                class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                            >
                                View All Orders
                            </Link>
                        </div>

                        <div v-if="recentOrders.length" class="space-y-4">
                            <article
                                v-for="order in recentOrders"
                                :key="order.id"
                                class="border border-neutral-200 p-5 transition hover:border-neutral-950"
                            >
                                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-start">
                                    <div>
                                        <Link
                                            :href="`/orders/${order.order_code}`"
                                            class="text-xl font-black uppercase tracking-[-0.03em] hover:underline"
                                        >
                                            {{ order.order_code }}
                                        </Link>

                                        <p class="mt-2 text-sm text-neutral-500">
                                            {{ formatDate(order.created_at) }} · {{ order.items_count }} item(s)
                                        </p>

                                        <div class="mt-4 flex flex-wrap gap-2">
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
                                    </div>

                                    <div class="md:text-right">
                                        <p class="text-xl font-black">
                                            {{ formatPrice(order.total) }}
                                        </p>

                                        <Link
                                            :href="`/orders/${order.order_code}`"
                                            class="mt-4 inline-flex bg-neutral-950 px-5 py-3 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-neutral-800"
                                        >
                                            Detail
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="border border-dashed border-neutral-300 p-10 text-center"
                        >
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                No Orders
                            </p>

                            <h3 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">
                                No order yet
                            </h3>

                            <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-neutral-600">
                                Start your endless rotation by placing your first NEVERENDING order.
                            </p>

                            <Link
                                href="/products"
                                class="mt-7 inline-flex bg-neutral-950 px-7 py-3 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                            >
                                Shop Now
                            </Link>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>