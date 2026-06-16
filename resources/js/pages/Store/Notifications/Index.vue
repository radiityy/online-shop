<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';

type OrderNotification = {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    order?: {
        id: number;
        order_code: string;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedNotifications = {
    data: OrderNotification[];
    links: PaginationLink[];
};

const props = defineProps<{
    notifications: PaginatedNotifications;
    unreadCount: number;
}>();

const formatDate = (date: string) => {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
};

const openNotification = (notification: OrderNotification) => {
    router.post(`/notifications/${notification.id}/read`);
};

const markAllAsRead = () => {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
    });
};

const notificationTone = (type: string) => {
    if (['payment_failed', 'order_cancelled'].includes(type)) {
        return 'border-red-200 bg-red-50';
    }

    if (['payment_confirmed', 'order_delivered'].includes(type)) {
        return 'border-green-200 bg-green-50';
    }

    if (['order_shipped', 'order_processing', 'order_packed'].includes(type)) {
        return 'border-blue-200 bg-blue-50';
    }

    return 'border-neutral-200 bg-white';
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1200px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                        Notifications
                    </p>

                    <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.05em] md:text-7xl">
                        Order Updates
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                        Follow payment, packing, shipment, and delivery updates for your NEVERENDING orders.
                    </p>
                </div>

                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="w-fit bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                    @click="markAllAsRead"
                >
                    Mark All As Read
                </button>
            </section>

            <section
                v-if="notifications.data.length"
                class="space-y-4"
            >
                <article
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="cursor-pointer border p-5 transition hover:border-neutral-950"
                    :class="[
                        notification.is_read ? 'border-neutral-200 bg-white' : notificationTone(notification.type),
                    ]"
                    @click="openNotification(notification)"
                >
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    v-if="!notification.is_read"
                                    class="inline-flex bg-neutral-950 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white"
                                >
                                    New
                                </span>

                                <span class="text-[11px] font-black uppercase tracking-[0.2em] text-neutral-400">
                                    {{ notification.type.replaceAll('_', ' ') }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-xl font-black uppercase tracking-[-0.03em]">
                                {{ notification.title }}
                            </h2>

                            <p class="mt-3 text-sm leading-7 text-neutral-600">
                                {{ notification.message }}
                            </p>

                            <p
                                v-if="notification.order"
                                class="mt-3 text-xs font-black uppercase tracking-[0.18em] text-neutral-500"
                            >
                                Order {{ notification.order.order_code }}
                            </p>
                        </div>

                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-neutral-400 md:text-right">
                            {{ formatDate(notification.created_at) }}
                        </p>
                    </div>
                </article>
            </section>

            <section
                v-else
                class="border border-dashed border-neutral-300 px-6 py-20 text-center"
            >
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400">
                    No Notifications
                </p>

                <h2 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">
                    You're all caught up.
                </h2>

                <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-neutral-500">
                    Order updates will appear here once there is a change in your payment, packing, shipment, or delivery status.
                </p>

                <Link
                    href="/orders"
                    class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                >
                    View Orders
                </Link>
            </section>

            <nav
                v-if="notifications.links.length > 3"
                class="mt-10 flex flex-wrap gap-2"
            >
                <Link
                    v-for="link in notifications.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="border px-4 py-3 text-xs font-black uppercase tracking-[0.16em]"
                    :class="[
                        link.active ? 'border-neutral-950 bg-neutral-950 text-white' : 'border-neutral-200 bg-white text-neutral-600',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </nav>
        </main>
    </StoreLayout>
</template>