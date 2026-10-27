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
        question: 'How do I receive a refund if I paid by Virtual Account?',
        answer: [
            'Refunds for Virtual Account payments are sent to your bank account, so we need your bank details. When a refund is in progress, open your order page and fill in your account number and the account holder name.',
            'Your details are stored encrypted, and the order page only shows the last four digits of the account number. The data is deleted once the refund is completed.',
            'If the details are still missing, you will get a daily reminder until you fill them in. Refunds for other payment methods are returned automatically to the original payment method and need no extra step.',
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
            'Please check your order details carefully before paying. Changes cannot be made once the order is processed.',
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
            'You have a maximum of 7 working days after receiving your item to submit a return request.',
            'To be eligible, the item must be in the same condition as when you received it: unworn and unused, with the tags still attached, never washed, and in its original packaging. An unboxing video must be included.',
            'Return shipping is paid by the buyer. The round-trip shipping cost is the customer’s responsibility, usually based on the initial agreement.',
            'To request a return, contact us at contact@neverending.com. If your return is approved, we will send a return shipping label together with instructions and the return address. Items sent back to us without an approved request will not be accepted.',
            'See the Returns page for the full conditions.',
        ],
    },
    {
        question: 'My item is damaged, defective or wrong. What should I do?',
        answer: [
            'Please check your order as soon as you receive it and contact us right away at contact@neverending.com if an item is defective, damaged, or not the one you ordered, so we can look into it and resolve the issue.',
            'Complaints must be submitted within 7 working days after the package is received.',
            'A complete, unedited unboxing video is required (no edits, cuts or pauses):',
            '1. Shipping label and seal: the shipping label is clearly readable and the package is still fully sealed.',
            '2. Opening: recorded from the very first moment the seal is opened.',
            '3. Product check: shows everything inside, the physical condition and the function of the item.',
            'A complaint is rejected if the video is cut or sped up, the shipping label is not readable, the package was opened before recording, or the request is submitted after 7 working days.',
        ],
    },
    {
        question: 'How closely do products match the photos?',
        answer: [
            'Product similarity is 95%–99%. Lighting and photo editing can affect how colors and details look on screen.',
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
                    Answers about ordering, payment, shipping, refunds and returns.
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