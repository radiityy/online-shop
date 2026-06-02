<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';

const faqs = [
    {
        question: 'Bagaimana cara melakukan pembayaran?',
        answer: 'Pembayaran dilakukan melalui transfer manual. Setelah checkout, upload bukti pembayaran di halaman detail pesanan.',
    },
    {
        question: 'Berapa lama pesanan diproses?',
        answer: 'Pesanan diproses setelah pembayaran dikonfirmasi oleh admin. Estimasi proses packing adalah 1-2 hari kerja.',
    },
    {
        question: 'Apakah bisa memilih ukuran?',
        answer: 'Bisa. Setiap produk memiliki pilihan size yang tersedia sesuai stok. Size yang habis akan otomatis disabled.',
    },
    {
        question: 'Bagaimana cara melihat resi?',
        answer: 'Nomor resi akan muncul di halaman detail pesanan setelah admin menginput data pengiriman.',
    },
    {
        question: 'Apakah bisa mengganti alamat setelah checkout?',
        answer: 'Jika pesanan belum diproses, hubungi admin secepatnya. Jika sudah dikirim, alamat tidak dapat diubah.',
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
                    FAQ
                </h1>

                <p class="mt-5 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Pertanyaan umum seputar order, pembayaran, pengiriman, dan produk NEVERENDING.
                </p>
            </section>

            <section class="space-y-4">
                <article
                    v-for="faq in faqs"
                    :key="faq.question"
                    class="border border-neutral-200 bg-white p-6"
                >
                    <h2 class="text-lg font-black uppercase tracking-[-0.02em]">
                        {{ faq.question }}
                    </h2>

                    <p class="mt-3 text-sm leading-7 text-neutral-600">
                        {{ faq.answer }}
                    </p>
                </article>
            </section>
        </main>
    </StoreLayout>
</template>