<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';

type Section = {
    title: string;
    paragraphs?: string[];
    items?: string[];
};

const sections: Section[] = [
    {
        title: 'Return Window',
        paragraphs: [
            'You have a maximum of 7 working days after receiving your item to submit a return request.',
        ],
    },
    {
        title: 'Product Condition',
        paragraphs: ['To be eligible for a return, the item must be:'],
        items: [
            'In the same condition as when you received it',
            'Unworn and unused',
            'Still carrying its tags',
            'Never washed',
            'In its original packaging',
        ],
    },
    {
        title: 'Unboxing Video',
        paragraphs: [
            'A complete, unedited unboxing video must be included with your request. Requests without one cannot be processed.',
        ],
    },
    {
        title: 'Return Shipping',
        paragraphs: [
            'The round-trip shipping cost of a return is paid by the buyer, usually based on the initial agreement.',
        ],
    },
    {
        title: 'How To Request A Return',
        paragraphs: [
            'Contact us at contact@neverending.com with your order code, the reason for the return and your unboxing video.',
            'If your return is approved, we will send a return shipping label together with instructions and the return address. Items sent back to us without an approved request will not be accepted.',
        ],
    },
    {
        title: 'Damaged, Defective Or Wrong Items',
        paragraphs: [
            'Please check your order as soon as you receive it and contact us right away at contact@neverending.com if an item is defective, damaged, or not the one you ordered, so we can look into it and resolve the issue.',
            'Complaints must be submitted within 7 working days after the package is received, together with a complete unboxing video that shows:',
        ],
        items: [
            'Shipping label and seal: the label is clearly readable and the package is still fully sealed',
            'Opening: recorded from the very first moment the seal is opened, with no edits, cuts or pauses',
            'Product check: everything inside, the physical condition and the function of the item',
        ],
    },
    {
        title: 'Complaints We Cannot Accept',
        paragraphs: ['A complaint is rejected if:'],
        items: [
            'The video is cut or sped up',
            'The shipping label is not readable',
            'The package was opened before recording started',
            'The request is submitted after 7 working days',
        ],
    },
    {
        title: 'Good To Know',
        paragraphs: [
            'Product similarity is 95%–99%. Lighting and photo editing can affect how colors and details look on screen.',
            'Please check your order details before paying. Changes cannot be made once an order is processed.',
        ],
    },
];
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1200px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Support
                </p>

                <h1 class="mt-4 text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                    Returns
                </h1>

                <p class="mt-5 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Our return policy for NEVERENDING products.
                </p>
            </section>

            <section class="space-y-5">
                <div
                    v-for="section in sections"
                    :key="section.title"
                    class="border border-neutral-200 p-6"
                >
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        {{ section.title }}
                    </h2>

                    <p
                        v-for="paragraph in section.paragraphs"
                        :key="paragraph"
                        class="mt-4 text-sm leading-7 text-neutral-600"
                    >
                        {{ paragraph }}
                    </p>

                    <ul
                        v-if="section.items"
                        class="mt-4 list-disc space-y-1 pl-5 text-sm leading-7 text-neutral-600"
                    >
                        <li v-for="item in section.items" :key="item">
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>