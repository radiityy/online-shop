<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { computed, onMounted, ref } from 'vue';

type Address = {
    id: number;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    postal_code: string | null;
    full_address: string;
    is_default: boolean;
};

type AddressForm = {
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    postal_code: string;
    full_address: string;
    is_default: boolean;
};

const props = defineProps<{
    addresses: Address[];
}>();

const editingId = ref<number | null>(null);
const isDeleteModalOpen = ref(false);
const selectedDeleteAddress = ref<Address | null>(null);
const isDeleting = ref(false);

const form = useForm<AddressForm>({
    recipient_name: '',
    phone: '',
    province: '',
    city: '',
    district: '',
    postal_code: '',
    full_address: '',
    is_default: false,
});

const isEditing = computed(() => editingId.value !== null);

const resetForm = () => {
    editingId.value = null;

    form.clearErrors();

    form.recipient_name = '';
    form.phone = '';
    form.province = '';
    form.city = '';
    form.district = '';
    form.postal_code = '';
    form.full_address = '';
    form.is_default = props.addresses.length === 0;
};

const editAddress = (address: Address) => {
    editingId.value = address.id;

    form.clearErrors();

    form.recipient_name = address.recipient_name;
    form.phone = address.phone;
    form.province = address.province;
    form.city = address.city;
    form.district = address.district;
    form.postal_code = address.postal_code ?? '';
    form.full_address = address.full_address;
    form.is_default = address.is_default;

    window.scrollTo({
        top: 0,
        behavior: 'smooth',
    });
};

const submitAddress = () => {
    if (isEditing.value && editingId.value !== null) {
        form.put(`/account/addresses/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });

        return;
    }

    form.post('/account/addresses', {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
};

const openDeleteModal = (address: Address) => {
    selectedDeleteAddress.value = address;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    if (isDeleting.value) {
        return;
    }

    selectedDeleteAddress.value = null;
    isDeleteModalOpen.value = false;
};

const confirmDeleteAddress = () => {
    if (!selectedDeleteAddress.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(`/account/addresses/${selectedDeleteAddress.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            selectedDeleteAddress.value = null;
        },
    });
};

const setDefaultAddress = (address: Address) => {
    router.patch(
        `/account/addresses/${address.id}/default`,
        {},
        {
            preserveScroll: true,
        },
    );
};

onMounted(() => {
    resetForm();
});
</script>

<template>
    <StoreLayout>
        <main class="mx-auto max-w-[1840px] px-6 pb-20 pt-32 sm:px-10 lg:px-[4.5vw]">
            <section class="mb-12">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Account
                </p>

                <div class="mt-4 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-5xl font-black uppercase tracking-[-0.045em] md:text-7xl">
                            My Addresses
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-neutral-600 md:text-base">
                            Manage your delivery addresses for faster NEVERENDING checkout.
                        </p>
                    </div>

                    <Link
                        href="/orders"
                        class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                    >
                        My Orders
                    </Link>
                </div>
            </section>

            <section class="grid gap-10 lg:grid-cols-[1fr_520px]">
                <div>
                    <div
                        v-if="addresses.length"
                        class="space-y-5"
                    >
                        <article
                            v-for="address in addresses"
                            :key="address.id"
                            class="border border-neutral-200 bg-white p-6 transition hover:border-neutral-950"
                        >
                            <div class="flex flex-col justify-between gap-5 md:flex-row md:items-start">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="text-2xl font-black uppercase tracking-[-0.04em]">
                                            {{ address.recipient_name }}
                                        </h2>

                                        <span
                                            v-if="address.is_default"
                                            class="bg-neutral-950 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-white"
                                        >
                                            Default
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm font-bold text-neutral-700">
                                        {{ address.phone }}
                                    </p>

                                    <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-600">
                                        {{ address.full_address }}
                                    </p>

                                    <p class="mt-1 text-sm leading-7 text-neutral-600">
                                        {{ address.district }}, {{ address.city }}, {{ address.province }}
                                        <span v-if="address.postal_code">, {{ address.postal_code }}</span>
                                    </p>
                                </div>

                                <div class="grid gap-3 md:min-w-[180px]">
                                    <button
                                        type="button"
                                        class="border border-neutral-950 bg-white px-5 py-3 text-xs font-black uppercase tracking-[0.18em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                                        @click="editAddress(address)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        v-if="!address.is_default"
                                        type="button"
                                        class="border border-neutral-300 bg-white px-5 py-3 text-xs font-black uppercase tracking-[0.18em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950"
                                        @click="setDefaultAddress(address)"
                                    >
                                        Set Default
                                    </button>

                                    <button
                                        type="button"
                                        class="border border-red-200 bg-white px-5 py-3 text-xs font-black uppercase tracking-[0.18em] text-red-600 transition hover:bg-red-50"
                                        @click="openDeleteModal(address)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="border border-dashed border-neutral-300 p-14 text-center"
                    >
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                            No Address
                        </p>

                        <h2 class="mt-4 text-4xl font-black uppercase tracking-[-0.04em]">
                            Add your first address
                        </h2>

                        <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-neutral-600">
                            Save your delivery details so your next checkout feels faster and cleaner.
                        </p>
                    </div>
                </div>

                <aside class="h-fit border border-neutral-200 bg-neutral-50 p-6 lg:sticky lg:top-28">
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                        {{ isEditing ? 'Edit Address' : 'New Address' }}
                    </p>

                    <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                        {{ isEditing ? 'Update Address' : 'Add Address' }}
                    </h2>

                    <form
                        class="mt-8 grid gap-5"
                        @submit.prevent="submitAddress"
                    >
                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Recipient Name
                            </label>

                            <input
                                v-model="form.recipient_name"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Full name"
                            />

                            <p
                                v-if="form.errors.recipient_name"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.recipient_name }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Phone
                            </label>

                            <input
                                v-model="form.phone"
                                type="text"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="08xxxxxxxxxx"
                            />

                            <p
                                v-if="form.errors.phone"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                    Province
                                </label>

                                <input
                                    v-model="form.province"
                                    type="text"
                                    class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                    placeholder="Banten"
                                />

                                <p
                                    v-if="form.errors.province"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.province }}
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                    City
                                </label>

                                <input
                                    v-model="form.city"
                                    type="text"
                                    class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                    placeholder="Tangerang"
                                />

                                <p
                                    v-if="form.errors.city"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.city }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                    District
                                </label>

                                <input
                                    v-model="form.district"
                                    type="text"
                                    class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                    placeholder="Karawaci"
                                />

                                <p
                                    v-if="form.errors.district"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.district }}
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                    Postal Code
                                </label>

                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                    placeholder="151xx"
                                />

                                <p
                                    v-if="form.errors.postal_code"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.postal_code }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-[0.18em] text-neutral-500">
                                Full Address
                            </label>

                            <textarea
                                v-model="form.full_address"
                                rows="4"
                                class="mt-2 w-full border border-neutral-300 bg-white px-4 py-4 text-sm outline-none transition focus:border-neutral-950"
                                placeholder="Street, house number, building detail..."
                            ></textarea>

                            <p
                                v-if="form.errors.full_address"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ form.errors.full_address }}
                            </p>
                        </div>

                        <label class="flex items-center gap-3 border border-neutral-200 bg-white p-4">
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="h-4 w-4 rounded-none border-neutral-300 text-neutral-950"
                            />

                            <span class="text-sm font-bold text-neutral-700">
                                Set as default address
                            </span>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                        >
                            {{ form.processing ? 'Saving...' : isEditing ? 'Update Address' : 'Save Address' }}
                        </button>

                        <button
                            v-if="isEditing"
                            type="button"
                            class="w-full border border-neutral-950 bg-white px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                            @click="resetForm"
                        >
                            Cancel Edit
                        </button>
                    </form>
                </aside>
            </section>
        </main>

        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-[120] flex items-center justify-center bg-black/60 px-6 backdrop-blur-sm"
            @click.self="closeDeleteModal"
        >
            <div class="w-full max-w-md border border-neutral-200 bg-white p-7 shadow-2xl">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-neutral-500">
                    Delete Address
                </p>

                <h2 class="mt-3 text-3xl font-black uppercase tracking-[-0.04em]">
                    Remove this address?
                </h2>

                <p class="mt-4 text-sm leading-7 text-neutral-600">
                    This action cannot be undone. The selected delivery address will be removed from your account.
                </p>

                <div
                    v-if="selectedDeleteAddress"
                    class="mt-5 border border-neutral-200 bg-neutral-50 p-4 text-sm leading-7 text-neutral-600"
                >
                    <p class="font-black uppercase tracking-[0.12em] text-neutral-950">
                        {{ selectedDeleteAddress.recipient_name }}
                    </p>

                    <p class="mt-1">
                        {{ selectedDeleteAddress.phone }}
                    </p>

                    <p class="mt-3">
                        {{ selectedDeleteAddress.full_address }}
                    </p>

                    <p>
                        {{ selectedDeleteAddress.district }},
                        {{ selectedDeleteAddress.city }},
                        {{ selectedDeleteAddress.province }}
                        <span v-if="selectedDeleteAddress.postal_code">
                            , {{ selectedDeleteAddress.postal_code }}
                        </span>
                    </p>
                </div>

                <div class="mt-7 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="border border-neutral-950 bg-white px-6 py-4 text-xs font-black uppercase tracking-[0.22em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white"
                        :disabled="isDeleting"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="bg-red-600 px-6 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300"
                        :disabled="isDeleting"
                        @click="confirmDeleteAddress"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </StoreLayout>
</template>