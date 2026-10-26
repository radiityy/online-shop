<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type RefundAccount = {
    payment_id: number;
    amount: number;
    method: string;
    state: 'needed' | 'submitted' | 'locked';
    bank_code: string | null;
    bank_label: string | null;
    masked: string | null;
    holder: string | null;
    can_edit: boolean;
    estimate: string;
};

type Bank = {
    code: string;
    label: string;
    min: number;
    max: number;
};

const props = defineProps<{
    orderCode: string;
    account: RefundAccount;
    banks: Bank[];
}>();

const form = useForm({
    bank_code: props.account.bank_code ?? '',
    account_number: '',
    account_holder: props.account.holder ?? '',
    confirmed: false,
});

const editing = ref(props.account.state === 'needed');
const confirming = ref(false);
const localError = ref<string | null>(null);

const bank = computed(() => props.banks.find((item) => item.code === form.bank_code) ?? null);

const money = (value: number) => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

const onNumberInput = (event: Event) => {
    form.account_number = (event.target as HTMLInputElement).value.replace(/\D/g, '');
};

const review = () => {
    localError.value = null;

    if (!bank.value) {
        localError.value = 'Please choose a bank.';
        return;
    }

    const length = form.account_number.length;

    if (length < bank.value.min || length > bank.value.max) {
        localError.value =
            bank.value.min === bank.value.max
                ? `${bank.value.label} account numbers have ${bank.value.min} digits.`
                : `${bank.value.label} account numbers have ${bank.value.min} to ${bank.value.max} digits.`;
        return;
    }

    if (form.account_holder.trim().length < 3) {
        localError.value = 'Please enter the account holder name.';
        return;
    }

    confirming.value = true;
};

const submit = () => {
    form.confirmed = true;
    form.post(`/orders/${props.orderCode}/refund-accounts/${props.account.payment_id}`, {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            editing.value = false;
            form.reset('account_number', 'confirmed');
        },
        onError: () => {
            confirming.value = false;
        },
    });
};

const inputClass =
    'w-full border border-neutral-950 bg-white px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-neutral-950';
</script>

<template>
    <section class="border-2 border-neutral-950 p-5 md:p-7">
        <p class="text-xs font-black uppercase tracking-[0.25em] text-neutral-500">Refund · {{ money(account.amount) }}</p>

        <!-- Account already received -->
        <template v-if="account.state !== 'needed' && !editing">
            <h2 class="mt-2 text-2xl font-black uppercase tracking-[-0.03em] md:text-3xl">We have your bank account</h2>

            <p class="mt-4 text-sm leading-7 text-neutral-700">
                {{ account.bank_label }} {{ account.masked }}
                <span v-if="account.holder"> · a.n. {{ account.holder }}</span>
            </p>

            <p class="mt-2 text-sm leading-7 text-neutral-600">
                We will transfer your money within {{ account.estimate }}, and we will notify you when it is done.
            </p>

            <button
                v-if="account.can_edit"
                type="button"
                class="mt-5 text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                @click="editing = true"
            >
                Change account
            </button>

            <p v-else class="mt-4 text-xs uppercase tracking-[0.15em] text-neutral-500">
                We have started your refund, so the account can no longer be changed.
            </p>
        </template>

        <!-- Form -->
        <template v-else-if="!confirming">
            <h2 class="mt-2 text-2xl font-black uppercase tracking-[-0.03em] md:text-3xl">
                Add your bank account so we can return your money
            </h2>

            <p class="mt-3 text-sm leading-7 text-neutral-600">
                Your {{ account.method }} payment cannot be returned automatically. Tell us where to send it. We will transfer
                it within {{ account.estimate }} after we receive your details.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="review">
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-[0.2em]" for="refund-bank">Bank</label>
                    <select id="refund-bank" v-model="form.bank_code" :class="inputClass">
                        <option value="" disabled>Choose a bank</option>
                        <option v-for="item in banks" :key="item.code" :value="item.code">{{ item.label }}</option>
                    </select>
                    <p v-if="form.errors.bank_code" class="mt-1 text-xs text-red-600">{{ form.errors.bank_code }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-[0.2em]" for="refund-number">
                        Account number
                    </label>
                    <input
                        id="refund-number"
                        :value="form.account_number"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        :class="inputClass"
                        @input="onNumberInput"
                    />
                    <p v-if="form.errors.account_number" class="mt-1 text-xs text-red-600">{{ form.errors.account_number }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-[0.2em]" for="refund-holder">
                        Account holder name
                    </label>
                    <input id="refund-holder" v-model="form.account_holder" type="text" autocomplete="off" :class="inputClass" />
                    <p v-if="form.errors.account_holder" class="mt-1 text-xs text-red-600">{{ form.errors.account_holder }}</p>
                </div>

                <p v-if="localError" class="text-xs text-red-600">{{ localError }}</p>
                <p v-if="form.errors.confirmed" class="text-xs text-red-600">{{ form.errors.confirmed }}</p>

                <div class="flex flex-wrap items-center gap-4">
                    <button
                        type="submit"
                        class="bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800"
                    >
                        Review
                    </button>

                    <button
                        v-if="account.state !== 'needed'"
                        type="button"
                        class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                        @click="editing = false"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </template>

        <!-- Confirmation screen -->
        <template v-else>
            <h2 class="mt-2 text-2xl font-black uppercase tracking-[-0.03em] md:text-3xl">Is this correct?</h2>

            <p class="mt-4 border border-neutral-950 p-4 text-lg font-black tracking-wide">
                {{ bank?.label }}, {{ form.account_number }}, a.n. {{ form.account_holder }}
            </p>

            <p class="mt-3 text-sm leading-7 text-neutral-600">
                Please check every digit. If the number is wrong, your money can go to someone else.
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-4">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="bg-neutral-950 px-8 py-4 text-xs font-black uppercase tracking-[0.22em] text-white transition hover:bg-neutral-800 disabled:opacity-50"
                    @click="submit"
                >
                    {{ form.processing ? 'Sending...' : 'Yes, send my details' }}
                </button>

                <button
                    type="button"
                    class="text-xs font-black uppercase tracking-[0.2em] underline underline-offset-8"
                    @click="confirming = false"
                >
                    Edit
                </button>
            </div>
        </template>
    </section>
</template>