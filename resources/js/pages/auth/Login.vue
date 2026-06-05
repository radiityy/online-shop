<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign In" />

    <main class="min-h-screen bg-[#f7f4ee] text-neutral-950">
        <div class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
            <section class="hidden overflow-hidden bg-neutral-950 text-white lg:block">
                <div class="flex h-full flex-col justify-between p-12">
                    <Link :href="route('home')" class="inline-flex w-fit text-2xl font-black tracking-[0.35em]">
                        NEVERENDING
                    </Link>

                    <div class="max-w-xl">
                        <p class="mb-6 text-xs font-bold uppercase tracking-[0.35em] text-white/50">
                            Daily Wear
                        </p>

                        <h1 class="text-5xl font-black uppercase leading-[0.95] tracking-[-0.06em] xl:text-7xl">
                            Built for endless rotation.
                        </h1>

                        <p class="mt-8 max-w-md text-sm leading-7 text-white/65">
                            Sign in to continue shopping, manage your bag, checkout faster, and track every order from NEVERENDING.
                        </p>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/10 pt-6 text-[10px] font-bold uppercase tracking-[0.25em] text-white/45">
                        <span>NEVERENDING</span>
                        <span>Est. 2026</span>
                    </div>
                </div>
            </section>

            <section class="flex items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div class="w-full max-w-md">
                    <div class="mb-10 lg:hidden">
                        <Link :href="route('home')" class="text-2xl font-black tracking-[0.35em]">
                            NEVERENDING
                        </Link>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.25em] text-neutral-500">
                            Daily wear for endless rotation
                        </p>
                    </div>

                    <div class="border border-neutral-950/10 bg-white p-6 shadow-[8px_8px_0_#111] sm:p-8">
                        <div class="mb-8">
                            <p class="text-xs font-bold uppercase tracking-[0.28em] text-neutral-500">
                                Customer Login
                            </p>
                            <h1 class="mt-3 text-3xl font-black uppercase tracking-[-0.05em]">
                                Sign in
                            </h1>
                            <p class="mt-3 text-sm leading-6 text-neutral-500">
                                Access your bag, checkout, and order progress.
                            </p>
                        </div>

                        <div v-if="status" class="mb-5 border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-700">
                            {{ status }}
                        </div>

                        <Link
                            :href="route('auth.google.redirect')"
                            class="mb-5 flex h-12 w-full items-center justify-center border border-neutral-300 bg-white text-xs font-black uppercase tracking-[0.18em] text-neutral-950 transition hover:border-neutral-950 hover:bg-neutral-50"
                        >
                            Continue with Google
                        </Link>

                        <div class="mb-5 flex items-center gap-4">
                            <div class="h-px flex-1 bg-neutral-200"></div>
                            <span class="text-[10px] font-black uppercase tracking-[0.25em] text-neutral-400">
                                or
                            </span>
                            <div class="h-px flex-1 bg-neutral-200"></div>
                        </div>
                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="space-y-2">
                                <Label for="email" class="text-xs font-black uppercase tracking-[0.18em]">
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    tabindex="1"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    class="h-12 rounded-none border-neutral-300 bg-white"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-4">
                                    <Label for="password" class="text-xs font-black uppercase tracking-[0.18em]">
                                        Password
                                    </Label>

                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-xs font-bold uppercase tracking-[0.12em] text-neutral-500 hover:text-neutral-950"
                                        tabindex="5"
                                    >
                                        Forgot?
                                    </Link>
                                </div>

                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Your password"
                                    class="h-12 rounded-none border-neutral-300 bg-white"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="flex items-center justify-between">
                                <Label for="remember" class="flex cursor-pointer items-center gap-3 text-sm text-neutral-600">
                                    <Checkbox id="remember" v-model:checked="form.remember" tabindex="3" />
                                    <span>Remember me</span>
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                class="h-12 w-full rounded-none bg-neutral-950 text-xs font-black uppercase tracking-[0.22em] text-white hover:bg-neutral-800"
                                tabindex="4"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Sign in
                            </Button>
                        </form>

                        <div class="mt-7 border-t border-neutral-200 pt-6 text-center text-sm text-neutral-500">
                            New to NEVERENDING?
                            <Link :href="route('register')" class="font-black uppercase tracking-[0.12em] text-neutral-950 hover:underline">
                                Create account
                            </Link>
                        </div>
                    </div>

                    <div class="mt-8 text-center">
                        <Link :href="route('home')" class="text-xs font-bold uppercase tracking-[0.2em] text-neutral-500 hover:text-neutral-950">
                            Back to store
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>