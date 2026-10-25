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
            <section class="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">Notifications</p>

                    <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.05em] md:text-7xl">Order Updates</h1>

                    <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                        Follow payment, refund, packing, shipment, and delivery updates for your NEVERENDING orders.
                    </p>
                </div>

                <button
                    v-if="props.unreadCount > 0"
                    type="button"
                    class="w-fit bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                    @click="markAllAsRead"
                >
                    Mark All As Read
                </button>
            </section>

            <section v-if="notifications.data.length" class="mx-auto max-w-3xl space-y-5">
                <article
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="border bg-white p-5 sm:p-6"
                    :class="notification.is_read ? 'border-neutral-200' : 'border-neutral-950'"
                >
                    <div class="flex flex-col justify-between gap-1 sm:flex-row sm:items-start sm:gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                v-if="!notification.is_read"
                                class="inline-flex bg-neutral-950 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-white"
                            >
                                New
                            </span>

                            <p
                                class="text-sm font-black uppercase tracking-[0.12em]"
                                :class="isNegative(notification.type) ? 'text-red-600' : 'text-neutral-950'"
                            >
                                Status: {{ notification.title }}
                            </p>
                        </div>

                        <p class="shrink-0 text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-400">
                            {{ formatDateTime(notification.created_at) }}
                        </p>
                    </div>

                    <p class="mt-3 text-sm font-bold leading-7 text-neutral-950">
                        {{ notification.message }}
                    </p>

                    <template v-if="notification.order">
                        <div class="mt-5 flex items-start justify-between gap-4 border-t border-neutral-200 pt-5">
                            <div class="min-w-0">
                                <p
                                    v-if="notification.order.product"
                                    class="text-sm font-black uppercase tracking-[-0.01em]"
                                >
                                    {{ notification.order.product.name }}
                                </p>

                                <p
                                    v-if="notification.order.product?.size"
                                    class="mt-1 text-xs font-bold uppercase tracking-[0.16em] text-neutral-500"
                                >
                                    Size {{ notification.order.product.size }}
                                </p>

                                <p
                                    v-if="notification.order.extra_items > 0"
                                    class="mt-1 text-xs font-bold uppercase tracking-[0.16em] text-neutral-400"
                                >
                                    +{{ notification.order.extra_items }} more
                                    {{ notification.order.extra_items === 1 ? 'item' : 'items' }}
                                </p>

                                <p class="mt-3 text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                    Order {{ notification.order.order_code }}
                                </p>

                                <p
                                    v-if="notification.order.ordered_at"
                                    class="mt-1 text-xs font-bold uppercase tracking-[0.16em] text-neutral-400"
                                >
                                    Ordered {{ formatDate(notification.order.ordered_at) }}
                                </p>
                            </div>

                            <img
                                v-if="notification.order.product"
                                :src="notification.order.product.image"
                                :alt="notification.order.product.name"
                                class="h-20 w-20 shrink-0 bg-neutral-100 object-cover"
                                loading="lazy"
                            />
                        </div>

                        <button
                            type="button"
                            :disabled="openingId !== null"
                            class="group relative mt-5 flex w-full justify-center overflow-hidden border border-neutral-950 bg-white px-8 py-4 text-xs font-black uppercase tracking-[0.22em] transition-transform active:scale-[0.99] disabled:cursor-wait"
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
                                :class="openingId === notification.id ? 'text-neutral-950' : 'text-white group-hover:text-neutral-950'"
                            >
                                {{ openingId === notification.id ? 'Opening...' : 'View Order' }}
                            </span>
                        </button>
                    </template>

                    <button
                        v-else-if="!notification.is_read"
                        type="button"
                        class="mt-5 text-xs font-black uppercase tracking-[0.2em] text-neutral-500 underline underline-offset-8 transition hover:text-neutral-950"
                        @click="openNotification(notification)"
                    >
                        Mark as read
                    </button>
                </article>
            </section>

            <section v-else class="border border-dashed border-neutral-300 px-6 py-20 text-center">
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