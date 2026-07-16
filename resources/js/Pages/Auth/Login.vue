<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

// The dots below the artwork are real controls, not decoration.
const slides = [
    'Log what you spend, the moment you spend it.',
    'See where the money actually goes each month.',
    'Set a budget. Know before you overshoot it.',
];
const slide = ref(0);
let timer = null;

function goTo(index) {
    slide.value = index;
    start();
}

function start() {
    stop();
    timer = setInterval(() => {
        slide.value = (slide.value + 1) % slides.length;
    }, 5000);
}

function stop() {
    if (timer) clearInterval(timer);
    timer = null;
}

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen bg-white p-3 font-display text-neutral-900 lg:p-4">
        <div class="grid min-h-[calc(100vh-1.5rem)] gap-4 lg:min-h-[calc(100vh-2rem)] lg:grid-cols-2">
            <!-- Form -->
            <div class="flex items-center justify-center px-4 py-10 sm:px-8">
                <div class="w-full max-w-[380px]">
                    <div class="anim" style="--d: 0ms">
                        <Link
                            href="/"
                            class="mb-10 inline-flex items-center gap-2 text-sm font-bold tracking-tight"
                        >
                            <span class="grid size-7 place-items-center rounded-lg bg-neutral-900 text-[13px] font-extrabold text-white">
                                S
                            </span>
                            SpendLog
                        </Link>
                    </div>

                    <h1
                        class="anim text-[2.6rem] font-extrabold leading-[1.05] tracking-[-0.03em] sm:text-5xl"
                        style="--d: 60ms"
                    >
                        Welcome back!
                    </h1>

                    <p class="anim mt-3 text-sm leading-relaxed text-neutral-500" style="--d: 120ms">
                        Pick up your log where you left off.
                        <span class="font-semibold text-neutral-900">SpendLog</span>
                        keeps the day-to-day money stuff honest.
                    </p>

                    <div
                        v-if="status"
                        class="anim mt-6 rounded-2xl bg-[#eaf5e6] px-4 py-3 text-sm font-medium text-[#2f6b3d]"
                        style="--d: 150ms"
                    >
                        {{ status }}
                    </div>

                    <form class="mt-8" @submit.prevent="submit">
                        <div class="anim" style="--d: 180ms">
                            <label for="email" class="sr-only">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Email"
                                class="h-[54px] w-full rounded-full border border-neutral-200 bg-white px-5 text-sm text-neutral-900 outline-none transition placeholder:text-neutral-400 hover:border-neutral-300 focus:border-neutral-900 focus:ring-4 focus:ring-neutral-900/5"
                                :class="form.errors.email && 'border-red-400 focus:border-red-500 focus:ring-red-500/10'"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="anim mt-3" style="--d: 240ms">
                            <label for="password" class="sr-only">Password</label>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Password"
                                    class="h-[54px] w-full rounded-full border border-neutral-200 bg-white pl-5 pr-12 text-sm text-neutral-900 outline-none transition placeholder:text-neutral-400 hover:border-neutral-300 focus:border-neutral-900 focus:ring-4 focus:ring-neutral-900/5"
                                    :class="form.errors.password && 'border-red-400 focus:border-red-500 focus:ring-red-500/10'"
                                />
                                <button
                                    type="button"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPassword"
                                    class="absolute right-1.5 top-1.5 grid size-[42px] place-items-center rounded-full text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
                                    @click="showPassword = !showPassword"
                                >
                                    <component :is="showPassword ? Eye : EyeOff" class="size-[18px]" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="anim mt-3 flex items-center justify-between px-1" style="--d: 300ms">
                            <label class="flex cursor-pointer select-none items-center gap-2 text-xs font-medium text-neutral-500">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    name="remember"
                                    class="size-4 rounded-md border-neutral-300 text-neutral-900 focus:ring-neutral-900"
                                />
                                Remember me
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-semibold text-neutral-900 underline-offset-4 hover:underline"
                            >
                                Forgot Password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="anim mt-6 h-[54px] w-full rounded-full bg-neutral-900 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-neutral-900/20 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-40"
                            style="--d: 360ms"
                        >
                            {{ form.processing ? 'Logging in…' : 'Login' }}
                        </button>
                    </form>

                    <p class="anim mt-12 text-center text-sm font-medium text-neutral-500" style="--d: 420ms">
                        Not a member?
                        <Link
                            :href="route('register')"
                            class="font-semibold text-[#4b9d5f] underline-offset-4 hover:underline"
                        >
                            Register now
                        </Link>
                    </p>
                </div>
            </div>

            <!-- Artwork -->
            <div
                class="anim relative hidden overflow-hidden rounded-[28px] bg-[#f1f7ef] lg:flex lg:flex-col lg:items-center lg:justify-center"
                style="--d: 200ms"
            >
                <!-- decorative arcs -->
                <svg
                    class="pointer-events-none absolute inset-0 size-full"
                    viewBox="0 0 400 400"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M120 132C120 92 168 72 200 96c32 24 80 4 80-36"
                        stroke="#bcdcc0"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                    <circle cx="318" cy="92" r="46" fill="#e6f2e3" />
                    <circle cx="72" cy="300" r="60" fill="#e6f2e3" />
                </svg>

                <div class="relative flex flex-col items-center px-10">
                    <!-- ledger card -->
                    <div class="w-[280px] rotate-[-3deg] rounded-3xl bg-white p-5 shadow-[0_18px_40px_-12px_rgba(31,64,38,0.18)]">
                        <div class="flex items-baseline justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-400">
                                Today
                            </span>
                            <span class="text-lg font-extrabold tracking-tight">$34.20</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="row in [
                                    { dot: 'bg-orange-400', name: 'Lunch', cat: 'Food', amt: '$12.50' },
                                    { dot: 'bg-blue-400', name: 'Tuk-tuk', cat: 'Transport', amt: '$3.70' },
                                    { dot: 'bg-purple-400', name: 'Headphones', cat: 'Shopping', amt: '$18.00' },
                                ]"
                                :key="row.name"
                                class="flex items-center gap-3"
                            >
                                <span class="size-2 shrink-0 rounded-full" :class="row.dot" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold leading-tight">
                                        {{ row.name }}
                                    </p>
                                    <p class="text-[11px] text-neutral-400">{{ row.cat }}</p>
                                </div>
                                <span class="text-[13px] font-bold tabular-nums">{{ row.amt }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- budget ring, overlapping -->
                    <div
                        class="-mt-6 ml-40 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-[0_14px_30px_-10px_rgba(31,64,38,0.22)]"
                    >
                        <div class="relative grid size-11 place-items-center">
                            <svg class="size-11 -rotate-90" viewBox="0 0 44 44" aria-hidden="true">
                                <circle cx="22" cy="22" r="18" fill="none" stroke="#e8f0e6" stroke-width="5" />
                                <circle
                                    cx="22"
                                    cy="22"
                                    r="18"
                                    fill="none"
                                    stroke="#5fae72"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    stroke-dasharray="113.1"
                                    stroke-dashoffset="18.1"
                                />
                            </svg>
                            <span class="absolute text-[10px] font-extrabold">84%</span>
                        </div>
                        <div>
                            <p class="text-[12px] font-bold leading-tight">Food budget</p>
                            <p class="text-[11px] text-neutral-400">$168 of $200</p>
                        </div>
                    </div>

                    <!-- rotating tagline -->
                    <div class="mt-14 flex h-16 max-w-[340px] items-start justify-center">
                        <Transition name="fade" mode="out-in">
                            <p
                                :key="slide"
                                class="text-center text-[22px] font-bold leading-snug tracking-[-0.02em]"
                            >
                                {{ slides[slide] }}
                            </p>
                        </Transition>
                    </div>

                    <div class="mt-2 flex items-center gap-1.5">
                        <button
                            v-for="(s, i) in slides"
                            :key="i"
                            type="button"
                            :aria-label="`Slide ${i + 1}`"
                            :aria-current="slide === i"
                            class="h-1.5 rounded-full transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
                            :class="slide === i ? 'w-6 bg-neutral-900' : 'w-1.5 bg-neutral-300 hover:bg-neutral-400'"
                            @click="goTo(i)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.anim {
    animation: rise 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: var(--d, 0ms);
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.35s ease, transform 0.35s ease;
}

.fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
    .anim,
    .fade-enter-active,
    .fade-leave-active {
        animation: none;
        transition: none;
    }
}
</style>
