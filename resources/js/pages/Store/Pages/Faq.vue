<script setup lang="ts">
import { reactive } from 'vue';
import StoreLayout from '@/layouts/StoreLayout.vue';

type Faq = {
    question: string;
    answer: string[];
};

const faqs: Faq[] = [
    {
        question: 'How do I place an order?',
        answer: [
            '1. Browse the store and open a product.',
            '2. Pick your size and add it to your bag.',
            '3. Go to checkout, choose a saved delivery address and a shipping service.',
            '4. Choose a payment method and place your order.',
            '5. Pay before the time limit shown on your order page.',
            'You need an account to check out, and at least one saved address.',
        ],
    },
    {
        question: 'What payment methods do you accept?',
        answer: [
            'QRIS (works with OVO, DANA, ShopeePay and other QRIS-enabled apps), GoPay, and Virtual Account transfer through BCA, BNI, BRI and Permata.',
            'A small payment fee may apply depending on the method. It is shown at checkout and included in your total before you pay.',
        ],
    },
    {
        question: 'How long do I have to pay?',
        answer: [
            'You have 60 minutes from placing the order. The deadline and a countdown are shown on your order page.',
            'If the time runs out, the order is cancelled automatically and the items are released back to stock.',
        ],
    },
    {
        question: 'Can I change my payment method or cancel an unpaid order?',
        answer: [
            'Yes. While an order is unpaid you can switch to another payment method with “Change payment method” on your order page. The payment deadline stays the same, and you cannot switch when only a couple of minutes are left.',
            'You can also cancel an unpaid order yourself. A cancelled order can be re-ordered from your cart.',
        ],
    },
    {
        question: 'I paid, but my order still shows as unpaid.',
        answer: [
            'Payments are confirmed automatically. If your order page has not updated after you paid, press “I have paid” on the order page to check the payment again.',
            'If you paid after the deadline, or paid the same order twice, the extra amount is refunded. Your order page shows “Refund in progress” and you will receive a notification.',
        ],
    },
    {
        question: 'How long does it take to process my order?',
        answer: [
            'Orders are processed within 1–2 working days after your payment is confirmed. Processing can take longer during campaigns and sales.',
        ],
    },
    {
        question: 'How do I track my package?',
        answer: [
            'Open your order from Order Status. Once the order has shipped, the courier, service, tracking number and delivery status appear on the order page.',
        ],
    },
    {
        question: 'Which couriers do you use?',
        answer: [
            'JNE, J&T Express, SiCepat and Anteraja. The available services and their prices are calculated at checkout based on your address and the weight of your order.',
            'Shipping insurance is included on every order and shown as a separate line in your order summary.',
        ],
    },
    {
        question: 'Can I change my address after checkout?',
        answer: [
            'No. Addresses cannot be edited once an order is placed. If your order is still unpaid, cancel it and place a new one with the correct address. Once an order is paid it goes into processing and the address can no longer be changed.',
        ],
    },
    {
        question: 'How do I choose my size?',
        answer: [
            'Each product page lists the sizes in stock. Sizes that are sold out are disabled and cannot be added to your bag.',
        ],
    },
    {
        question: 'What is your return policy?',
        answer: [
            'Items can be returned if you received the wrong product, a defective product, or a size that does not match your order. The buyer pays the return shipping.',
            'See the Returns page for the full conditions.',
        ],
    },
];

const open = reactive<Record<number, boolean>>({});

const toggle = (index: number) => {
    open[index] = !open[index];
};
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1200px] px-6 pb-24 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                    FAQ
                </h1>

                <p class="mt-5 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Answers about ordering, payment, shipping and returns.
                </p>
            </section>

            <section class="border-t border-neutral-200">
                <article
                    v-for="(faq, index) in faqs"
                    :key="faq.question"
                    class="border-b border-neutral-200"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-6 py-5 text-left"
                        :aria-expanded="Boolean(open[index])"
                        :aria-controls="`faq-panel-${index}`"
                        @click="toggle(index)"
                    >
                        <h2 class="text-xs font-black uppercase tracking-[0.12em] md:text-sm">
                            {{ faq.question }}
                        </h2>

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 shrink-0 transition-transform"
                            :class="open[index] ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <div
                        v-show="open[index]"
                        :id="`faq-panel-${index}`"
                        class="max-w-3xl space-y-2 pb-7 text-sm leading-7 text-neutral-600"
                    >
                        <p v-for="line in faq.answer" :key="line">
                            {{ line }}
                        </p>
                    </div>
                </article>
            </section>
        </main>
    </StoreLayout>
</template>