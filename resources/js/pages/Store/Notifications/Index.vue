<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { ref } from 'vue';

type NotificationOrder = {
    id: number;
    order_code: string;
    ordered_at: string | null;
    extra_items: number;
    product: {
        name: string;
        size: string | null;
        image: string;
    } | null;
};

type OrderNotification = {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string | null;
    order: NotificationOrder | null;
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

const TIME_ZONE = 'Asia/Jakarta';

const dateTimeFormat = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: TIME_ZONE,
});

const dateFormat = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: TIME_ZONE,
});

const formatDateTime = (date: string | null) => (date ? `${dateTimeFormat.format(new Date(date))} WIB` : '');

const formatDate = (date: string | null) => (date ? dateFormat.format(new Date(date)) : '');

const negativeTypes = ['payment_failed', 'payment_expired', 'order_cancelled', 'refund_required'];

const isNegative = (type: string) => negativeTypes.includes(type);

const openingId = ref<number | null>(null);

const openNotification = (notification: OrderNotification) => {
    if (openingId.value !== null) {
        return;
    }

    openingId.value = notification.id;

    router.post(
        `/notifications/${notification.id}/read`,
        {},
        {
            onFinish: () => {
                openingId.value = null;
            },
        },
    );
};

const markAllAsRead = () => {
    router.post(
        '/notifications/read-all',
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1200px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mx-auto mb-10 max-w-4xl">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">Notifications</p>

                <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.05em] md:text-7xl">Order Updates</h1>

                <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Follow payment, refund, packing, shipment, and delivery updates for your NEVERENDING orders.
                </p>
            </section>

            <section v-if="notifications.data.length" class="mx-auto max-w-4xl border border-neutral-200 bg-white">
                <div class="flex items-center justify-between gap-4 border-b border-neutral-200 px-5 py-4 sm:px-6">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-neutral-500">
                        {{ props.unreadCount > 0 ? `${props.unreadCount} unread` : 'All read' }}
                    </p>

                    <button
                        v-if="props.unreadCount > 0"
                        type="button"
                        class="text-[11px] font-black uppercase tracking-[0.2em] text-neutral-950 underline underline-offset-8 transition hover:text-neutral-500"
                        @click="markAllAsRead"
                    >
                        Mark All As Read
                    </button>
                </div>

                <div class="divide-y divide-neutral-200">
                    <article
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        class="flex flex-col gap-4 border-l-4 p-5 sm:flex-row sm:items-start sm:gap-6 sm:p-6"
                        :class="notification.is_read ? 'border-transparent bg-white' : 'border-neutral-950 bg-neutral-100'"
                    >
                        <div class="flex min-w-0 flex-1 gap-4">
                            <img
                                v-if="notification.order?.product"
                                :src="notification.order.product.image"
                                :alt="notification.order.product.name"
                                class="h-20 w-20 shrink-0 bg-neutral-200 object-cover sm:h-24 sm:w-24"
                                loading="lazy"
                            />

                            <div
                                v-else
                                class="flex h-20 w-20 shrink-0 items-center justify-center bg-neutral-200 text-neutral-400 sm:h-24 sm:w-24"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-8 w-8"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.6"
                                        d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9"
                                    />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-black uppercase tracking-[0.12em]"
                                    :class="isNegative(notification.type) ? 'text-red-600' : 'text-neutral-950'"
                                >
                                    Status: {{ notification.title }}
                                </p>

                                <p class="mt-2 text-sm leading-6 text-neutral-700">
                                    {{ notification.message }}
                                </p>

                                <template v-if="notification.order">
                                    <p
                                        v-if="notification.order.product"
                                        class="mt-3 text-xs font-black uppercase tracking-[0.1em] text-neutral-950"
                                    >
                                        {{ notification.order.product.name }}
                                    </p>

                                    <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-500">
                                        <template v-if="notification.order.product?.size">
                                            Size {{ notification.order.product.size }}
                                            <span class="px-1 text-neutral-300">/</span>
                                        </template>
                                        <template v-if="notification.order.extra_items > 0">
                                            +{{ notification.order.extra_items }} more
                                            {{ notification.order.extra_items === 1 ? 'item' : 'items' }}
                                            <span class="px-1 text-neutral-300">/</span>
                                        </template>
                                        Order {{ notification.order.order_code }}
                                        <template v-if="notification.order.ordered_at">
                                            <span class="px-1 text-neutral-300">/</span>
                                            Ordered {{ formatDate(notification.order.ordered_at) }}
                                        </template>
                                    </p>
                                </template>

                                <p class="mt-3 text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-400">
                                    {{ formatDateTime(notification.created_at) }}
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0 sm:w-44">
                            <button
                                v-if="notification.order"
                                type="button"
                                :disabled="openingId !== null"
                                class="group relative flex w-full justify-center overflow-hidden border border-neutral-950 bg-white px-6 py-3.5 text-xs font-black uppercase tracking-[0.22em] transition-transform active:scale-[0.99] disabled:cursor-wait"
                                @click="openNotification(notification)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="absolute inset-0 bg-neutral-950 transition-all duration-500 ease-out"
                                    :class="
                                        openingId === notification.id
                                            ? 'translate-x-full opacity-0'
                                            : 'translate-x-0 opacity-100 group-hover:translate-x-full group-hover:opacity-0'
                                    "
                                />

                                <span
                                    class="relative transition-colors duration-300"
                                    :class="
                                        openingId === notification.id
                                            ? 'text-neutral-950'
                                            : 'text-white group-hover:text-neutral-950'
                                    "
                                >
                                    {{ openingId === notification.id ? 'Opening...' : 'View Order' }}
                                </span>
                            </button>

                            <button
                                v-else-if="!notification.is_read"
                                type="button"
                                :disabled="openingId !== null"
                                class="text-xs font-black uppercase tracking-[0.2em] text-neutral-500 underline underline-offset-8 transition hover:text-neutral-950"
                                @click="openNotification(notification)"
                            >
                                Mark as read
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section
                v-else
                class="mx-auto max-w-4xl border border-dashed border-neutral-300 px-6 py-20 text-center"
            >
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400">No Notifications</p>

                <h2 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">You're all caught up.</h2>

                <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-neutral-500">
                    Order updates will appear here once there is a change in your payment, refund, packing, shipment, or
                    delivery status.
                </p>

                <Link
                    href="/orders"
                    class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                >
                    View Orders
                </Link>
            </section>

            <nav v-if="notifications.links.length > 3" class="mt-10 flex flex-wrap justify-center gap-2">
                <Link
                    v-for="link in notifications.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="border px-4 py-3 text-xs font-black uppercase tracking-[0.16em]"
                    :class="[
                        link.active
                            ? 'border-neutral-950 bg-neutral-950 text-white'
                            : 'border-neutral-200 bg-white text-neutral-600',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </nav>
        </main>
    </StoreLayout>
</template>