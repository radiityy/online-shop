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
                    Returns
                </h1>

                <p class="mt-5 max-w-2xl text-sm leading-7 text-neutral-600 md:text-base">
                    Kebijakan pengembalian dan penukaran produk NEVERENDING.
                </p>
            </section>

            <section class="space-y-5">
                <div class="border border-neutral-200 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        Syarat Pengembalian
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Produk dapat diajukan untuk retur jika terdapat kesalahan produk, cacat produksi, atau ukuran tidak sesuai dengan pesanan.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        Kondisi Produk
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Produk harus belum digunakan, belum dicuci, tag masih terpasang, dan dikirim kembali dengan kondisi baik.
                    </p>
                </div>

                <div class="border border-neutral-200 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-[-0.03em]">
                        Hubungi Admin
                    </h2>

                    <p class="mt-4 text-sm leading-7 text-neutral-600">
                        Ajukan retur dengan menyertakan order code, foto produk, dan alasan pengembalian kepada admin NEVERENDING.
                    </p>
                </div>
            </section>
        </main>
    </StoreLayout>
</template>