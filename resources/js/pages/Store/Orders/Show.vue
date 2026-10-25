<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import PaymentLogo from '@/components/PaymentLogo.vue';
import PaymentPicker from '@/components/PaymentPicker.vue';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

type TrackingHistory = {
    date?: string;
    desc?: string;
    location?: string;
};

type EstimateItem = {
    label: string;
    value: string;
};

type PaymentRecord = {
    id: number;
    payment_method: string;
    payment_type: string | null;
    bank: string | null;
    gateway: string | null;
    gateway_status: string | null;
    va_number: string | null;
    qr_url: string | null;
    deeplink_url: string | null;
    amount: string;
    fee: string | null;
    status: string;
    expires_at: string | null;
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
    shipping_insurance_fee: string | null;
    payment_fee: string | null;
    shipping_courier_name: string | null;
    shipping_service_name: string | null;
    shipping_duration: string | null;
    total: string;
    payment_expires_at: string | null;
    payment_status: string;
    order_status: string;
    shipping_status: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
    items: OrderItem[];
    payment?: PaymentRecord | null;
    shipment?: {
        courier: string | null;
        courier_code: string | null;
        service: string | null;
        tracking_number: string | null;
        status: string;
        tracking_status: string | null;
        tracking_history: TrackingHistory[] | null;
        last_tracked_at: string | null;
        estimated_packed_at: string | null;
        estimated_shipped_at: string | null;
        estimated_delivered_from: string | null;
        estimated_delivered_to: string | null;
    } | null;
};

type PaymentMethod = {
    key: string;
    label: string;
    payment_type: string;
    bank: string | null;
    group: 'virtual_account' | 'other';
    fee_flat: number;
    fee_percent: number;
    fee: number;
    amount: number;
};

type Channel = {
    title: string;
    steps: string[];
};

const props = defineProps<{
    order: Order;
    paymentMethods: PaymentMethod[];
    customerEmail: string | null;
    canReorder: boolean;
    serverNow: string;
}>();

/* ------------------------------------------------------------------ */
/* Format                                                              */
/* ------------------------------------------------------------------ */

const TIME_ZONE = 'Asia/Jakarta';

// Tujuan tautan bantuan. Halaman /contact dan /terms belum ada, ganti di sini kalau alamatnya lain.
const HELP_LINKS = {
    contact: '/contact',
    faq: '/faq',
    terms: '/terms',
};

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

const formatDate = (date: string) => `${dateTimeFormat.format(new Date(date))} WIB`;
const formatDateOnly = (date: string) => dateFormat.format(new Date(date));

const formatPrice = (price: string | number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(price));

const formatVa = (value: string) => value.replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim();

const storageUrl = (path: string | null | undefined) => (path ? `/storage/${path}` : '/placeholder-product.jpg');

const sizeLabel = (item: OrderItem) => {
    if (item.variant_name) {
        return item.variant_name.split('/')[0]?.trim() || item.variant_name;
    }

    return item.sku ?? 'Default';
};

/* ------------------------------------------------------------------ */
/* Waktu bayar                                                         */
/* ------------------------------------------------------------------ */

const clockOffset = new Date(props.serverNow).getTime() - Date.now();
const now = ref(Date.now() + clockOffset);

const expiresAtMs = computed(() =>
    props.order.payment_expires_at ? new Date(props.order.payment_expires_at).getTime() : null,
);

const remainingSeconds = computed(() => {
    if (expiresAtMs.value === null) return 0;

    return Math.max(0, Math.floor((expiresAtMs.value - now.value) / 1000));
});

const windowSeconds = computed(() => {
    if (expiresAtMs.value === null) return 1;

    return Math.max(1, Math.floor((expiresAtMs.value - new Date(props.order.created_at).getTime()) / 1000));
});

const remainingPercent = computed(() => Math.min(100, (remainingSeconds.value / windowSeconds.value) * 100));

const countdown = computed(() => {
    const total = remainingSeconds.value;
    const pad = (n: number) => String(n).padStart(2, '0');
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    return hours > 0 ? `${pad(hours)}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`;
});

const urgent = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 600);
const critical = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 60);

// Server menolak metode baru bila sisa waktu <= 2 menit, jadi tombolnya ikut disembunyikan.
const canChangeMethod = computed(() => remainingSeconds.value > 125);

/* ------------------------------------------------------------------ */
/* Status tampilan                                                     */
/* ------------------------------------------------------------------ */

const gatewayPayment = computed(() => (props.order.payment?.gateway ? props.order.payment : null));

const activePayment = computed(() =>
    gatewayPayment.value && gatewayPayment.value.status === 'pending' ? gatewayPayment.value : null,
);

const vaNumber = computed(() => activePayment.value?.va_number ?? null);

const isPaid = computed(() => props.order.payment_status === 'paid');

const closedInfo = computed<{ title: string; description: string } | null>(() => {
    const order = props.order;

    if (order.payment_status === 'failed') {
        return {
            title: 'Payment failed',
            description: 'We could not verify the payment for this order, so it will not be processed. You can place a new order anytime.',
        };
    }

    if (order.payment_status === 'expired') {
        return {
            title: 'Payment expired',
            description: 'The payment time ran out, so this order was closed and your items were released. You can place a new order anytime.',
        };
    }

    if (order.payment_status === 'refunded') {
        return {
            title: 'Payment refunded',
            description: 'Your payment has been refunded. Please contact us if you need more information.',
        };
    }

    if (order.order_status === 'cancelled') {
        return {
            title: 'Order cancelled',
            description: 'This order was cancelled and will not be processed. Your items were released, so you can place a new order if you still want them.',
        };
    }

    if (order.shipping_status === 'returned') {
        return {
            title: 'Order returned',
            description: 'This order was returned during delivery. Please contact us for more information.',
        };
    }

    return null;
});

const closedCopy = computed(
    () =>
        closedInfo.value ?? {
            title: 'Order update',
            description: 'This order is no longer waiting for payment.',
        },
);

const mode = computed<'awaiting' | 'closing' | 'paid' | 'closed'>(() => {
    if (closedInfo.value) return 'closed';
    if (isPaid.value) return 'paid';

    if (props.order.payment_expires_at && props.order.payment_status === 'pending') {
        return remainingSeconds.value > 0 ? 'awaiting' : 'closing';
    }

    return 'closed';
});

const amountDue = computed(() => (activePayment.value ? Number(activePayment.value.amount) : Number(props.order.total)));

const activeMethodLabel = computed(() => {
    const key = activePayment.value?.payment_method;

    return props.paymentMethods.find((method) => method.key === key)?.label ?? key ?? '';
});

const canCancel = computed(() => mode.value === 'awaiting' && props.order.shipping_status === 'not_shipped');

const showStickyBar = computed(() => mode.value === 'awaiting' && activePayment.value !== null);

/* ------------------------------------------------------------------ */
/* Progres order                                                       */
/* ------------------------------------------------------------------ */

const stages = computed(() => {
    const order = props.order;
    const paid = order.payment_status === 'paid';

    const packed =
        paid
        && (['packed', 'shipped', 'delivered', 'completed'].includes(order.order_status)
            || ['packed', 'shipped', 'delivered'].includes(order.shipping_status));

    const shipped = paid && ['shipped', 'delivered', 'completed'].includes(order.shipping_status);

    const delivered =
        paid
        && (['delivered', 'completed'].includes(order.shipping_status)
            || ['delivered', 'completed'].includes(order.order_status));

    return [
        { label: 'Placed', done: true },
        { label: 'Paid', done: paid },
        { label: 'Packed', done: packed || shipped || delivered },
        { label: 'Shipped', done: shipped || delivered },
        { label: 'Delivered', done: delivered },
    ];
});

const currentStageIndex = computed(() => stages.value.findIndex((stage) => !stage.done));

type MarkerState = 'done' | 'current' | 'failed' | 'idle';

const markerState = (index: number): MarkerState => {
    if (mode.value === 'closed') {
        if (index === 0) return 'done';
        if (index === 1) return 'failed';

        return 'idle';
    }

    if (stages.value[index]?.done) return 'done';
    if (index === currentStageIndex.value) return 'current';

    return 'idle';
};

const markerClass = (index: number) => {
    const state = markerState(index);

    if (state === 'done') return 'border-neutral-950 bg-neutral-950 text-white';
    if (state === 'failed') return 'border-red-600 bg-red-600 text-white';
    if (state === 'current') return 'border-neutral-950 bg-white text-neutral-950';

    return 'border-neutral-300 bg-white text-neutral-400';
};

const stageLabelClass = (index: number) => {
    const state = markerState(index);

    if (state === 'failed') return 'text-red-600';
    if (state === 'idle') return 'text-neutral-400';

    return 'text-neutral-950';
};

const paidHeadline = computed(() => {
    const [, , packed, shipped, delivered] = stages.value;

    if (delivered?.done) return 'Delivered';
    if (shipped?.done) return 'On its way';
    if (packed?.done) return 'Packed';

    return 'Payment received';
});

const paidMessage = computed(() => {
    const [, , packed, shipped, delivered] = stages.value;

    if (delivered?.done) return 'Your order has arrived. Enjoy your NEVERENDING piece.';
    if (shipped?.done) return 'Your order is with the courier. Follow its progress below.';
    if (packed?.done) return 'Your order is packed and waiting for the courier to pick it up.';

    return 'Thank you. We are preparing your order now.';
});

/* ------------------------------------------------------------------ */
/* Pengiriman                                                          */
/* ------------------------------------------------------------------ */

const insuranceFee = computed(() => Number(props.order.shipping_insurance_fee ?? 0));
const paymentFee = computed(() => Number(props.order.payment_fee ?? 0));

const shippingMethod = computed(() => {
    const name = props.order.shipping_courier_name;

    if (!name) return null;

    const label = [name, props.order.shipping_service_name].filter(Boolean).join(' ');

    return props.order.shipping_duration ? `${label} (${props.order.shipping_duration})` : label;
});

const courierLabel = computed(
    () => props.order.shipment?.courier || props.order.shipment?.courier_code?.toUpperCase() || '-',
);

const estimateItems = computed<EstimateItem[]>(() => {
    const shipment = props.order.shipment;

    if (!shipment) return [];

    const items: EstimateItem[] = [];

    if (shipment.estimated_packed_at) {
        items.push({ label: 'Packed by', value: formatDate(shipment.estimated_packed_at) });
    }

    if (shipment.estimated_shipped_at) {
        items.push({ label: 'Shipped by', value: formatDate(shipment.estimated_shipped_at) });
    }

    if (shipment.estimated_delivered_from && shipment.estimated_delivered_to) {
        items.push({
            label: 'Arrives',
            value: `${formatDateOnly(shipment.estimated_delivered_from)} to ${formatDateOnly(shipment.estimated_delivered_to)}`,
        });
    } else if (shipment.estimated_delivered_from || shipment.estimated_delivered_to) {
        items.push({
            label: 'Arrives',
            value: formatDateOnly((shipment.estimated_delivered_from || shipment.estimated_delivered_to) as string),
        });
    }

    return items;
});

/* ------------------------------------------------------------------ */
/* Petunjuk bayar                                                      */
/* ------------------------------------------------------------------ */

const bankGuides: Record<string, Channel[]> = {
    bca_va: [
        {
            title: 'ATM',
            steps: [
                'Insert your card and enter your PIN.',
                'Choose Other Transactions, then Transfer, then To BCA Virtual Account.',
                'Enter the virtual account number {va} and check the amount.',
                'Confirm the payment and keep the receipt.',
            ],
        },
        {
            title: 'Mobile banking',
            steps: [
                'Open the BCA mobile app and log in.',
                'Choose m-Transfer, then BCA Virtual Account.',
                'Enter {va} and tap Send.',
                'Check the amount and enter your m-BCA PIN.',
            ],
        },
        {
            title: 'Internet banking',
            steps: [
                'Log in to KlikBCA Individual.',
                'Choose Fund Transfer, then Transfer to BCA Virtual Account.',
                'Enter {va}, check the amount and continue.',
                'Authorize with your KeyBCA response code.',
            ],
        },
    ],
    bni_va: [
        {
            title: 'ATM',
            steps: [
                'Insert your card and enter your PIN.',
                'Choose Other Menu, then Transfer, then Savings Account, then To BNI Account.',
                'Enter the virtual account number {va} and check the amount.',
                'Confirm the payment and keep the receipt.',
            ],
        },
        {
            title: 'Mobile banking',
            steps: [
                'Open BNI Mobile Banking and log in.',
                'Choose Transfer, then Virtual Account Billing.',
                'Enter {va} and continue.',
                'Check the amount and confirm with your password.',
            ],
        },
        {
            title: 'Internet banking',
            steps: [
                'Log in to BNI Internet Banking.',
                'Choose Transactions, then Virtual Account Billing.',
                'Enter {va} and check the amount.',
                'Confirm the payment.',
            ],
        },
    ],
    bri_va: [
        {
            title: 'ATM',
            steps: [
                'Insert your card and enter your PIN.',
                'Choose Other Menu, then Payment, then Others, then BRIVA.',
                'Enter the BRIVA number {va} and check the amount.',
                'Confirm the payment and keep the receipt.',
            ],
        },
        {
            title: 'Mobile banking',
            steps: [
                'Open BRImo and log in.',
                'Choose Payment, then BRIVA.',
                'Enter {va} and continue.',
                'Check the amount and enter your PIN.',
            ],
        },
        {
            title: 'Internet banking',
            steps: [
                'Log in to BRI Internet Banking.',
                'Choose Payment, then BRIVA.',
                'Enter {va} and send.',
                'Confirm with your password and mToken.',
            ],
        },
    ],
    permata_va: [
        {
            title: 'ATM',
            steps: [
                'Insert your card and enter your PIN.',
                'Choose Other Transactions, then Payments, then Other Payments, then Virtual Account.',
                'Enter the virtual account number {va} and check the amount.',
                'Confirm the payment and keep the receipt.',
            ],
        },
        {
            title: 'Mobile banking',
            steps: [
                'Open PermataMobile X and log in.',
                'Choose Pay, then Pay Bills, then Virtual Account.',
                'Enter {va} and check the amount.',
                'Confirm with your PIN or OTP.',
            ],
        },
        {
            title: 'Internet banking',
            steps: [
                'Log in to PermataNet.',
                'Choose Bill Payment, then Virtual Account.',
                'Enter {va} and check the amount.',
                'Confirm with your SMS token.',
            ],
        },
    ],
};

const walletGuides: Record<string, Channel[]> = {
    qris: [
        {
            title: 'Pay with QRIS',
            steps: [
                'Open any e-wallet or mobile banking app that supports QRIS, such as GoPay, OVO, DANA, ShopeePay or your bank app.',
                'Choose Scan or Pay and scan the QR code above. On a phone, tap Save QR and pick the image from your gallery inside the app.',
                'Check that the amount matches the amount due, then confirm.',
            ],
        },
    ],
    gopay: [
        {
            title: 'Pay with GoPay',
            steps: [
                'On your phone, tap Open GoPay. On a computer, scan the QR code with the GoPay app.',
                'Check the amount and confirm with your GoPay PIN.',
            ],
        },
    ],
};

const genericVaGuide: Channel[] = [
    {
        title: 'Bank transfer',
        steps: [
            'Open your bank ATM, mobile banking or internet banking.',
            'Choose Virtual Account or Bill Payment.',
            'Enter {va} and check that the amount matches.',
            'Confirm the payment.',
        ],
    },
];

const guides = computed<Channel[]>(() => {
    const payment = activePayment.value;

    if (!payment) return [];

    return bankGuides[payment.payment_method]
        ?? walletGuides[payment.payment_method]
        ?? (payment.va_number ? genericVaGuide : []);
});

const guideStep = (step: string) => step.replaceAll('{va}', vaNumber.value ? formatVa(vaNumber.value) : 'your virtual account number');

const openGuide = ref(0);

/* ------------------------------------------------------------------ */
/* Aksi                                                                */
/* ------------------------------------------------------------------ */

const copiedKey = ref<string | null>(null);
let copiedTimer: number | undefined;

const copy = async (key: string, value: string) => {
    try {
        await navigator.clipboard.writeText(value);
        copiedKey.value = key;
        window.clearTimeout(copiedTimer);
        copiedTimer = window.setTimeout(() => {
            copiedKey.value = null;
        }, 2000);
    } catch {
        copiedKey.value = null;
    }
};

const savingQr = ref(false);

const saveQr = async () => {
    const url = activePayment.value?.qr_url;

    if (!url) return;

    savingQr.value = true;

    try {
        const response = await fetch(url, { mode: 'cors' });

        if (!response.ok) throw new Error('QR download failed');

        const blob = await response.blob();
        const href = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = href;
        link.download = `${props.order.order_code}-qr.png`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(href);
    } catch {
        window.open(url, '_blank', 'noopener');
    } finally {
        savingQr.value = false;
    }
};

const refreshing = ref(false);
const notReceivedUntil = ref(0);

const refreshStatus = () => {
    router.post(
        `/orders/${props.order.order_code}/payment/refresh`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                refreshing.value = true;
            },
            onSuccess: () => {
                if (props.order.payment_status === 'pending') {
                    notReceivedUntil.value = Date.now() + 8000;
                }
            },
            onFinish: () => {
                refreshing.value = false;
            },
        },
    );
};

const showNotReceived = computed(() => notReceivedUntil.value > now.value && mode.value === 'awaiting');

const reloadOrder = () => {
    router.reload({ only: ['order'] });
};

// Polling otomatis tanpa pesan flash (silent), jaring pengaman bila webhook terlambat.
const silentRefresh = () => {
    if (!activePayment.value) return;

    router.post(
        `/orders/${props.order.order_code}/payment/refresh`,
        { silent: true },
        { preserveScroll: true, preserveState: true, only: ['order'] },
    );
};

/* Pemilih metode bayar (komponen yang sama dengan checkout) */

const showMethodModal = ref(false);
const payForm = useForm<{ method: string }>({ method: '' });

const methodFees = computed<Record<string, number>>(() =>
    Object.fromEntries(props.paymentMethods.map((method) => [method.key, method.fee])),
);

const openMethodModal = () => {
    payForm.clearErrors();
    showMethodModal.value = true;
};

const chooseMethod = (key: string) => {
    if (key === activePayment.value?.payment_method) return;

    payForm.method = key;
    payForm.post(`/orders/${props.order.order_code}/pay`, {
        preserveScroll: true,
        onSuccess: () => {
            openGuide.value = 0;
        },
    });
};

/* Modal batalkan order */

const showCancelModal = ref(false);
const cancelForm = useForm({});
const reorderForm = useForm({});

const reorderToBag = () => {
    reorderForm.post(`/orders/${props.order.order_code}/reorder`);
};

const confirmCancel = () => {
    cancelForm.post(`/orders/${props.order.order_code}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
};

/* ------------------------------------------------------------------ */
/* Siklus hidup                                                        */
/* ------------------------------------------------------------------ */

const justPaid = ref(false);

watch(
    () => props.order.payment_status,
    (value, previous) => {
        if (value === 'paid' && previous === 'pending') {
            justPaid.value = true;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    },
);

watch([showMethodModal, showCancelModal], ([method, cancel]) => {
    document.body.style.overflow = method || cancel ? 'hidden' : '';
});

let timer: number | undefined;
let tick = 0;

const onVisible = () => {
    if (document.visibilityState !== 'visible') return;
    if (mode.value !== 'awaiting' && mode.value !== 'closing') return;

    if (activePayment.value) {
        silentRefresh();
    } else {
        reloadOrder();
    }
};

onMounted(() => {
    timer = window.setInterval(() => {
        now.value = Date.now() + clockOffset;
        tick += 1;

        if (document.hidden) return;
        if (mode.value !== 'awaiting' && mode.value !== 'closing') return;

        if (tick % 6 === 0) reloadOrder();
        if (tick % 30 === 15) silentRefresh();
    }, 1000);

    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    if (timer) window.clearInterval(timer);

    window.clearTimeout(copiedTimer);
    document.removeEventListener('visibilitychange', onVisible);
    document.body.style.overflow = '';
});
</script>

<template>
    <StoreLayout>
        <main
            class="mx-auto max-w-[1840px] px-6 pt-32 sm:px-10 lg:px-[4.5vw]"
            :class="showStickyBar ? 'pb-40 lg:pb-20' : 'pb-20'"
        >
            <section class="mb-8 md:mb-10">
                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                            Your order
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                            <h1 class="text-3xl font-black uppercase tracking-[-0.045em] sm:text-4xl md:text-5xl">
                                {{ order.order_code }}
                            </h1>

                            <button
                                type="button"
                                class="border border-neutral-300 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] transition hover:border-neutral-950"
                                @click="copy('code', order.order_code)"
                            >
                                {{ copiedKey === 'code' ? 'Copied' : 'Copy' }}
                            </button>
                        </div>

                        <p class="mt-3 text-sm text-neutral-500">
                            Placed on {{ formatDate(order.created_at) }}
                        </p>
                    </div>

                    <Link
                        href="/orders"
                        class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                    >
                        Back to orders
                    </Link>
                </div>
            </section>

            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-10">
                <!-- ============================ KOLOM UTAMA ============================ -->
                <div class="min-w-0 space-y-6">
                    <!-- Pita status -->
                    <section
                        v-if="mode === 'awaiting'"
                        class="border-2 border-neutral-950 p-5 transition-colors duration-500 md:p-7"
                        :class="urgent ? 'bg-white text-neutral-950' : 'bg-neutral-950 text-white'"
                    >
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p
                                    class="text-xs font-black uppercase tracking-[0.2em]"
                                    :class="urgent ? 'text-neutral-600' : 'text-neutral-400'"
                                >
                                    {{ urgent ? 'Less than 10 minutes left' : 'Pay within' }}
                                </p>

                                <p
                                    class="mt-2 text-5xl font-black tabular-nums tracking-[-0.05em] md:text-7xl"
                                    :class="critical ? 'animate-pulse' : ''"
                                >
                                    {{ countdown }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p
                                    class="text-xs font-black uppercase tracking-[0.2em]"
                                    :class="urgent ? 'text-neutral-600' : 'text-neutral-400'"
                                >
                                    {{ activePayment ? 'Amount due' : 'Order total' }}
                                </p>

                                <p class="mt-2 text-2xl font-black md:text-4xl">
                                    {{ formatPrice(amountDue) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 h-1 w-full" :class="urgent ? 'bg-neutral-200' : 'bg-neutral-800'">
                            <div
                                class="h-full transition-[width] duration-1000 ease-linear"
                                :class="urgent ? 'bg-neutral-950' : 'bg-white'"
                                :style="{ width: `${remainingPercent}%` }"
                            ></div>
                        </div>

                        <p
                            v-if="order.payment_expires_at"
                            class="mt-3 text-sm"
                            :class="urgent ? 'text-neutral-600' : 'text-neutral-400'"
                        >
                            Please pay before {{ formatDate(order.payment_expires_at) }}.
                        </p>
                    </section>

                    <section
                        v-else-if="mode === 'paid'"
                        class="border-2 border-neutral-950 bg-neutral-950 p-5 text-white md:p-7"
                    >
                        <div class="flex items-center gap-4 md:gap-5">
                            <span
                                class="flex h-14 w-14 shrink-0 items-center justify-center bg-white text-neutral-950 md:h-16 md:w-16"
                                :class="justPaid ? 'paid-pop' : ''"
                            >
                                <svg viewBox="0 0 16 16" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                    <path d="M3 8.5l3.2 3L13 4.5" stroke-linecap="square" />
                                </svg>
                            </span>

                            <div>
                                <p class="text-3xl font-black uppercase tracking-[-0.04em] md:text-5xl">
                                    {{ paidHeadline }}
                                </p>

                                <p class="mt-2 text-sm leading-6 text-neutral-300">
                                    {{ paidMessage }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section v-else class="border-2 border-neutral-950 p-5 md:p-7">
                        <h2 class="text-3xl font-black uppercase tracking-[-0.04em] md:text-5xl">
                            {{ mode === 'closing' ? 'Time is up' : closedCopy.title }}
                        </h2>

                        <p class="mt-3 max-w-xl text-sm leading-7 text-neutral-600">
                            {{
                                mode === 'closing'
                                    ? 'The payment time has ended. We are closing this order, and this page will update on its own.'
                                    : closedCopy.description
                            }}
                        </p>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                            <button
                                v-if="canReorder"
                                type="button"
                                :disabled="reorderForm.processing"
                                class="flex justify-center bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:opacity-50"
                                @click="reorderToBag"
                            >
                                {{ reorderForm.processing ? 'Adding...' : 'Add items back to bag' }}
                            </button>

                            <Link
                                href="/products"
                                class="flex justify-center px-8 py-4 text-xs font-black uppercase tracking-[0.22em] transition"
                                :class="canReorder
                                    ? 'border border-neutral-950 bg-white text-neutral-950 hover:bg-neutral-950 hover:text-white'
                                    : 'bg-neutral-950 text-white hover:bg-neutral-800'"
                            >
                                Continue shopping
                            </Link>

                            <Link
                                href="/orders"
                                class="px-2 py-2 text-center text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                            >
                                My orders
                            </Link>
                        </div>
                    </section>

                    <!-- Strip progres -->
                    <section class="border border-neutral-200 px-4 py-6 sm:px-8">
                        <ol class="grid grid-cols-5">
                            <li
                                v-for="(stage, index) in stages"
                                :key="stage.label"
                                class="relative flex flex-col items-center text-center"
                            >
                                <span
                                    v-if="index > 0"
                                    class="absolute right-1/2 top-4 h-0.5 w-full -translate-y-1/2"
                                    :class="stage.done && mode !== 'closed' ? 'bg-neutral-950' : 'bg-neutral-200'"
                                ></span>

                                <span
                                    class="relative z-10 flex h-8 w-8 items-center justify-center border-2 text-[11px] font-black"
                                    :class="markerClass(index)"
                                >
                                    <svg
                                        v-if="markerState(index) === 'done'"
                                        viewBox="0 0 16 16"
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        aria-hidden="true"
                                    >
                                        <path d="M3 8.5l3.2 3L13 4.5" stroke-linecap="square" />
                                    </svg>

                                    <span v-else-if="markerState(index) === 'failed'">×</span>

                                    <span
                                        v-else-if="markerState(index) === 'current'"
                                        class="h-2.5 w-2.5 animate-pulse bg-neutral-950"
                                    ></span>

                                    <span v-else>{{ index + 1 }}</span>
                                </span>

                                <span
                                    class="mt-3 text-[10px] font-black uppercase tracking-[0.12em] sm:text-[11px] sm:tracking-[0.16em]"
                                    :class="stageLabelClass(index)"
                                >
                                    {{ stage.label }}
                                </span>
                            </li>
                        </ol>
                    </section>

                    <!-- ===== Menunggu pembayaran ===== -->
                    <template v-if="mode === 'awaiting'">
                        <p
                            v-if="payForm.processing"
                            class="flex items-center gap-3 border border-neutral-950 px-4 py-3 text-sm font-bold"
                        >
                            <span class="h-2.5 w-2.5 shrink-0 animate-pulse bg-neutral-950"></span>
                            Creating your new payment code...
                        </p>

                        <p v-else-if="payForm.errors.method" class="border-l-2 border-red-600 pl-3 text-sm text-red-600">
                            {{ payForm.errors.method }}
                        </p>

                        <section v-if="activePayment" class="border border-neutral-950">
                            <header class="flex items-center gap-4 border-b border-neutral-950 px-5 py-4">
                                <PaymentLogo :method-key="activePayment.payment_method" :label="activeMethodLabel" />

                                <div>
                                    <p class="text-xs text-neutral-500">Pay with</p>
                                    <p class="text-sm font-black uppercase tracking-[0.1em]">{{ activeMethodLabel }}</p>
                                </div>
                            </header>

                            <div class="p-5 md:p-7">
                                <div v-if="vaNumber">
                                    <p class="text-xs font-bold text-neutral-500">Virtual account number</p>

                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                        <p class="text-2xl font-black tabular-nums tracking-[0.04em] sm:text-4xl">
                                            {{ formatVa(vaNumber) }}
                                        </p>

                                        <button
                                            type="button"
                                            class="border border-neutral-950 px-4 py-2.5 text-[11px] font-black uppercase tracking-[0.18em] transition hover:bg-neutral-950 hover:text-white"
                                            @click="copy('va', vaNumber.replace(/\s+/g, ''))"
                                        >
                                            {{ copiedKey === 'va' ? 'Copied' : 'Copy number' }}
                                        </button>
                                    </div>

                                    <div class="mt-6 border-t border-neutral-200 pt-5">
                                        <p class="text-xs font-bold text-neutral-500">Exact amount to transfer</p>

                                        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                            <p class="text-2xl font-black sm:text-3xl">{{ formatPrice(amountDue) }}</p>

                                            <button
                                                type="button"
                                                class="border border-neutral-300 px-4 py-2.5 text-[11px] font-black uppercase tracking-[0.18em] transition hover:border-neutral-950"
                                                @click="copy('amount', String(Math.round(amountDue)))"
                                            >
                                                {{ copiedKey === 'amount' ? 'Copied' : 'Copy amount' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="activePayment.qr_url" class="flex flex-col items-center">
                                    <div class="border-2 border-neutral-950 bg-white p-3">
                                        <img
                                            :src="activePayment.qr_url"
                                            alt="Payment QR code"
                                            class="h-56 w-56 object-contain sm:h-64 sm:w-64"
                                        />
                                    </div>

                                    <button
                                        type="button"
                                        :disabled="savingQr"
                                        class="mt-4 border border-neutral-950 px-6 py-3 text-[11px] font-black uppercase tracking-[0.2em] transition hover:bg-neutral-950 hover:text-white disabled:opacity-50"
                                        @click="saveQr"
                                    >
                                        {{ savingQr ? 'Saving...' : 'Save QR' }}
                                    </button>

                                    <p class="mt-3 max-w-sm text-center text-sm leading-6 text-neutral-500">
                                        Scan with any e-wallet or mobile banking app that supports QRIS. On a phone, save the QR and upload it from your gallery.
                                    </p>
                                </div>

                                <a
                                    v-if="activePayment.deeplink_url"
                                    :href="activePayment.deeplink_url"
                                    class="mt-5 flex w-full justify-center bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800 md:hidden"
                                >
                                    Open GoPay
                                </a>

                                <p v-if="Number(activePayment.fee) > 0" class="mt-5 text-sm text-neutral-500">
                                    Includes a payment fee of {{ formatPrice(activePayment.fee ?? 0) }}.
                                </p>
                            </div>
                        </section>

                        <section v-else class="border border-dashed border-neutral-950 p-6 text-center md:p-10">
                            <h2 class="text-3xl font-black uppercase tracking-[-0.04em]">
                                Choose how to pay
                            </h2>

                            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-600">
                                Your items are reserved while the timer runs. Pick a payment method to get your payment code.
                            </p>

                            <button
                                v-if="canChangeMethod"
                                type="button"
                                class="mt-6 bg-neutral-950 px-10 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                                @click="openMethodModal"
                            >
                                Choose payment method
                            </button>

                            <p v-else class="mt-6 text-sm font-bold text-neutral-950">
                                There is too little time left to start a new payment.
                            </p>
                        </section>

                        <div v-if="activePayment" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    :disabled="refreshing"
                                    class="bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="refreshStatus"
                                >
                                    {{ refreshing ? 'Checking...' : 'I have paid' }}
                                </button>

                                <button
                                    v-if="canChangeMethod"
                                    type="button"
                                    class="border border-neutral-950 bg-white px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                                    @click="openMethodModal"
                                >
                                    Change payment method
                                </button>
                            </div>

                            <p v-if="canChangeMethod" class="text-xs leading-5 text-neutral-500">
                                Changing the payment method does not extend your payment time.
                            </p>

                            <p v-if="showNotReceived" class="border-l-2 border-neutral-950 pl-3 text-sm text-neutral-700">
                                We have not received your payment yet. It can take a few minutes after you pay.
                            </p>

                            <p v-else class="flex items-center gap-2 text-sm text-neutral-500">
                                <span class="h-2 w-2 shrink-0 animate-pulse bg-neutral-950"></span>
                                We check for your payment automatically. This page updates as soon as it arrives.
                            </p>
                        </div>

                        <section v-if="guides.length" class="border border-neutral-200">
                            <h2 class="border-b border-neutral-200 px-5 py-4 text-lg font-black uppercase tracking-[-0.02em]">
                                How to pay
                            </h2>

                            <div
                                v-for="(guide, index) in guides"
                                :key="guide.title"
                                class="border-b border-neutral-200 last:border-b-0"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                                    :aria-expanded="openGuide === index"
                                    @click="openGuide = openGuide === index ? -1 : index"
                                >
                                    <span class="text-xs font-black uppercase tracking-[0.2em]">{{ guide.title }}</span>
                                    <span class="text-xl font-black leading-none">{{ openGuide === index ? '−' : '+' }}</span>
                                </button>

                                <ol v-show="openGuide === index" class="space-y-3 px-5 pb-6">
                                    <li
                                        v-for="(step, stepIndex) in guide.steps"
                                        :key="stepIndex"
                                        class="grid grid-cols-[24px_1fr] gap-3 text-sm leading-6 text-neutral-700"
                                    >
                                        <span class="font-black text-neutral-950">{{ stepIndex + 1 }}</span>
                                        <span>{{ guideStep(step) }}</span>
                                    </li>
                                </ol>
                            </div>

                            <p class="border-t border-neutral-200 bg-neutral-50 px-5 py-3 text-xs leading-5 text-neutral-500">
                                Menu names can differ slightly depending on your bank app version.
                            </p>
                        </section>

                        <p class="border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm leading-6 text-neutral-600">
                            <span class="font-black text-neutral-950">Stay safe.</span>
                            Only pay with the details on this page. NEVERENDING never asks for your PIN, OTP or password.
                        </p>
                    </template>

                    <!-- ===== Sudah dibayar: pengiriman ===== -->
                    <template v-else-if="mode === 'paid'">
                        <section v-if="estimateItems.length" class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="item in estimateItems"
                                :key="item.label"
                                class="border border-neutral-200 bg-neutral-50 p-4"
                            >
                                <p class="text-xs text-neutral-500">{{ item.label }}</p>
                                <p class="mt-2 text-sm font-black">{{ item.value }}</p>
                            </div>
                        </section>

                        <section class="border border-neutral-200 p-5 md:p-7">
                            <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                                Package tracking
                            </h2>

                            <p
                                v-if="!order.shipment?.tracking_number"
                                class="mt-4 text-sm leading-7 text-neutral-500"
                            >
                                Your tracking number will appear here as soon as the courier picks up your package.
                            </p>

                            <template v-if="order.shipment">
                                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs text-neutral-500">Courier</dt>
                                        <dd class="mt-1 text-sm font-bold">{{ courierLabel }}</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs text-neutral-500">Service</dt>
                                        <dd class="mt-1 text-sm font-bold">{{ order.shipment.service || '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs text-neutral-500">Tracking number</dt>
                                        <dd class="mt-1 flex flex-wrap items-center gap-3 text-sm font-bold">
                                            {{ order.shipment.tracking_number || '-' }}

                                            <button
                                                v-if="order.shipment.tracking_number"
                                                type="button"
                                                class="border border-neutral-300 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] transition hover:border-neutral-950"
                                                @click="copy('resi', order.shipment.tracking_number as string)"
                                            >
                                                {{ copiedKey === 'resi' ? 'Copied' : 'Copy' }}
                                            </button>
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs text-neutral-500">Status</dt>
                                        <dd class="mt-1 text-sm font-bold">
                                            {{ order.shipment.tracking_status || order.shipment.status.replaceAll('_', ' ') || '-' }}
                                        </dd>
                                    </div>

                                    <div v-if="order.shipment.last_tracked_at">
                                        <dt class="text-xs text-neutral-500">Last update</dt>
                                        <dd class="mt-1 text-sm font-bold">{{ formatDate(order.shipment.last_tracked_at) }}</dd>
                                    </div>
                                </dl>

                                <div v-if="order.shipment.tracking_history?.length" class="mt-8 space-y-5">
                                    <div
                                        v-for="(history, index) in order.shipment.tracking_history"
                                        :key="index"
                                        class="border-l-2 pl-4"
                                        :class="index === 0 ? 'border-neutral-950' : 'border-neutral-300'"
                                    >
                                        <p class="text-xs text-neutral-500">{{ history.date || '-' }}</p>
                                        <p class="mt-1 text-sm font-bold">{{ history.desc || '-' }}</p>
                                        <p v-if="history.location" class="mt-1 text-sm text-neutral-500">{{ history.location }}</p>
                                    </div>
                                </div>
                            </template>
                        </section>
                    </template>
                </div>

                <!-- ============================ RINGKASAN ============================ -->
                <aside class="h-fit space-y-6 lg:sticky lg:top-28">
                    <section class="border border-neutral-200 bg-white">
                        <div class="flex items-baseline justify-between border-b border-neutral-200 px-5 py-4">
                            <h2 class="text-xl font-black uppercase tracking-[-0.03em]">Order summary</h2>
                            <span class="text-xs text-neutral-500">
                                {{ order.items.length }} {{ order.items.length === 1 ? 'item' : 'items' }}
                            </span>
                        </div>

                        <div class="space-y-5 p-5">
                            <article v-for="item in order.items" :key="item.id" class="grid grid-cols-[64px_1fr_auto] gap-4">
                                <Link
                                    v-if="item.product"
                                    :href="`/products/${item.product.slug}`"
                                    class="block overflow-hidden bg-neutral-100"
                                >
                                    <img
                                        :src="storageUrl(item.product?.primary_image?.image_path)"
                                        :alt="item.product_name"
                                        class="aspect-[4/5] w-full object-cover"
                                    />
                                </Link>

                                <div v-else class="aspect-[4/5] bg-neutral-100"></div>

                                <div class="min-w-0">
                                    <h3 class="text-sm font-black uppercase leading-5 tracking-[-0.01em]">
                                        {{ item.product_name }}
                                    </h3>

                                    <p class="mt-1 text-xs text-neutral-500">
                                        Size {{ sizeLabel(item) }}, qty {{ item.quantity }}
                                    </p>
                                </div>

                                <p class="text-sm font-black">{{ formatPrice(item.subtotal) }}</p>
                            </article>
                        </div>

                        <div class="space-y-3 border-t border-neutral-200 p-5 text-sm">
                            <div class="flex justify-between gap-4">
                                <span class="text-neutral-500">Subtotal</span>
                                <span class="font-bold">{{ formatPrice(order.subtotal) }}</span>
                            </div>

                            <div>
                                <div class="flex justify-between gap-4">
                                    <span class="text-neutral-500">Shipping</span>
                                    <span class="font-bold">{{ formatPrice(order.shipping_cost) }}</span>
                                </div>

                                <p v-if="shippingMethod" class="mt-1 text-xs text-neutral-500">{{ shippingMethod }}</p>
                            </div>

                            <div v-if="insuranceFee > 0" class="flex justify-between gap-4">
                                <span class="text-neutral-500">Shipping insurance</span>
                                <span class="font-bold">{{ formatPrice(insuranceFee) }}</span>
                            </div>

                            <div v-if="paymentFee > 0" class="flex justify-between gap-4">
                                <span class="text-neutral-500">Payment fee</span>
                                <span class="font-bold">{{ formatPrice(paymentFee) }}</span>
                            </div>
                        </div>

                        <div class="flex items-baseline justify-between border-t-2 border-neutral-950 p-5">
                            <span class="text-lg font-black uppercase tracking-[-0.03em]">Total</span>
                            <span class="text-2xl font-black">{{ formatPrice(order.total) }}</span>
                        </div>
                    </section>

                    <section class="border border-neutral-200 p-5 text-sm leading-6">
                        <h2 class="text-xl font-black uppercase tracking-[-0.03em]">Order details</h2>

                        <dl class="mt-4 space-y-4">
                            <div v-if="customerEmail">
                                <dt class="text-xs text-neutral-500">Email</dt>
                                <dd class="font-bold">{{ customerEmail }}</dd>
                            </div>

                            <div v-if="shippingMethod">
                                <dt class="text-xs text-neutral-500">Shipping method</dt>
                                <dd class="font-bold">{{ shippingMethod }}</dd>
                            </div>

                            <div>
                                <dt class="text-xs text-neutral-500">Recipient address</dt>
                                <dd class="text-neutral-700">
                                    <p class="font-bold text-neutral-950">{{ order.recipient_name }}</p>
                                    <p>{{ order.phone }}</p>
                                    <p class="mt-1">{{ order.full_address }}</p>
                                    <p>
                                        {{ order.district }}, {{ order.city }}, {{ order.province }}
                                        <span v-if="order.postal_code">{{ order.postal_code }}</span>
                                    </p>
                                </dd>
                            </div>

                            <div v-if="order.notes">
                                <dt class="text-xs text-neutral-500">Notes</dt>
                                <dd class="border-l-2 border-neutral-950 pl-3 text-neutral-700">{{ order.notes }}</dd>
                            </div>
                        </dl>
                    </section>

                    <div class="space-y-3">
                        <Link
                            v-if="mode !== 'closed' && mode !== 'closing'"
                            href="/products"
                            class="flex w-full justify-center border border-neutral-950 bg-white px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                        >
                            Continue shopping
                        </Link>

                        <button
                            v-if="canCancel"
                            type="button"
                            class="block w-full py-2 text-center text-xs font-black uppercase tracking-[0.2em] text-neutral-500 underline underline-offset-8 transition hover:text-neutral-950"
                            @click="showCancelModal = true"
                        >
                            Cancel order
                        </button>

                        <div class="space-y-2 pt-2 text-center text-xs leading-6 text-neutral-500">
                            <p>
                                Need help?
                                <Link :href="HELP_LINKS.contact" class="font-bold text-neutral-950 underline underline-offset-4">Contact us</Link>
                            </p>

                            <p class="flex justify-center gap-5">
                                <Link :href="HELP_LINKS.faq" class="underline underline-offset-4 hover:text-neutral-950">FAQ</Link>
                                <Link :href="HELP_LINKS.terms" class="underline underline-offset-4 hover:text-neutral-950">Terms &amp; Conditions</Link>
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </main>

        <!-- Bar bawah (HP) -->
        <div
            v-if="showStickyBar"
            class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-neutral-950 bg-white px-4 pt-3 lg:hidden"
            :style="{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }"
        >
            <div class="flex items-center gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] text-neutral-500">Amount due, {{ countdown }} left</p>
                    <p class="text-lg font-black">{{ formatPrice(amountDue) }}</p>
                </div>

                <button
                    type="button"
                    :disabled="refreshing"
                    class="ml-auto bg-neutral-950 px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-white disabled:opacity-50"
                    @click="refreshStatus"
                >
                    {{ refreshing ? 'Checking...' : 'I have paid' }}
                </button>
            </div>
        </div>

        <!-- Pemilih metode bayar (sama dengan checkout) -->
        <PaymentPicker
            :open="showMethodModal"
            :methods="paymentMethods"
            :selected-key="activePayment?.payment_method ?? null"
            :fees="methodFees"
            @close="showMethodModal = false"
            @select="chooseMethod"
        />

        <!-- Modal batalkan order -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-[100] flex items-end justify-center bg-black/60 sm:items-center"
            @click.self="showCancelModal = false"
        >
            <div class="w-full bg-white p-6 sm:max-w-md sm:p-8">
                <h3 class="text-2xl font-black uppercase tracking-[-0.03em]">Cancel this order?</h3>

                <p class="mt-3 text-sm leading-7 text-neutral-600">
                    Your payment code will stop working and your items will be released. If you already paid, do not cancel. Wait a few minutes for your payment to arrive.
                </p>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800"
                        @click="showCancelModal = false"
                    >
                        Keep order
                    </button>

                    <button
                        type="button"
                        :disabled="cancelForm.processing"
                        class="border border-neutral-950 bg-white px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white disabled:opacity-50"
                        @click="confirmCancel"
                    >
                        {{ cancelForm.processing ? 'Cancelling...' : 'Cancel order' }}
                    </button>
                </div>
            </div>
        </div>
    </StoreLayout>
</template>

<style scoped>
@keyframes paid-pop {
    0% {
        transform: scale(0.5);
        opacity: 0;
    }

    60% {
        transform: scale(1.12);
        opacity: 1;
    }

    100% {
        transform: scale(1);
    }
}

.paid-pop {
    animation: paid-pop 0.5s ease-out both;
}

@media (prefers-reduced-motion: reduce) {
    .paid-pop {
        animation: none;
    }
}
</style>