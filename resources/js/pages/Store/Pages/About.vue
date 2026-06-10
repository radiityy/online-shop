<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
</script>

<template>
    <StoreLayout>
        <main class="bg-white text-neutral-950">
            <section class="mx-auto max-w-[1840px] px-6 pb-16 pt-32 sm:px-10 lg:px-[4.5vw]">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    About Us
                </p>

                <div class="mt-5 grid gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase leading-[0.92] tracking-[-0.055em] md:text-7xl lg:text-8xl">
                            Daily Wear For Endless Rotation.
                        </h1>
                    </div>

                    <div>
                        <p class="text-sm leading-7 text-neutral-600 md:text-base">
                            NEVERENDING is an independent apparel brand from Yogyakarta, Indonesia, founded by radiityy in 2026.
                            Built around the idea of endless rotation, the brand creates clean daily pieces designed to stay relevant
                            throughout everyday life.
                        </p>

                        <Link
                            href="/products"
                            class="mt-8 inline-flex bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                        >
                            Explore Collection
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-[1840px] px-6 pb-20 sm:px-10 lg:px-[4.5vw]">
                <div class="border-y border-neutral-200 py-14">
                    <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                                Brand Story
                            </p>

                            <h2 class="mt-4 text-4xl font-black uppercase leading-tight tracking-[-0.04em] md:text-6xl">
                                Not just made to be worn once.
                            </h2>
                        </div>

                        <div class="space-y-6 text-sm leading-8 text-neutral-600 md:text-base">
                            <p>
                                NEVERENDING is an independent apparel brand from Yogyakarta, Indonesia, founded by Radiityy in 2026.
                                The brand was created from a simple belief: the best clothes are not the ones worn once for a moment,
                                but the ones that remain relevant throughout everyday life.
                            </p>

                            <p>
                                Rather than chasing short-lived trends, NEVERENDING focuses on creating pieces that feel natural to wear,
                                easy to style, and comfortable to return to again and again. Each product is designed with a balance of
                                clean aesthetics, practical function, and a contemporary attitude, allowing it to fit naturally into
                                different routines, environments, and personal styles.
                            </p>

                            <p>
                                The name NEVERENDING reflects the brand’s perspective on clothing as an ongoing part of daily expression.
                                A wardrobe should evolve with the person wearing it, not be limited by seasons or temporary hype.
                                For that reason, every collection is developed with longevity in mind, prioritizing versatility,
                                wearability, and a timeless visual direction.
                            </p>

                            <p>
                                Through its products and growing community, NEVERENDING aims to become more than a clothing label.
                                It represents a mindset of consistency, self-expression, and everyday confidence, creating essentials
                                that remain relevant through changing moments while continuing to earn their place in the rotation.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid max-w-[1840px] gap-6 px-6 pb-20 sm:px-10 md:grid-cols-3 lg:px-[4.5vw]">
                <div class="border border-neutral-200 p-7">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                        01
                    </p>

                    <h2 class="mt-5 text-2xl font-black uppercase tracking-[-0.03em]">
                        Clean Direction
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        A minimal visual direction built around clean shapes, balanced details, and pieces that feel easy to wear repeatedly.
                    </p>
                </div>

                <div class="border border-neutral-200 p-7">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                        02
                    </p>

                    <h2 class="mt-5 text-2xl font-black uppercase tracking-[-0.03em]">
                        Daily Function
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Designed to move through everyday routines, from campus, work, casual plans, and everything in between.
                    </p>
                </div>

                <div class="border border-neutral-200 p-7">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-400">
                        03
                    </p>

                    <h2 class="mt-5 text-2xl font-black uppercase tracking-[-0.03em]">
                        Endless Rotation
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Pieces made to stay in the wardrobe, return in different looks, and keep earning their place in daily rotation.
                    </p>
                </div>
            </section>

            <section class="bg-neutral-950 px-6 py-20 text-white sm:px-10 lg:px-[4.5vw]">
                <div class="mx-auto grid max-w-[1840px] gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-white/50">
                            NEVERENDING
                        </p>

                        <h2 class="mt-4 text-4xl font-black uppercase leading-tight tracking-[-0.04em] md:text-6xl">
                            Quiet pieces. Strong presence.
                        </h2>
                    </div>

                    <div class="space-y-6 text-sm leading-8 text-white/70 md:text-base">
                        <p>
                            We are not trying to make clothes that only look good for a moment.
                            We are building daily pieces that can stay in your rotation again and again.
                        </p>

                        <p>
                            We make clothes that are easy to wear, easy to repeat, and still feel right after many wears. Clean shapes, comfortable fits, and everyday pieces that can keep coming back into your rotation.
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-[1840px] px-6 py-20 sm:px-10 lg:px-[4.5vw]">
                <div class="border border-neutral-200 p-8 md:p-12">
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                        Start Your Rotation
                    </p>

                    <div class="mt-5 flex flex-col justify-between gap-8 md:flex-row md:items-end">
                        <h2 class="max-w-3xl text-4xl font-black uppercase leading-tight tracking-[-0.04em] md:text-6xl">
                            Find your next daily piece.
                        </h2>

                        <Link
                            href="/products"
                            class="inline-flex justify-center bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                        >
                            Shop Now
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>