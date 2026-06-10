<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1200px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Support
                </p>

                <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                    Shipping
                </h1>

                <p class="mt-5 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Informasi pengiriman pesanan NEVERENDING.
                </p>
            </section>

            <section class="grid gap-6 md:grid-cols-2">
                <div class="border border-neutral-200 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Courier
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        JNE / J&T / SiCepat / Anteraja
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Pengiriman dilakukan menggunakan ekspedisi yang tersedia. Nomor resi akan diberikan setelah pesanan dikirim.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Processing Time
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        1-2 Hari Kerja
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Pesanan diproses setelah pembayaran dikonfirmasi. Estimasi dapat berubah saat periode campaign atau sale.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6 md:col-span-2">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Tracking
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        Track Your Order
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Customer dapat melihat courier, service, nomor resi, dan status pengiriman melalui halaman detail pesanan.
                    </p>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>