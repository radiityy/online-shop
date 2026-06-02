<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type PageProps = {
    auth?: {
        user?: unknown;
    };
    cart_count?: number;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
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
const cartCount = computed(() => Number((page.props as PageProps).cart_count ?? 0));

const isShopOpen = ref(false);
const isAccountOpen = ref(false);
const isMobileMenuOpen = ref(false);
const isScrolled = ref(false);
const isSearchOpen = ref(false);
const searchKeyword = ref('');
const isPageLoading = ref(false);

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

const closeMenus = () => {
    isShopOpen.value = false;
    isAccountOpen.value = false;
    isMobileMenuOpen.value = false;
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
    isShopOpen.value = false;
    isAccountOpen.value = false;
};

const openSearch = () => {
    isShopOpen.value = false;
    isAccountOpen.value = false;
    isMobileMenuOpen.value = false;

    handleScroll();

    const params = new URLSearchParams(window.location.search);
    searchKeyword.value = params.get('search') ?? '';
    isSearchOpen.value = true;

    setTimeout(() => {
        document.getElementById('store-search-input')?.focus();
    }, 50);
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
    if (event.key === 'Escape') {
        closeSearch();
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
        !isMobileMenuOpen.value &&
        !isSearchOpen.value
    );
});

const showAnnouncement = computed(() => {
    return (
        props.transparentHeader === true &&
        !isScrolled.value &&
        !isMobileMenuOpen.value &&
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
});
    router.on('start', () => {
        isPageLoading.value = true;
    });

    router.on('finish', () => {
        isPageLoading.value = false;
    });

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('keydown', handleKeydown);
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
            v-if="flashSuccess"
            class="fixed right-5 top-24 z-[100] max-w-sm border border-green-200 bg-green-50 px-5 py-4 text-sm font-bold text-green-700 shadow-xl"
        >
            {{ flashSuccess }}
        </div>

        <div
            v-if="flashError"
            class="fixed right-5 top-24 z-[100] max-w-sm border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-700 shadow-xl"
        >
            {{ flashError }}
        </div>

        <div
            v-show="showAnnouncement"
            class="fixed left-0 right-0 top-0 z-[60] overflow-hidden bg-neutral-950 py-1.5 text-white transition duration-300"
        >
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

        <header
            class="fixed left-0 right-0 z-50 transition-all duration-300"
            :class="headerClass"
        >
            <div class="grid grid-cols-[auto_1fr_auto] items-center px-5 py-4 lg:hidden">
                <button
                    type="button"
                    class="flex items-center justify-self-start"
                    aria-label="Open menu"
                    @click="toggleMobileMenu"
                >
                    <svg
                        v-if="!isMobileMenuOpen"
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

                    <svg
                        v-else
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
                    class="justify-self-center text-lg font-black uppercase tracking-[0.28em]"
                    @click="closeMenus"
                >
                    NEVERENDING
                </Link>

                <Link
                    href="/cart"
                    aria-label="Cart"
                    class="relative inline-flex items-center justify-center justify-self-end"
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

                    <div class="relative">
                        <button
                            type="button"
                            aria-label="Account"
                            class="flex items-center transition hover:opacity-60"
                            @click="isAccountOpen = !isAccountOpen"
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
                                    Akun Saya
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/orders"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Riwayat Pesanan
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/account/addresses"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Alamat Saya
                                </Link>

                                <Link
                                    v-if="isLoggedIn"
                                    href="/cart"
                                    class="block hover:text-neutral-500"
                                    @click="closeMenus"
                                >
                                    Keranjang
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
                                    Keluar
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

            <div
                v-if="isMobileMenuOpen"
                class="max-h-[calc(100vh-72px)] overflow-y-auto border-t border-neutral-200 bg-white px-6 py-7 text-neutral-950 shadow-2xl lg:hidden"
            >
                <div class="space-y-7">
                    <div
                        v-for="group in menuGroups"
                        :key="group.title"
                    >
                        <p class="mb-4 text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                            {{ group.title }}
                        </p>

                        <div class="grid grid-cols-2 gap-4 text-base font-bold">
                            <Link
                                v-for="item in group.items"
                                :key="item.label"
                                :href="item.href"
                                @click="closeMenus"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 pt-6">
                        <div class="space-y-4 text-base font-black uppercase tracking-[0.16em]">
                            <Link href="/products" class="block" @click="closeMenus">
                                Shop
                            </Link>

                            <button
                                type="button"
                                class="block text-left"
                                @click="openSearch"
                            >
                                Search
                            </button>

                            <Link href="/products" class="block" @click="closeMenus">
                                Collection
                            </Link>

                            <Link href="/products" class="block" @click="closeMenus">
                                Sale
                            </Link>

                            <Link href="/products" class="block" @click="closeMenus">
                                Campaign
                            </Link>

                            <Link href="/cart" class="block" @click="closeMenus">
                                Cart
                            </Link>

                            <Link
                                v-if="isLoggedIn"
                                href="/account"
                                class="block"
                                @click="closeMenus"
                            >
                                Account
                            </Link>

                            <Link
                                v-if="isLoggedIn"
                                href="/orders"
                                class="block"
                                @click="closeMenus"
                            >
                                Orders
                            </Link>

                            <Link
                                v-if="isLoggedIn"
                                href="/account/addresses"
                                class="block"
                                @click="closeMenus"
                            >
                                Addresses
                            </Link>

                            <Link
                                v-if="!isLoggedIn"
                                href="/login"
                                class="block"
                                @click="closeMenus"
                            >
                                Login
                            </Link>

                            <Link
                                v-if="!isLoggedIn"
                                href="/register"
                                class="block"
                                @click="closeMenus"
                            >
                                Register
                            </Link>

                            <Link
                                v-if="isLoggedIn"
                                href="/logout"
                                method="post"
                                as="button"
                                class="block text-left"
                                @click="closeMenus"
                            >
                                Keluar
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

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
                        class="min-h-14 flex-1 border border-neutral-300 bg-white px-5 text-base font-bold outline-none transition focus:border-neutral-950"
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

        <footer class="border-t border-neutral-200 bg-neutral-950 text-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-[1.5fr_1fr_1fr_1fr]">
                <div>
                    <h2 class="text-2xl font-black uppercase tracking-[0.22em]">
                        NEVERENDING
                    </h2>

                    <p class="mt-5 max-w-sm text-sm leading-7 text-white/60">
                        Daily wear for endless rotation. Clean pieces built for everyday movement.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-black uppercase tracking-[0.25em]">
                        Shop
                    </h3>

                    <div class="mt-5 space-y-3 text-sm text-white/60">
                        <Link href="/products" class="block hover:text-white">
                            New Arrivals
                        </Link>

                        <Link href="/products" class="block hover:text-white">
                            All Products
                        </Link>

                        <Link href="/products?category=tees" class="block hover:text-white">
                            Tees
                        </Link>

                        <Link href="/products?category=hoodies" class="block hover:text-white">
                            Hoodies
                        </Link>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-black uppercase tracking-[0.25em]">
                        Support
                    </h3>

                    <div class="mt-5 space-y-3 text-sm text-white/60">
                        <Link href="/orders" class="block hover:text-white">
                            Order Status
                        </Link>

                        <Link href="/account/addresses" class="block hover:text-white">
                            My Addresses
                        </Link>

                        <a href="#" class="block hover:text-white">
                            FAQ
                        </a>

                        <a href="#" class="block hover:text-white">
                            Shipping
                        </a>

                        <a href="#" class="block hover:text-white">
                            Returns
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-black uppercase tracking-[0.25em]">
                        Payment & Shipping
                    </h3>

                    <div class="mt-5 space-y-3 text-sm text-white/60">
                        <p>BCA / Mandiri / BRI</p>
                        <p>QRIS / E-Wallet</p>
                        <p>JNE / J&T / SiCepat</p>
                        <p>Anteraja</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 px-6 py-5 text-center text-xs uppercase tracking-[0.25em] text-white/40">
                © 2026 NEVERENDING. Endless daily wear.
            </div>
        </footer>
    </div>
</template>

<style scoped>
.marquee-track {
    display: flex;
    width: max-content;
    animation: neverending-marquee 24s linear infinite;
}

.marquee-item {
    flex-shrink: 0;
    padding-right: 3rem;
    white-space: nowrap;
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