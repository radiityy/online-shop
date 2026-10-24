<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type PageProps = {
    cart_count?: number;
    unreadNotificationsCount?: number;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

const page = usePage();

const flashSuccess = computed(() => (page.props as PageProps).flash?.success);
const flashError = computed(() => (page.props as PageProps).flash?.error);
const cartCount = computed(() => Number((page.props as PageProps).cart_count ?? 0));
const unreadCount = computed(() => Number((page.props as PageProps).unreadNotificationsCount ?? 0));

const showFlashSuccess = ref(false);
const showFlashError = ref(false);
const isPageLoading = ref(false);

let flashTimer: number | undefined;
let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;

const badgeLabel = (count: number) => (count > 99 ? '99+' : String(count));

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
    () => (page.props as PageProps).flash,
    (flash) => {
        if (flash?.success) {
            showFlashSuccess.value = true;
            resetFlashTimer();
        }

        if (flash?.error) {
            showFlashError.value = true;
            resetFlashTimer();
        }
    },
    { immediate: true },
);

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isPageLoading.value = true;
    });

    removeFinishListener = router.on('finish', () => {
        isPageLoading.value = false;
    });
});

onBeforeUnmount(() => {
    removeStartListener?.();
    removeFinishListener?.();

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
            <div class="h-full w-1/2 animate-[checkout-loading_1s_ease-in-out_infinite] bg-neutral-950"></div>
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

        <header class="sticky top-0 z-50 border-b border-neutral-200 bg-white">
            <div class="relative mx-auto flex h-16 max-w-7xl items-center justify-between px-6 md:h-20">
                <Link
                    href="/cart"
                    aria-label="Back to cart"
                    class="-ml-2 flex h-10 w-10 items-center justify-center text-neutral-950 transition hover:opacity-60"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M19 12H5" />
                        <path d="M12 19l-7-7 7-7" />
                    </svg>
                </Link>

                <Link
                    href="/"
                    class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-base font-black uppercase tracking-[0.24em] md:text-[24px] md:tracking-[0.34em]"
                >
                    NEVERENDING
                </Link>

                <div class="flex items-center gap-2 md:gap-4">
                    <Link
                        href="/notifications"
                        aria-label="Notifications"
                        class="relative hidden h-10 w-10 items-center justify-center text-neutral-950 transition hover:opacity-60 md:flex"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                        </svg>

                        <span
                            v-if="unreadCount > 0"
                            class="absolute right-0 top-0 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-neutral-950 px-1 text-[10px] font-bold text-white"
                        >
                            {{ badgeLabel(unreadCount) }}
                        </span>
                    </Link>

                    <Link
                        href="/account"
                        aria-label="Account"
                        class="hidden h-10 w-10 items-center justify-center text-neutral-950 transition hover:opacity-60 md:flex"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </Link>

                    <Link
                        href="/cart"
                        aria-label="Cart"
                        class="relative -mr-2 flex h-10 w-10 items-center justify-center text-neutral-950 transition hover:opacity-60 md:mr-0"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="8" cy="21" r="1" />
                            <circle cx="19" cy="21" r="1" />
                            <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
                        </svg>

                        <span
                            v-if="cartCount > 0"
                            class="absolute right-0 top-0 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-neutral-950 px-1 text-[10px] font-bold text-white"
                        >
                            {{ badgeLabel(cartCount) }}
                        </span>
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>

<style>
@keyframes checkout-loading {
    0% {
        transform: translateX(-100%);
    }

    100% {
        transform: translateX(220%);
    }
}
</style>