<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type PageProps = {
    auth?: {
        user?: unknown;
    };
};

const page = usePage();

const isLoggedIn = computed(() => Boolean((page.props as PageProps).auth?.user));

const isShopOpen = ref(false);
const isAccountOpen = ref(false);
const isMobileMenuOpen = ref(false);
const isScrolled = ref(false);

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

const handleScroll = () => {
    isScrolled.value = window.scrollY > 40;
};

const headerClass = computed(() => {
    if (isScrolled.value || isMobileMenuOpen.value) {
        return 'top-0 border-b border-neutral-200 bg-white/95 text-neutral-950 shadow-sm backdrop-blur-xl';
    }

    return 'top-[28px] border-b border-white/10 bg-transparent text-white';
});

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-950">
        <div
            v-show="!isScrolled && !isMobileMenuOpen"
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
            <!-- MOBILE HEADER -->
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

                <Link href="/cart" aria-label="Cart" class="justify-self-end" @click="closeMenus">
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
                </Link>
            </div>

            <!-- DESKTOP HEADER -->
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
                            class="absolute left-0 top-full z-50 mt-5 w-[680px] border border-neutral-200 bg-white p-7 text-neutral-950 shadow-2xl"
                        >
                            <div class="grid grid-cols-3 gap-8">
                                <div>
                                    <p class="mb-4 text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                        Shop
                                    </p>

                                    <div class="space-y-3 text-sm font-semibold normal-case tracking-normal">
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            New Arrivals
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            All Products
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Tees
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Hoodies
                                        </Link>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-4 text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                        Clothing
                                    </p>

                                    <div class="space-y-3 text-sm font-semibold normal-case tracking-normal">
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Longsleeve
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Shirts
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Outerwear
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Pants
                                        </Link>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-4 text-[11px] font-black uppercase tracking-[0.22em] text-neutral-400">
                                        Accessories
                                    </p>

                                    <div class="space-y-3 text-sm font-semibold normal-case tracking-normal">
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Headwear
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Bags
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Socks
                                        </Link>
                                        <Link href="/products" class="block hover:text-neutral-500" @click="closeMenus">
                                            Jewelry
                                        </Link>
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
                    <Link href="/products" aria-label="Search" class="transition hover:opacity-60">
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
                    </Link>

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
                            class="absolute right-0 top-full z-50 mt-5 w-56 border border-neutral-200 bg-white p-5 text-neutral-950 shadow-2xl"
                        >
                            <div class="space-y-3 text-sm font-semibold">
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

                    <Link href="/cart" aria-label="Cart" class="transition hover:opacity-60" @click="closeMenus">
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
                    </Link>
                </div>
            </div>

            <!-- MOBILE MENU -->
            <div
                v-if="isMobileMenuOpen"
                class="max-h-[calc(100vh-72px)] overflow-y-auto border-t border-neutral-200 bg-white px-6 py-7 text-neutral-950 shadow-2xl lg:hidden"
            >
                <div class="space-y-7">
                    <div>
                        <p class="mb-4 text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                            Shop
                        </p>

                        <div class="grid grid-cols-2 gap-4 text-base font-bold">
                            <Link href="/products" @click="closeMenus">New Arrivals</Link>
                            <Link href="/products" @click="closeMenus">All Products</Link>
                            <Link href="/products" @click="closeMenus">Tees</Link>
                            <Link href="/products" @click="closeMenus">Hoodies</Link>
                            <Link href="/products" @click="closeMenus">Outerwear</Link>
                            <Link href="/products" @click="closeMenus">Pants</Link>
                            <Link href="/products" @click="closeMenus">Headwear</Link>
                            <Link href="/products" @click="closeMenus">Bags</Link>
                        </div>
                    </div>

                    <div class="border-t border-neutral-200 pt-6">
                        <div class="space-y-4 text-base font-black uppercase tracking-[0.16em]">
                            <Link href="/products" class="block" @click="closeMenus">Shop</Link>
                            <Link href="/products" class="block" @click="closeMenus">Collection</Link>
                            <Link href="/products" class="block" @click="closeMenus">Sale</Link>
                            <Link href="/products" class="block" @click="closeMenus">Campaign</Link>
                            <Link href="/cart" class="block" @click="closeMenus">Cart</Link>

                            <Link
                                v-if="isLoggedIn"
                                href="/orders"
                                class="block"
                                @click="closeMenus"
                            >
                                Orders
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
                        <Link href="/products" class="block hover:text-white">New Arrivals</Link>
                        <Link href="/products" class="block hover:text-white">All Products</Link>
                        <Link href="/products" class="block hover:text-white">Clothing</Link>
                        <Link href="/products" class="block hover:text-white">Accessories</Link>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-black uppercase tracking-[0.25em]">
                        Support
                    </h3>

                    <div class="mt-5 space-y-3 text-sm text-white/60">
                        <Link href="/orders" class="block hover:text-white">Order Status</Link>
                        <a href="#" class="block hover:text-white">FAQ</a>
                        <a href="#" class="block hover:text-white">Shipping</a>
                        <a href="#" class="block hover:text-white">Returns</a>
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
</style>