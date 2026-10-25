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
                    How NEVERENDING orders are processed and delivered.
                </p>
            </section>

            <section class="grid gap-6 md:grid-cols-2">
                <div class="border border-neutral-200 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Couriers
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        JNE / J&amp;T / SiCepat / Anteraja
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        We ship within Indonesia. The services available to you, their prices and delivery estimates are
                        calculated at checkout from your saved address and the weight of your order.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Processing Time
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        1–2 Working Days
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Orders are processed after your payment is confirmed. Processing can take longer during campaigns
                        and sales. Delivery estimates shown at checkout start from dispatch and do not include processing time.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Insurance
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        Included On Every Order
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Shipping insurance is added to every order and shown as a separate line in your order summary.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">
                        Tracking
                    </p>

                    <h2 class="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">
                        Track Your Order
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Once your order has shipped, the courier, service, tracking number and delivery status appear on
                        your order page.
                    </p>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>