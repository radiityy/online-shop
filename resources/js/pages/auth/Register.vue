<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Create Account" />

    <main class="min-h-screen bg-[#f7f4ee] text-neutral-950">
        <div class="grid min-h-screen lg:grid-cols-[0.95fr_1.05fr]">
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
                                Join NEVERENDING
                            </p>
                            <h1 class="mt-3 text-3xl font-black uppercase tracking-[-0.05em]">
                                Create account
                            </h1>
                            <p class="mt-3 text-sm leading-6 text-neutral-500">
                                Register to checkout faster, save addresses, and track your orders.
                            </p>
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
                                <Label for="name" class="text-xs font-black uppercase tracking-[0.18em]">
                                    Full Name
                                </Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    autofocus
                                    tabindex="1"
                                    autocomplete="name"
                                    placeholder="Your full name"
                                    class="h-12 rounded-none border-neutral-300 bg-white"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="email" class="text-xs font-black uppercase tracking-[0.18em]">
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    tabindex="2"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    class="h-12 rounded-none border-neutral-300 bg-white"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password" class="text-xs font-black uppercase tracking-[0.18em]">
                                    Password
                                </Label>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    tabindex="3"
                                    autocomplete="new-password"
                                    placeholder="Minimum 8 characters"
                                    class="h-12 rounded-none border-neutral-300 bg-white"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password_confirmation" class="text-xs font-black uppercase tracking-[0.18em]">
                                    Confirm Password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    required
                                    tabindex="4"
                                    autocomplete="new-password"
                                    placeholder="Repeat your password"
                                    class="h-12 rounded-none border-neutral-300 bg-white"
                                />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>

                            <Button
                                type="submit"
                                class="h-12 w-full rounded-none bg-neutral-950 text-xs font-black uppercase tracking-[0.22em] text-white hover:bg-neutral-800"
                                tabindex="5"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Create account
                            </Button>
                        </form>

                        <div class="mt-7 border-t border-neutral-200 pt-6 text-center text-sm text-neutral-500">
                            Already have an account?
                            <Link :href="route('login')" class="font-black uppercase tracking-[0.12em] text-neutral-950 hover:underline">
                                Sign in
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

            <section class="hidden overflow-hidden bg-neutral-950 text-white lg:block">
                <div class="flex h-full flex-col justify-between p-12">
                    <Link :href="route('home')" class="inline-flex w-fit text-2xl font-black tracking-[0.35em]">
                        NEVERENDING
                    </Link>

                    <div class="max-w-xl">
                        <p class="mb-6 text-xs font-bold uppercase tracking-[0.35em] text-white/50">
                            Customer Account
                        </p>

                        <h1 class="text-5xl font-black uppercase leading-[0.95] tracking-[-0.06em] xl:text-7xl">
                            Your rotation starts here.
                        </h1>

                        <p class="mt-8 max-w-md text-sm leading-7 text-white/65">
                            Create your NEVERENDING account to save your delivery details, manage orders, and keep your essentials ready.
                        </p>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/10 pt-6 text-[10px] font-bold uppercase tracking-[0.25em] text-white/45">
                        <span>NEVERENDING</span>
                        <span>Official Store</span>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>