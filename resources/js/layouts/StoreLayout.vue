<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StoreFooter from '@/components/StoreFooter.vue';

type PageProps = {
    auth?: {
        user?: unknown;
    };
    cart_count?: number;
    unreadNotificationsCount?: number;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

type NotificationPreview = {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string | null;
    order: {
        order_code: string;
        product: {
            name: string;
            image: string;
        } | null;
    } | null;
};

type MenuItem = {
    label: string;
    href: string;
};

type MenuGroup = {
    title: string;
    items: MenuItem[];
};

const props = withDefaults(defineProps<{
    transparentHeader?: boolean;
}>(), {
    transparentHeader: false,
});

const page = usePage();

const isLoggedIn = computed(() => Boolean((page.props as PageProps).auth?.user));
const flashSuccess = computed(() => (page.props as PageProps).flash?.success);
const flashError = computed(() => (page.props as PageProps).flash?.error);
const showFlashSuccess = ref(false);
const showFlashError = ref(false);

let flashTimer: number | undefined;
let removeRouterStartListener: (() => void) | undefined;
let removeRouterFinishListener: (() => void) | undefined;

const resetFlashTimer = () => {
    if (flashTimer) {
        window.clearTimeout(flashTimer);
    }

    flashTimer = window.setTimeout(() => {
        showFlashSuccess.value = false;
        showFlashError.value = false;
    }, 3000);
};

watch(
    flashSuccess,
    (value) => {
        if (value) {
            showFlashSuccess.value = true;
            resetFlashTimer();
        }
    },
    { immediate: true },
);

watch(
    flashError,
    (value) => {
        if (value) {
            showFlashError.value = true;
            resetFlashTimer();
        }
    },
    { immediate: true },
);
const cartCount = computed(() => Number((page.props as PageProps).cart_count ?? 0));
const unreadNotificationsCount = computed(() => Number((page.props as PageProps).unreadNotificationsCount ?? 0));

const isShopOpen = ref(false);
const isAccountOpen = ref(false);
const isMobileMenuOpen = ref(false);
const isScrolled = ref(false);
const isSearchOpen = ref(false);
const searchKeyword = ref('');
const isPageLoading = ref(false);
const isNotificationsOpen = ref(false);
const isLoadingNotifications = ref(false);
const hasNotificationsError = ref(false);
const latestNotifications = ref<NotificationPreview[]>([]);
const openingNotificationId = ref<number | null>(null);
const notificationsRoot = ref<HTMLElement | null>(null);
const menuToggleButton = ref<HTMLButtonElement | null>(null);
const menuCloseButton = ref<HTMLButtonElement | null>(null);

let latestRequestId = 0;

const NOTIFICATION_TIME_ZONE = 'Asia/Jakarta';

const notificationTimeFormat = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: NOTIFICATION_TIME_ZONE,
});

const negativeNotificationTypes = ['payment_failed', 'payment_expired', 'order_cancelled', 'refund_required'];

const isNegativeNotification = (type: string) => negativeNotificationTypes.includes(type);

const formatNotificationTime = (date: string | null) =>
    date ? `${notificationTimeFormat.format(new Date(date))} WIB` : '';

const menuGroups: MenuGroup[] = [
    {
        title: 'Shop',
        items: [
            { label: 'New Arrivals', href: '/products' },
            { label: 'All Products', href: '/products' },
            { label: 'Tees', href: '/products?category=tees' },
            { label: 'Hoodies', href: '/products?category=hoodies' },
        ],
    },
    {
        title: 'Clothing',
        items: [
            { label: 'Longsleeve', href: '/products?category=longsleeve' },
            { label: 'Shirts', href: '/products?category=shirts' },
            { label: 'Outerwear', href: '/products?category=outerwear' },
            { label: 'Pants', href: '/products?category=pants' },
        ],
    },
    {
        title: 'Accessories',
        items: [
            { label: 'Headwear', href: '/products?category=headwear' },
            { label: 'Bags', href: '/products?category=bags' },
            { label: 'Socks', href: '/products?category=socks' },
            { label: 'Jewelry', href: '/products?category=jewelry' },
        ],
    },
];

const mobileShopLinks: MenuItem[] = [
    { label: 'New Arrivals', href: '/products' },
    { label: 'All Products', href: '/products' },
];

const mobileShopGroups: (MenuGroup & { key: string })[] = [
    {
        key: 'clothing',
        title: 'Clothing',
        items: [
            { label: 'Tees', href: '/products?category=tees' },
            { label: 'Hoodies', href: '/products?category=hoodies' },
            { label: 'Longsleeve', href: '/products?category=longsleeve' },
            { label: 'Shirts', href: '/products?category=shirts' },
            { label: 'Outerwear', href: '/products?category=outerwear' },
            { label: 'Pants', href: '/products?category=pants' },
        ],
    },
    {
        key: 'accessories',
        title: 'Accessories',
        items: [
            { label: 'Headwear', href: '/products?category=headwear' },
            { label: 'Bags', href: '/products?category=bags' },
            { label: 'Socks', href: '/products?category=socks' },
            { label: 'Jewelry', href: '/products?category=jewelry' },
        ],
    },
];

const mobileOpen = ref<Record<string, boolean>>({});

const toggleMobileSection = (key: string) => {
    mobileOpen.value = { ...mobileOpen.value, [key]: !mobileOpen.value[key] };
};

const closeMenus = () => {
    isShopOpen.value = false;
    isAccountOpen.value = false;
    isMobileMenuOpen.value = false;
    isNotificationsOpen.value = false;
};

const openMobileMenu = () => {
    mobileOpen.value = {};
    isShopOpen.value = false;
    isAccountOpen.value = false;
    isNotificationsOpen.value = false;
    isMobileMenuOpen.value = true;
};

const closeMobileMenu = () => {
    closeMenus();
    nextTick(() => menuToggleButton.value?.focus());
};

const toggleAccount = () => {
    isAccountOpen.value = !isAccountOpen.value;
    isNotificationsOpen.value = false;
};

const loadLatestNotifications = async () => {
    latestRequestId += 1;

    const requestId = latestRequestId;

    isLoadingNotifications.value = true;
    hasNotificationsError.value = false;

    try {
        const response = await fetch('/notifications/latest', {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Request failed');
        }

        const data = await response.json();

        if (requestId === latestRequestId) {
            latestNotifications.value = Array.isArray(data.notifications) ? data.notifications : [];
        }
    } catch {
        if (requestId === latestRequestId) {
            hasNotificationsError.value = true;
        }
    } finally {
        if (requestId === latestRequestId) {
            isLoadingNotifications.value = false;
        }
    }
};

const toggleNotifications = () => {
    const willOpen = !isNotificationsOpen.value;

    isShopOpen.value = false;
    isAccountOpen.value = false;
    isNotificationsOpen.value = willOpen;

    if (willOpen) {
        loadLatestNotifications();
    }
};

const openLatestNotification = (notification: NotificationPreview) => {
    if (openingNotificationId.value !== null) {
        return;
    }

    openingNotificationId.value = notification.id;

    router.post(
        `/notifications/${notification.id}/read`,
        {},
        {
            onFinish: () => {
                openingNotificationId.value = null;
                isNotificationsOpen.value = false;
            },
        },
    );
};

const handleDocumentClick = (event: MouseEvent) => {
    if (!isNotificationsOpen.value) {
        return;
    }

    const target = event.target as Node | null;

    if (target && notificationsRoot.value && !notificationsRoot.value.contains(target)) {
        isNotificationsOpen.value = false;
    }
};

const openSearch = async () => {
    isShopOpen.value = false;
    isAccountOpen.value = false;
    isMobileMenuOpen.value = false;
    isNotificationsOpen.value = false;

    handleScroll();

    const params = new URLSearchParams(window.location.search);
    searchKeyword.value = params.get('search') ?? '';
    isSearchOpen.value = true;

    await nextTick();

    document.getElementById('store-search-input')?.focus();
};

const closeSearch = () => {
    isSearchOpen.value = false;
};

const submitSearch = () => {
    const keyword = searchKeyword.value.trim();

    isSearchOpen.value = false;

    router.get(
        '/products',
        {
            search: keyword || undefined,
        },
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

const quickSearch = (keyword: string) => {
    searchKeyword.value = keyword;
    submitSearch();
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isSearchOpen.value) {
        closeSearch();
    }

    if (event.key === 'Escape' && isNotificationsOpen.value) {
        isNotificationsOpen.value = false;
    }

    if (event.key === 'Escape' && isMobileMenuOpen.value) {
        closeMobileMenu();
    }
};

watch(isMobileMenuOpen, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        await nextTick();
        menuCloseButton.value?.focus();
    }
});

let desktopQuery: MediaQueryList | undefined;

const handleDesktopChange = (event: MediaQueryListEvent) => {
    if (event.matches) {
        closeMenus();
    }
};

const handleScroll = () => {
    const scrollTop =
        window.scrollY ||
        document.documentElement.scrollTop ||
        document.body.scrollTop ||
        0;

    isScrolled.value = scrollTop > 40;
};

const showTransparentHeader = computed(() => {
    return (
        props.transparentHeader === true &&
        !isScrolled.value &&
        !isSearchOpen.value
    );
});

const showAnnouncement = computed(() => {
    return (
        props.transparentHeader === true &&
        !isScrolled.value &&
        !isSearchOpen.value
    );
});

const headerClass = computed(() => {
    if (showTransparentHeader.value) {
        return 'top-[28px] border-b border-white/10 bg-transparent text-white';
    }

    return 'top-0 border-b border-neutral-200 bg-white/95 text-neutral-950 shadow-sm backdrop-blur-xl';
});

onMounted(() => {
    handleScroll();

    window.addEventListener('scroll', handleScroll, {
        passive: true,
    });

    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleDocumentClick);

    desktopQuery = window.matchMedia('(min-width: 1024px)');
    desktopQuery.addEventListener('change', handleDesktopChange);

    removeRouterStartListener = router.on('start', () => {
        isPageLoading.value = true;
    });

    removeRouterFinishListener = router.on('finish', () => {
        isPageLoading.value = false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleDocumentClick);
    desktopQuery?.removeEventListener('change', handleDesktopChange);
    document.body.style.overflow = '';

    removeRouterStartListener?.();
    removeRouterFinishListener?.();

    if (flashTimer) {
        window.clearTimeout(flashTimer);
    }
});
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-950">
        <div
            v-if="isPageLoading"
            class="fixed left-0 top-0 z-[200] h-1 w-full overflow-hidden bg-neutral-200"
        >
            <div class="h-full w-1/2 animate-[neverending-loading_1s_ease-in-out_infinite] bg-neutral-950"></div>
        </div>
        <div
            v-if="flashSuccess && showFlashSuccess"
            class="fixed right-5 top-24 z-[100] w-[calc(100%-2.5rem)] max-w-sm border border-green-200 bg-green-50 p-5 text-sm text-green-800 shadow-2xl"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-green-700">
                        Success
                    </p>

                    <p class="mt-2 font-bold leading-6">
                        {{ flashSuccess }}
                    </p>
                </div>

                <button
                    type="button"
                    class="text-xs font-black uppercase tracking-[0.18em] text-green-700 transition hover:text-green-950"
                    @click="showFlashSuccess = false"
                >
                    X
                </button>
            </div>
        </div>

        <div
            v-if="flashError && showFlashError"
            class="fixed right-5 top-24 z-[100] w-[calc(100%-2.5rem)] max-w-sm border border-red-200 bg-red-50 p-5 text-sm text-red-800 shadow-2xl"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-red-700">
                        Error
                    </p>

                    <p class="mt-2 font-bold leading-6">
                        {{ flashError }}
                    </p>
                </div>

                <button
                    type="button"
                    class="text-xs font-black uppercase tracking-[0.18em] text-red-700 transition hover:text-red-950"
                    @click="showFlashError = false"
                >
                    X
                </button>
            </div>
        </div>

        <div
            v-show="showAnnouncement"
            class="fixed left-0 right-0 top-0 z-[60] overflow-hidden bg-neutral-950 py-1.5 text-white transition duration-300"
        >
            <div class="overflow-hidden bg-neutral-950 py-2 text-white">
                <div class="marquee-track text-[10px] font-bold uppercase tracking-[0.25em]">
                    <span class="marquee-item">
                        NEVERENDING DAILY WEAR — NEW DROP AVAILABLE — BUILT FOR ENDLESS ROTATION —
                        NEVERENDING DAILY WEAR — NEW DROP AVAILABLE — BUILT FOR ENDLESS ROTATION —
                    </span>

                    <span class="marquee-item">
                        NEVERENDING DAILY WEAR — NEW DROP AVAILABLE — BUILT FOR ENDLESS ROTATION —
                        NEVERENDING DAILY WEAR — NEW DROP AVAILABLE — BUILT FOR ENDLESS ROTATION —
                    </span>
                </div>
            </div>
        </div>

        <header
            class="fixed left-0 right-0 z-50 transition-all duration-300"
            :class="headerClass"
        >
            <div class="grid grid-cols-[auto_1fr_auto] items-center px-5 py-4 lg:hidden">
                <button
                    ref="menuToggleButton"
                    type="button"
                    class="flex items-center justify-self-start"
                    aria-label="Open menu"
                    aria-controls="mobile-menu"
                    :aria-expanded="isMobileMenuOpen"
                    @click="openMobileMenu"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-7 w-7"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M4 7h16M4 12h16M4 17h16"
                        />
                    </svg>
                </button>

                <Link
                    href="/"
                    class="justify-self-center text-lg font-black uppercase tracking-[0.28em]"
                    @click="closeMenus"
                >
                    NEVERENDING
                </Link>

                <div class="flex items-center justify-self-end gap-4">
                    <Link
                        v-if="isLoggedIn"
                        href="/notifications"
                        aria-label="Notifications"
                        class="relative inline-flex items-center justify-center"
                        @click="closeMenus"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-7 w-7"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9"
                            />
                        </svg>

                        <span
                            v-if="unreadNotificationsCount > 0"
                            class="absolute -right-2 -top-2 z-10 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black leading-none text-white"
                        >
                            {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                        </span>
                    </Link>

                    <Link
                        href="/cart"
                        aria-label="Cart"
                        class="relative inline-flex items-center justify-center"
                        @click="closeMenus"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-7 w-7"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M3.75 5.25h2.1l1.8 10.05a2.25 2.25 0 0 0 2.22 1.86h6.65a2.25 2.25 0 0 0 2.2-1.78l1.03-5.13H7.2M9.75 20.25h.01M17.25 20.25h.01"
                            />
                        </svg>

                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-2 -top-2 z-10 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-black leading-none"
                            :class="showTransparentHeader ? 'bg-white text-neutral-950' : 'bg-neutral-950 text-white'"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                </div>
            </div>

            <div class="relative hidden h-[62px] items-center lg:block">
                <nav class="absolute left-[6.5vw] top-1/2 flex -translate-y-1/2 items-center gap-8 text-[11px] font-black uppercase tracking-[0.15em]">
                    <div
                        class="relative"
                        @mouseenter="isShopOpen = true"
                        @mouseleave="isShopOpen = false"
                    >
                        <Link href="/products" class="transition hover:opacity-60">
                            Shop
                        </Link>

                        <div
                            v-if="isShopOpen"
                            class="absolute left-0 top-full z-50 pt-5"
                        >
                            <div class="w-[760px] border border-neutral-200 bg-white p-8 text-neutral-950 shadow-2xl">
                                <div class="grid grid-cols-3 gap-10">
                                    <div
                                        v-for="group in menuGroups"
                                        :key="group.title"
                                    >
                                        <p class="mb-5 text-[11px] font-black uppercase tracking-[0.24em] text-neutral-400">
                                            {{ group.title }}
                                        </p>

                                        <div class="space-y-3 text-sm font-semibold normal-case tracking-normal">
                                            <Link
                                                v-for="item in group.items"
                                                :key="item.label"
                                                :href="item.href"
                                                class="block hover:text-neutral-500"
                                                @click="closeMenus"
                                            >
                                                {{ item.label }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Link href="/products" class="transition hover:opacity-60">
                        Collection
                    </Link>

                    <Link href="/products" class="transition hover:opacity-60">
                        Sale
                    </Link>

                    <Link href="/products" class="transition hover:opacity-60">
                        Campaign
                    </Link>
                </nav>

                <Link
                    href="/"
                    class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[24px] font-black uppercase tracking-[0.34em] transition"
                    @click="closeMenus"
                >
                    NEVERENDING
                </Link>

                <div class="absolute right-[3vw] top-1/2 flex -translate-y-1/2 items-center gap-6">
                    <button
                        type="button"
                        aria-label="Search"
                        class="transition hover:opacity-60"
                        @click="openSearch"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="m21 21-4.35-4.35m1.1-5.4a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"
                            />
                        </svg>
                    </button>

                    <div v-if="isLoggedIn" ref="notificationsRoot" class="relative">
                        <button
                            type="button"
                            aria-label="Notifications"
                            :aria-expanded="isNotificationsOpen"
                            class="relative flex items-center transition hover:opacity-60"
                            @click="toggleNotifications"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9"
                                />
                            </svg>

                            <span
                                v-if="unreadNotificationsCount > 0"
                                class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black leading-none text-white"
                            >
                                {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                            </span>
                        </button>

                        <div
                            v-if="isNotificationsOpen"
                            class="absolute right-0 top-full z-50 mt-5 w-[26rem] border border-neutral-200 bg-white text-neutral-950 shadow-2xl"
                        >
                            <p class="border-b border-neutral-200 px-5 py-4 text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                Latest Updates
                            </p>

                            <p
                                v-if="isLoadingNotifications && !latestNotifications.length"
                                class="px-5 py-10 text-center text-xs font-bold uppercase tracking-[0.18em] text-neutral-400"
                            >
                                Loading...
                            </p>

                            <div v-else-if="hasNotificationsError" class="px-5 py-10 text-center">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-500">
                                    We could not load your updates.
                                </p>

                                <button
                                    type="button"
                                    class="mt-4 text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8 transition hover:text-neutral-500"
                                    @click="loadLatestNotifications"
                                >
                                    Try again
                                </button>
                            </div>

                            <p
                                v-else-if="!latestNotifications.length"
                                class="px-5 py-10 text-center text-xs font-bold uppercase tracking-[0.18em] text-neutral-400"
                            >
                                No updates yet.
                            </p>

                            <div v-else class="max-h-[28rem] divide-y divide-neutral-200 overflow-y-auto">
                                <button
                                    v-for="notification in latestNotifications"
                                    :key="notification.id"
                                    type="button"
                                    :disabled="openingNotificationId !== null"
                                    class="flex w-full gap-4 border-l-4 px-5 py-4 text-left transition disabled:cursor-wait"
                                    :class="
                                        notification.is_read
                                            ? 'border-transparent bg-white hover:bg-neutral-100'
                                            : 'border-neutral-950 bg-neutral-100 hover:bg-neutral-200'
                                    "
                                    @click="openLatestNotification(notification)"
                                >
                                    <img
                                        v-if="notification.order?.product"
                                        :src="notification.order.product.image"
                                        :alt="notification.order.product.name"
                                        class="h-14 w-14 shrink-0 bg-neutral-200 object-cover"
                                        loading="lazy"
                                    />

                                    <div
                                        v-else
                                        class="flex h-14 w-14 shrink-0 items-center justify-center bg-neutral-200 text-neutral-400"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-6 w-6"
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
                                            class="text-xs font-black uppercase tracking-[0.12em]"
                                            :class="isNegativeNotification(notification.type) ? 'text-red-600' : 'text-neutral-950'"
                                        >
                                            {{ notification.title }}
                                        </p>

                                        <p class="mt-1 line-clamp-2 text-sm font-medium normal-case leading-5 tracking-normal text-neutral-700">
                                            {{ notification.message }}
                                        </p>

                                        <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.16em] text-neutral-400">
                                            {{ formatNotificationTime(notification.created_at) }}
                                        </p>
                                    </div>
                                </button>
                            </div>

                            <Link
                                href="/notifications"
                                class="block border-t border-neutral-200 px-5 py-4 text-center text-[11px] font-black uppercase tracking-[0.22em] transition hover:bg-neutral-950 hover:text-white"
                                @click="closeMenus"
                            >
                                Show All
                            </Link>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            aria-label="Account"
                            class="flex items-center transition hover:opacity-60"
                            @click="toggleAccount"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0"
                                />
                            </svg>
                        </button>

                        <div
                            v-if="isAccountOpen"
                            class="absolute right-0 top-full z-50 mt-5 w-60 border border-neutral-200 bg-white p-5 text-neutral-950 shadow-2xl"
                        >
                            <div class="space-y-3 text-sm font-semibold">
                                <Link
                                    v-if="isLoggedIn"
                                    href="/account"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    My Account
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/orders"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Order History
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/notifications"
                                    class="flex items-center justify-between gap-3 hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    <span>Notifications</span>

                                    <span
                                        v-if="unreadNotificationsCount > 0"
                                        class="rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-black text-white"
                                    >
                                        {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                                    </span>
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/account/addresses"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    My Addresses
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/cart"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Cart
                                </Link>

                                <Link
                                    v-if="!isLoggedIn"
                                    href="/login"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Login
                                </Link>

                                <Link
                                    v-if="!isLoggedIn"
                                    href="/register"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Register
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    class="block text-left hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Log Out
                                </Link>
                            </div>
                        </div>
                    </div>

                    <Link
                        href="/cart"
                        aria-label="Cart"
                        class="relative transition hover:opacity-60"
                        @click="closeMenus"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M3.75 5.25h2.1l1.8 10.05a2.25 2.25 0 0 0 2.22 1.86h6.65a2.25 2.25 0 0 0 2.2-1.78l1.03-5.13H7.2M9.75 20.25h.01M17.25 20.25h.01"
                            />
                        </svg>
                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-neutral-950 px-1 text-[10px] font-black leading-none text-white"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                </div>
            </div>
        </header>

        <div class="lg:hidden">
            <Transition
                enter-active-class="transition-opacity duration-300 ease-out motion-reduce:transition-none"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-200 ease-in motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="isMobileMenuOpen"
                    class="fixed inset-0 z-[110] bg-neutral-950/60"
                    aria-hidden="true"
                    @click="closeMobileMenu"
                ></div>
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-300 ease-out motion-reduce:transition-none"
                enter-from-class="-translate-x-full"
                leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
                leave-to-class="-translate-x-full"
            >
                <aside
                    v-if="isMobileMenuOpen"
                    id="mobile-menu"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Main menu"
                    class="fixed inset-y-0 left-0 z-[111] flex w-[86%] max-w-[22rem] flex-col bg-white text-neutral-950 shadow-2xl"
                >
                    <div class="flex h-[60px] shrink-0 items-center gap-4 border-b border-neutral-200 px-5">
                        <button
                            ref="menuCloseButton"
                            type="button"
                            class="flex items-center"
                            aria-label="Close menu"
                            @click="closeMobileMenu"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-7 w-7"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M6 18 18 6M6 6l12 12"
                                />
                            </svg>
                        </button>

                        <Link
                            href="/"
                            class="text-base font-black uppercase tracking-[0.28em]"
                            @click="closeMenus"
                        >
                            NEVERENDING
                        </Link>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain">
                        <nav class="shrink-0 px-6 pb-4 pt-2">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 border-b border-neutral-200 py-4 text-left text-sm font-semibold text-neutral-500"
                                @click="openSearch"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.1-5.4a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                                </svg>
                                Search
                            </button>

                            <div>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between py-4 text-left text-[13px] font-bold uppercase tracking-[0.16em]"
                                    :aria-expanded="Boolean(mobileOpen.shop)"
                                    @click="toggleMobileSection('shop')"
                                >
                                    Shop
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 shrink-0 transition-transform"
                                        :class="mobileOpen.shop ? 'rotate-180' : ''"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>

                                <div v-show="mobileOpen.shop" class="pb-2 pl-4">
                                    <Link
                                        v-for="item in mobileShopLinks"
                                        :key="item.label"
                                        :href="item.href"
                                        class="block py-3 text-sm font-semibold"
                                        @click="closeMenus"
                                    >
                                        {{ item.label }}
                                    </Link>

                                    <div v-for="group in mobileShopGroups" :key="group.key">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between py-3 text-left text-sm font-semibold"
                                            :aria-expanded="Boolean(mobileOpen[group.key])"
                                            @click="toggleMobileSection(group.key)"
                                        >
                                            {{ group.title }}
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-4 w-4 shrink-0 transition-transform"
                                                :class="mobileOpen[group.key] ? 'rotate-180' : ''"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                aria-hidden="true"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                            </svg>
                                        </button>

                                        <div v-show="mobileOpen[group.key]" class="pb-1 pl-4">
                                            <Link
                                                v-for="item in group.items"
                                                :key="item.label"
                                                :href="item.href"
                                                class="block py-2.5 text-sm text-neutral-600"
                                                @click="closeMenus"
                                            >
                                                {{ item.label }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Link
                                href="/products"
                                class="block py-4 text-[13px] font-bold uppercase tracking-[0.16em]"
                                @click="closeMenus"
                            >
                                Collection
                            </Link>

                            <Link
                                href="/products"
                                class="block py-4 text-[13px] font-bold uppercase tracking-[0.16em]"
                                @click="closeMenus"
                            >
                                Sale
                            </Link>

                            <Link
                                href="/products"
                                class="block py-4 text-[13px] font-bold uppercase tracking-[0.16em]"
                                @click="closeMenus"
                            >
                                Campaign
                            </Link>
                        </nav>

                        <div class="mt-auto shrink-0 bg-neutral-100 px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-5 text-sm font-semibold">
                            <template v-if="isLoggedIn">
                                <Link href="/account" class="block py-2" @click="closeMenus">My Account</Link>
                                <Link href="/orders" class="block py-2" @click="closeMenus">Orders</Link>
                                <Link href="/account/addresses" class="block py-2" @click="closeMenus">Addresses</Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    class="block py-2 text-left text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Log Out
                                </Link>
                            </template>

                            <template v-else>
                                <Link href="/login" class="block py-2" @click="closeMenus">Login</Link>
                                <Link href="/register" class="block py-2" @click="closeMenus">Register</Link>
                            </template>
                        </div>
                    </div>
                </aside>
            </Transition>
        </div>

        <div
            v-if="isSearchOpen"
            class="fixed inset-0 z-[120] bg-neutral-950/80 px-6 backdrop-blur-sm"
            @click.self="closeSearch"
        >
            <div class="mx-auto mt-28 max-w-4xl border border-white/10 bg-white p-6 shadow-2xl md:mt-36 md:p-8">
                <div class="mb-6 flex items-center justify-between gap-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                            Search
                        </p>

                        <h2 class="mt-2 text-3xl font-black uppercase tracking-[-0.04em] md:text-5xl">
                            Find Your Rotation
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="text-xs font-black uppercase tracking-[0.2em] text-neutral-500 transition hover:text-neutral-950"
                        @click="closeSearch"
                    >
                        Close
                    </button>
                </div>

                <form
                    class="flex flex-col gap-3 md:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <input
                        id="store-search-input"
                        v-model="searchKeyword"
                        type="text"
                        placeholder="Search tees, hoodies, bags..."
                        class="min-h-14 flex-1 border border-neutral-300 bg-white px-5 text-base font-bold outline-none transition duration-200 focus:border-neutral-950 focus:ring-1 focus:ring-neutral-950"
                    />

                    <button
                        type="submit"
                        class="min-h-14 bg-neutral-950 px-8 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                    >
                        Search
                    </button>
                </form>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="border border-neutral-200 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950"
                        @click="quickSearch('tees')"
                    >
                        Tees
                    </button>

                    <button
                        type="button"
                        class="border border-neutral-200 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950"
                        @click="quickSearch('hoodies')"
                    >
                        Hoodies
                    </button>

                    <button
                        type="button"
                        class="border border-neutral-200 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950"
                        @click="quickSearch('bags')"
                    >
                        Bags
                    </button>

                    <button
                        type="button"
                        class="border border-neutral-200 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950"
                        @click="quickSearch('outerwear')"
                    >
                        Outerwear
                    </button>
                </div>
            </div>
        </div>

        <slot />

        <StoreFooter />
    </div>
</template>

<style scoped>
.marquee-track {
    display: flex;
    width: max-content;
    white-space: nowrap;
    animation: neverending-marquee 22s linear infinite;
}

.marquee-item {
    display: inline-flex;
    flex-shrink: 0;
    padding-right: 1rem;
}

@keyframes neverending-marquee {
    0% {
        transform: translateX(0);
    }

    100% {
        transform: translateX(-50%);
    }
}
@keyframes neverending-loading {
    0% {
        transform: translateX(-100%);
    }

    100% {
        transform: translateX(220%);
    }
}
</style>