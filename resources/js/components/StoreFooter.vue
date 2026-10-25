<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { reactive } from 'vue';
import FooterSection from '@/components/FooterSection.vue';
import FooterSocials from '@/components/FooterSocials.vue';

type Logo = {
    key: string;
    label: string;
    src: string;
};

const paymentLogos: Logo[] = [
    { key: 'qris', label: 'QRIS', src: '/images/payments/qris.svg' },
    { key: 'gopay', label: 'GoPay', src: '/images/payments/gopay.svg' },
    { key: 'bca_va', label: 'BCA', src: '/images/payments/bca_va.svg' },
    { key: 'bni_va', label: 'BNI', src: '/images/payments/bni_va.svg' },
    { key: 'bri_va', label: 'BRI', src: '/images/payments/bri_va.svg' },
    { key: 'permata_va', label: 'Permata', src: '/images/payments/permata_va.png' },
];

const shipmentLogos: Logo[] = [
    { key: 'jne', label: 'JNE', src: '/images/couriers/jne.svg' },
    { key: 'jnt', label: 'J&T Express', src: '/images/couriers/jnt.svg' },
    { key: 'sicepat', label: 'SiCepat', src: '/images/couriers/sicepat.svg' },
    { key: 'anteraja', label: 'Anteraja', src: '/images/couriers/anteraja.svg' },
];

const supportLinks = [
    { label: 'Order Status', href: '/orders' },
    { label: 'My Addresses', href: '/account/addresses' },
    { label: 'FAQ', href: '/faq' },
    { label: 'Shipping', href: '/shipping' },
    { label: 'Returns', href: '/returns' },
];

const failed = reactive<Record<string, boolean>>({});
</script>

<template>
    <footer class="bg-neutral-950 text-white">
        <div class="mx-auto max-w-7xl px-6 pt-4 md:grid md:grid-cols-[1.3fr_0.8fr_1.8fr] md:gap-x-12 md:pb-14 md:pt-14">
            <FooterSection title="About Us">
                <p class="max-w-sm text-sm leading-7 text-white/70">
                    Daily wear for endless rotation. Clean pieces built for everyday movement.
                </p>

                <Link
                    href="/about"
                    class="mt-4 inline-block text-sm font-bold underline underline-offset-4 hover:text-white/70"
                >
                    Learn more about us
                </Link>

                <div class="mt-6 hidden md:block">
                    <FooterSocials />
                </div>
            </FooterSection>

            <FooterSection title="Support">
                <div class="space-y-3 text-sm text-white/70">
                    <Link
                        v-for="item in supportLinks"
                        :key="item.label"
                        :href="item.href"
                        class="block hover:text-white"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </FooterSection>

            <div class="md:space-y-10">
                <FooterSection title="Payment Method">
                    <div class="flex flex-wrap items-center gap-x-7 gap-y-5">
                        <template v-for="logo in paymentLogos" :key="logo.key">
                            <img
                                v-if="!failed[logo.key]"
                                :src="logo.src"
                                :alt="logo.label"
                                class="h-6 w-auto max-w-[5.5rem] object-contain brightness-0 invert"
                                loading="lazy"
                                @error="failed[logo.key] = true"
                            />

                            <span v-else class="text-xs font-black uppercase tracking-[0.12em] text-white/70">
                                {{ logo.label }}
                            </span>
                        </template>
                    </div>
                </FooterSection>

                <FooterSection title="Shipment Method">
                    <div class="flex flex-wrap items-center gap-x-7 gap-y-5">
                        <template v-for="logo in shipmentLogos" :key="logo.key">
                            <img
                                v-if="!failed[logo.key]"
                                :src="logo.src"
                                :alt="logo.label"
                                class="h-6 w-auto max-w-[5.5rem] object-contain brightness-0 invert"
                                loading="lazy"
                                @error="failed[logo.key] = true"
                            />

                            <span v-else class="text-xs font-black uppercase tracking-[0.12em] text-white/70">
                                {{ logo.label }}
                            </span>
                        </template>
                    </div>
                </FooterSection>
            </div>

            <div class="border-t border-white/10 py-6 md:hidden">
                <FooterSocials />
            </div>
        </div>

        <div class="border-t border-white/10 px-6 py-5 text-xs text-white/50">
            <div class="mx-auto max-w-7xl">
                © 2026 NEVERENDING. Endless daily wear.
            </div>
        </div>
    </footer>
</template>