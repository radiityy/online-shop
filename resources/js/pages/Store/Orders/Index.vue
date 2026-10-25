<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { computed, ref, watch } from 'vue';

type OrderProduct = {
    name: string;
    size: string | null;
    price: number;
    quantity: number;
    image: string;
};

type OrderSummary = {
    id: number;
    order_code: string;
    created_at: string | null;
    total: number;
    items_count: number;
    status: string;
    status_label: string;
    product: OrderProduct | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedOrders = {
    data: OrderSummary[];
    links: PaginationLink[];
    total: number;
};

type StatusOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    orders: PaginatedOrders;
    status: string;
    statusOptions: StatusOption[];
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

const formatDateTime = (date: string | null) => (date ? `${dateTimeFormat.format(new Date(date))} WIB` : '');

const formatPrice = (price: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);

const negativeStatuses = ['cancelled', 'refund', 'returned'];

const isNegative = (status: string) => negativeStatuses.includes(status);

const sizeLabel = (size: string | null) => (size && size !== 'Default' ? size : null);

const selectedStatus = ref(props.status);
const isFiltering = ref(false);

watch(
    () => props.status,
    (value) => {
        selectedStatus.value = value;
    },
);

const isFiltered = computed(() => props.status !== 'all');

const applyStatus = (value: string) => {
    selectedStatus.value = value;

    router.get(
        '/orders',
        { status: value === 'all' ? undefined : value },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => {
                isFiltering.value = true;
            },
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
};

const onStatusChange = (event: Event) => {
    applyStatus((event.target as HTMLSelectElement).value);
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1200px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mx-auto mb-10 max-w-4xl">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">Order History</p>

                <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.05em] md:text-7xl">My Orders</h1>

                <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Track payment, packing, shipment, and delivery for your NEVERENDING orders.
                </p>
            </section>

            <section class="mx-auto max-w-4xl">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-neutral-500">
                        {{ orders.total }} {{ orders.total === 1 ? 'Order' : 'Orders' }}
                    </p>

                    <select
                        :value="selectedStatus"
                        aria-label="Filter orders by status"
                        class="rounded-none border border-neutral-300 bg-white px-4 py-3 text-sm outline-none transition duration-200 focus:border-neutral-950 focus:ring-1 focus:ring-neutral-950 sm:w-56"
                        @change="onStatusChange"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div v-if="orders.data.length" class="space-y-4 transition-opacity" :class="isFiltering ? 'opacity-60' : ''">
                    <Link
                        v-for="order in orders.data"
                        :key="order.id"
                        :href="`/orders/${order.order_code}`"
                        class="block border border-neutral-200 bg-white p-5 transition hover:border-neutral-950 sm:p-6"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate text-lg font-black uppercase tracking-[-0.03em] sm:text-xl">
                                    {{ order.order_code }}
                                </p>

                                <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.16em] text-neutral-400">
                                    {{ formatDateTime(order.created_at) }}
                                </p>
                            </div>

                            <p
                                class="shrink-0 text-xs font-black uppercase tracking-[0.18em]"
                                :class="isNegative(order.status) ? 'text-red-600' : 'text-neutral-950'"
                            >
                                {{ order.status_label }}
                            </p>
                        </div>

                        <div class="mt-5 flex gap-4">
                            <img
                                v-if="order.product"
                                :src="order.product.image"
                                :alt="order.product.name"
                                class="h-20 w-20 shrink-0 bg-neutral-200 object-cover sm:h-24 sm:w-24"
                                loading="lazy"
                            />

                            <div v-else class="h-20 w-20 shrink-0 bg-neutral-200 sm:h-24 sm:w-24" />

                            <div v-if="order.product" class="flex min-w-0 flex-1 flex-col justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-black uppercase tracking-[0.1em] text-neutral-950">
                                        {{ order.product.name }}
                                    </p>

                                    <p
                                        v-if="sizeLabel(order.product.size)"
                                        class="mt-1 text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-500"
                                    >
                                        Size {{ sizeLabel(order.product.size) }}
                                    </p>
                                </div>

                                <div class="flex items-baseline justify-between gap-4 text-sm">
                                    <p class="font-bold text-neutral-950">{{ formatPrice(order.product.price) }}</p>

                                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-neutral-500">
                                        x{{ order.product.quantity }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-neutral-200 pt-4 text-sm">
                            <span class="text-neutral-500">
                                {{ order.items_count }} {{ order.items_count === 1 ? 'item' : 'items' }}:
                            </span>

                            <span class="font-black text-neutral-950">{{ formatPrice(order.total) }}</span>
                        </div>
                    </Link>

                    <nav v-if="orders.links.length > 3" class="flex flex-wrap justify-center gap-2 pt-6">
                        <Link
                            v-for="link in orders.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            preserve-scroll
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
                </div>

                <div v-else-if="isFiltered" class="border border-dashed border-neutral-300 px-6 py-16 text-center">
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400">No Results</p>

                    <h2 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">No orders with this status</h2>

                    <button
                        type="button"
                        class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                        @click="applyStatus('all')"
                    >
                        Show All Orders
                    </button>
                </div>

                <div v-else class="border border-dashed border-neutral-300 px-6 py-20 text-center">
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400">No Orders</p>

                    <h2 class="mt-4 text-3xl font-black uppercase tracking-[-0.04em]">You have no orders yet</h2>

                    <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-neutral-500">
                        Start your endless rotation by placing your first NEVERENDING order.
                    </p>

                    <Link
                        href="/products"
                        class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                    >
                        Start Shopping
                    </Link>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>