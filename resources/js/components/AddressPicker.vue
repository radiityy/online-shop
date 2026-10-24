<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import AreaSearch, { type Area } from '@/components/AreaSearch.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type Address = {
    id: number;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    district: string;
    area_id: string | null;
    area_name: string | null;
    postal_code: string | null;
    full_address: string;
    is_default: boolean;
};

type AddressForm = {
    recipient_name: string;
    phone: string;
    area_id: string;
    postal_code: string;
    full_address: string;
    is_default: boolean;
};

const props = defineProps<{
    open: boolean;
    addresses: Address[];
    selectedId: number | null;
}>();

const emit = defineEmits<{
    close: [];
    select: [address: Address];
}>();

const minAddressLength = 10;
const maxAddressLength = 250;

const labelClass = 'text-xs font-black uppercase tracking-[0.18em] text-neutral-500';
const inputBase = 'mt-2 w-full border bg-white px-4 py-4 text-sm outline-none transition';

const view = ref<'list' | 'form'>('list');
const editingId = ref<number | null>(null);
const confirmDeleteId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const areaKey = ref(0);
const initialAreaName = ref<string | null>(null);
const addressTouched = ref(false);

const form = useForm<AddressForm>({
    recipient_name: '',
    phone: '',
    area_id: '',
    postal_code: '',
    full_address: '',
    is_default: false,
});

const editingAddress = computed(() => {
    return props.addresses.find((item) => item.id === editingId.value) ?? null;
});

const isEditing = computed(() => editingId.value !== null);

const canGoBack = computed(() => props.addresses.length > 0);

const showDefaultOption = computed(() => {
    return props.addresses.length > 0 && !editingAddress.value?.is_default;
});

const isBusy = computed(() => form.processing || deletingId.value !== null);

const title = computed(() => {
    if (view.value === 'list') {
        return 'Choose Delivery Address';
    }

    return isEditing.value ? 'Edit Delivery Information' : 'New Delivery Address';
});

const addressError = computed(() => {
    if (form.errors.full_address) {
        return form.errors.full_address;
    }

    if (!addressTouched.value) {
        return null;
    }

    if (form.full_address.trim().length < minAddressLength) {
        return `Address Details requires a minimum of ${minAddressLength} characters.`;
    }

    if (form.full_address.length > maxAddressLength) {
        return `Address Details may not exceed ${maxAddressLength} characters.`;
    }

    return null;
});

const fieldBorder = (error?: string | null) => {
    return error ? 'border-red-500 focus:border-red-600' : 'border-neutral-300 focus:border-neutral-950';
};

const areaLabel = (item: Address) => {
    return item.area_name ?? [item.district, item.city, item.province].filter(Boolean).join(', ');
};

const resetForm = () => {
    editingId.value = null;

    form.clearErrors();

    form.recipient_name = '';
    form.phone = '';
    form.area_id = '';
    form.postal_code = '';
    form.full_address = '';
    form.is_default = props.addresses.length === 0;

    initialAreaName.value = null;
    addressTouched.value = false;
    areaKey.value++;
};

const startNew = () => {
    resetForm();
    confirmDeleteId.value = null;
    view.value = 'form';
};

const startEdit = (item: Address) => {
    resetForm();

    editingId.value = item.id;

    form.recipient_name = item.recipient_name;
    form.phone = item.phone;
    form.area_id = item.area_id ?? '';
    form.postal_code = item.postal_code ?? '';
    form.full_address = item.full_address;
    form.is_default = item.is_default;

    initialAreaName.value = item.area_name;
    addressTouched.value = true;
    confirmDeleteId.value = null;
    view.value = 'form';
};

const cancelForm = () => {
    if (form.processing) {
        return;
    }

    form.clearErrors();

    if (props.addresses.length > 0) {
        view.value = 'list';

        return;
    }

    emit('close');
};

const closePanel = () => {
    if (isBusy.value) {
        return;
    }

    emit('close');
};

const onBackdropClick = () => {
    if (view.value === 'list') {
        closePanel();
    }
};

const onAreaSelect = (area: Area) => {
    form.area_id = area.id;
    form.clearErrors('area_id');

    if (area.postal_code) {
        form.postal_code = area.postal_code;
    }
};

const onAreaClear = () => {
    form.area_id = '';
};

const onAddressInput = () => {
    addressTouched.value = true;
    form.clearErrors('full_address');
};

const choose = (item: Address) => {
    if (!item.area_id || isBusy.value) {
        return;
    }

    emit('select', item);
    emit('close');
};

const submit = () => {
    addressTouched.value = true;

    if (!form.area_id) {
        form.setError('area_id', 'Select your sub-district, district, and city.');
    }

    if (addressError.value || !form.area_id) {
        return;
    }

    const editing = editingAddress.value;
    const knownIds = new Set(props.addresses.map((item) => item.id));
    const selectAfterEdit = editing !== null && (editing.id === props.selectedId || !editing.area_id);

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page: { props: Record<string, unknown> }) => {
            const list = (page.props.addresses ?? []) as Address[];

            const saved = editing
                ? list.find((item) => item.id === editing.id)
                : list.find((item) => !knownIds.has(item.id));

            resetForm();

            if (saved && (editing === null || selectAfterEdit)) {
                emit('select', saved);
                emit('close');

                return;
            }

            view.value = 'list';
        },
    };

    if (editing) {
        form.put(`/account/addresses/${editing.id}`, options);

        return;
    }

    form.post('/account/addresses', options);
};

const removeAddress = (item: Address) => {
    if (item.id === props.selectedId || deletingId.value !== null) {
        return;
    }

    deletingId.value = item.id;

    router.delete(`/account/addresses/${item.id}`, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            deletingId.value = null;
            confirmDeleteId.value = null;
        },
    });
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (view.value === 'form') {
        cancelForm();

        return;
    }

    closePanel();
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            confirmDeleteId.value = null;

            if (props.addresses.length > 0) {
                view.value = 'list';
            } else {
                startNew();
            }

            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', onKeydown);

            return;
        }

        document.body.style.overflow = '';
        window.removeEventListener('keydown', onKeydown);
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[150] flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-6"
            @click.self="onBackdropClick"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="address-picker-title"
                class="address-picker-panel flex max-h-[92dvh] w-full flex-col bg-white shadow-2xl sm:max-w-xl"
            >
                <header class="relative flex shrink-0 items-center justify-center border-b border-neutral-200 px-14 py-5">
                    <button
                        v-if="view === 'form' && canGoBack"
                        type="button"
                        aria-label="Back to address list"
                        class="absolute left-4 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center transition hover:opacity-60 disabled:opacity-30"
                        :disabled="form.processing"
                        @click="cancelForm"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M19 12H5" />
                            <path d="M12 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <h2
                        id="address-picker-title"
                        class="text-center text-sm font-black uppercase tracking-[0.22em]"
                    >
                        {{ title }}
                    </h2>

                    <button
                        type="button"
                        aria-label="Close"
                        class="absolute right-4 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center transition hover:opacity-60 disabled:opacity-30"
                        :disabled="isBusy"
                        @click="closePanel"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M18 6L6 18" />
                            <path d="M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div
                    v-if="view === 'list'"
                    class="flex min-h-0 flex-1 flex-col"
                >
                    <div class="flex-1 space-y-4 overflow-y-auto p-5 sm:p-6">
                        <article
                            v-for="item in addresses"
                            :key="item.id"
                            class="border p-5 transition"
                            :class="item.id === selectedId ? 'border-neutral-950 bg-neutral-50' : 'border-neutral-200 bg-white'"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-black uppercase tracking-[0.08em] text-neutral-950">
                                            {{ item.recipient_name }}
                                        </p>

                                        <span
                                            v-if="item.is_default"
                                            class="bg-neutral-950 px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-white"
                                        >
                                            Default
                                        </span>
                                    </div>

                                    <p class="mt-1 text-sm font-bold text-neutral-700">
                                        {{ item.phone }}
                                    </p>

                                    <p class="mt-3 text-sm leading-6 text-neutral-600">
                                        {{ item.full_address }}
                                    </p>

                                    <p class="text-sm leading-6 text-neutral-600">
                                        {{ areaLabel(item) }}<span v-if="item.postal_code">, {{ item.postal_code }}</span>
                                    </p>

                                    <p
                                        v-if="!item.area_id"
                                        class="mt-3 inline-block border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-bold leading-5 text-amber-800"
                                    >
                                        Update Area: edit this address and select its area again so shipping can be calculated.
                                    </p>
                                </div>

                                <span
                                    v-if="item.id === selectedId"
                                    class="shrink-0 text-neutral-950"
                                    aria-label="Selected address"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        class="h-7 w-7"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="12"
                                            fill="currentColor"
                                        />
                                        <path
                                            d="M7 12.5l3.2 3.2L17 9"
                                            fill="none"
                                            stroke="#ffffff"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                            </div>

                            <div
                                v-if="confirmDeleteId === item.id"
                                class="mt-5 border-t border-neutral-200 pt-4"
                            >
                                <p class="text-sm font-bold text-neutral-950">
                                    Remove this address?
                                </p>

                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        :disabled="deletingId !== null"
                                        class="border border-neutral-950 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="confirmDeleteId = null"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        type="button"
                                        :disabled="deletingId !== null"
                                        class="bg-red-600 px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300"
                                        @click="removeAddress(item)"
                                    >
                                        {{ deletingId === item.id ? 'Removing...' : 'Remove' }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-5 flex flex-wrap items-center gap-3"
                            >
                                <button
                                    type="button"
                                    :disabled="item.id === selectedId || isBusy"
                                    :title="item.id === selectedId ? 'The selected address cannot be removed' : undefined"
                                    class="border border-neutral-300 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-neutral-300 disabled:hover:text-neutral-600"
                                    @click="confirmDeleteId = item.id"
                                >
                                    Remove
                                </button>

                                <button
                                    type="button"
                                    :disabled="isBusy"
                                    class="border border-neutral-300 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-neutral-600 transition hover:border-neutral-950 hover:text-neutral-950 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="startEdit(item)"
                                >
                                    Edit
                                </button>

                                <button
                                    v-if="item.id !== selectedId"
                                    type="button"
                                    :disabled="!item.area_id || isBusy"
                                    class="ml-auto bg-neutral-950 px-5 py-3 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                                    @click="choose(item)"
                                >
                                    Choose This Address
                                </button>
                            </div>
                        </article>
                    </div>

                    <div class="flex shrink-0 justify-end border-t border-neutral-200 p-4 sm:px-6">
                        <button
                            type="button"
                            :disabled="isBusy"
                            class="flex w-full items-center justify-center gap-2 bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300 sm:w-auto"
                            @click="startNew"
                        >
                            <span class="text-lg leading-none">+</span>
                            Add New
                        </button>
                    </div>
                </div>

                <form
                    v-else
                    class="flex min-h-0 flex-1 flex-col"
                    @submit.prevent="submit"
                >
                    <div class="flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">
                        <div>
                            <label
                                for="picker-recipient"
                                :class="labelClass"
                            >
                                Recipient Full Name
                            </label>

                            <input
                                id="picker-recipient"
                                v-model="form.recipient_name"
                                type="text"
                                maxlength="255"
                                autocomplete="name"
                                :class="[inputBase, fieldBorder(form.errors.recipient_name)]"
                                placeholder="Full name"
                            />

                            <p
                                v-if="form.errors.recipient_name"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.recipient_name }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="picker-phone"
                                :class="labelClass"
                            >
                                Recipient Phone Number
                            </label>

                            <input
                                id="picker-phone"
                                v-model="form.phone"
                                type="tel"
                                maxlength="20"
                                autocomplete="tel"
                                :class="[inputBase, fieldBorder(form.errors.phone)]"
                                placeholder="08xxxxxxxxxx"
                            />

                            <p
                                v-if="form.errors.phone"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <p :class="labelClass">
                                Sub-district, District, City
                            </p>

                            <div class="mt-2">
                                <AreaSearch
                                    :key="areaKey"
                                    :initial-name="initialAreaName"
                                    :error="form.errors.area_id"
                                    @select="onAreaSelect"
                                    @clear="onAreaClear"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                for="picker-postal"
                                :class="labelClass"
                            >
                                Postal Code
                            </label>

                            <input
                                id="picker-postal"
                                v-model="form.postal_code"
                                type="text"
                                inputmode="numeric"
                                maxlength="5"
                                autocomplete="postal-code"
                                :class="[inputBase, fieldBorder(form.errors.postal_code)]"
                                placeholder="151xx"
                            />

                            <p
                                v-if="form.errors.postal_code"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.postal_code }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="picker-address"
                                :class="labelClass"
                            >
                                Address Details
                            </label>

                            <textarea
                                id="picker-address"
                                v-model="form.full_address"
                                rows="4"
                                :maxlength="maxAddressLength"
                                :class="[inputBase, 'resize-none', fieldBorder(addressError)]"
                                placeholder="Street, house number, building, RT/RW..."
                                @input="onAddressInput"
                            ></textarea>

                            <div class="mt-1 flex items-start justify-between gap-4 text-xs">
                                <p
                                    v-if="addressError"
                                    class="text-red-600"
                                >
                                    {{ addressError }}
                                </p>

                                <span v-else></span>

                                <span class="shrink-0 text-neutral-500">
                                    {{ form.full_address.length }} / {{ maxAddressLength }}
                                </span>
                            </div>
                        </div>

                        <label
                            v-if="showDefaultOption"
                            class="flex items-center gap-3 border border-neutral-200 bg-neutral-50 p-4"
                        >
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="h-4 w-4 rounded-none border-neutral-300 text-neutral-950"
                            />

                            <span class="text-sm font-bold text-neutral-700">
                                Set as default address
                            </span>
                        </label>
                    </div>

                    <div class="grid shrink-0 grid-cols-2 gap-3 border-t border-neutral-200 p-4 sm:px-6">
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="border border-neutral-950 bg-white px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-neutral-950 transition hover:bg-neutral-950 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                            @click="cancelForm"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-neutral-950 px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-300"
                        >
                            {{ form.processing ? 'Saving...' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style>
@keyframes address-picker-in {
    from {
        opacity: 0;
        transform: translateY(24px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.address-picker-panel {
    animation: address-picker-in 0.2s ease-out;
}
</style>