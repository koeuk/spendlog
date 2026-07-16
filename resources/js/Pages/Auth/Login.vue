<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import AuthArtwork from '@/Components/AuthArtwork.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';
import { trans } from '@/lib/i18n';

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

// computed, not a plain array: the strings must re-resolve when the locale changes.
const slides = computed(() => [
    trans('Log what you spend, the moment you spend it.'),
    trans('See where the money actually goes each month.'),
    trans('Set a budget. Know before you overshoot it.'),
]);
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen bg-white p-3 font-display text-neutral-900 lg:p-4 dark:bg-neutral-950 dark:text-neutral-100">
        <div class="grid min-h-[calc(100vh-1.5rem)] gap-4 lg:min-h-[calc(100vh-2rem)] lg:grid-cols-2">
            <div class="relative flex items-center justify-center px-4 py-10 sm:px-8">
                <div class="absolute right-4 top-4 flex items-center gap-2 sm:right-6">
                    <LocaleSwitcher />
                    <ThemeToggle />
                </div>

                <div class="w-full max-w-[380px]">
                    <Link
                        href="/"
                        class="anim mb-10 inline-flex items-center gap-2 text-sm font-bold tracking-tight"
                        style="--d: 0ms"
                    >
                        <span class="grid size-7 place-items-center rounded-lg bg-neutral-900 text-[13px] font-extrabold text-white dark:bg-neutral-100 dark:text-neutral-900">
                            S
                        </span>
                        SpendLog
                    </Link>

                    <h1
                        class="anim text-[2.6rem] font-extrabold leading-[1.05] tracking-[-0.03em] sm:text-5xl"
                        style="--d: 60ms"
                    >
                        {{ __('Welcome back!') }}
                    </h1>

                    <p class="anim mt-3 text-sm leading-relaxed text-neutral-500 dark:text-neutral-400" style="--d: 120ms">
                        {{ __('Pick up your log where you left off.') }}
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">SpendLog</span>
                        {{ __('keeps the day-to-day money stuff honest.') }}
                    </p>

                    <div
                        v-if="status"
                        class="anim mt-6 rounded-2xl bg-[#eaf5e6] px-4 py-3 text-sm font-medium text-[#2f6b3d] dark:bg-[#16281a] dark:text-[#8fd4a0]"
                        style="--d: 150ms"
                    >
                        {{ status }}
                    </div>

                    <form class="mt-8" @submit.prevent="submit">
                        <div class="anim" style="--d: 180ms">
                            <Label for="email" class="sr-only">{{ __('Email') }}</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                :placeholder="__('Email')"
                                :aria-invalid="!!form.errors.email"
                                :class="PILL_INPUT"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="anim mt-3" style="--d: 240ms">
                            <Label for="password" class="sr-only">{{ __('Password') }}</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    :placeholder="__('Password')"
                                    :aria-invalid="!!form.errors.password"
                                    :class="[PILL_INPUT, 'pr-12']"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="showPassword ? __('Hide password') : __('Show password')"
                                    :aria-pressed="showPassword"
                                    class="absolute right-1.5 top-1.5 size-[42px] rounded-full text-neutral-400 hover:bg-neutral-50 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                    @click="showPassword = !showPassword"
                                >
                                    <component :is="showPassword ? Eye : EyeOff" class="size-[18px]" />
                                </Button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="anim mt-4 flex items-center justify-between px-1" style="--d: 300ms">
                            <div class="flex items-center gap-2">
                                <Checkbox id="remember" v-model="form.remember" name="remember" />
                                <Label for="remember" class="cursor-pointer text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                    {{ __('Remember me') }}
                                </Label>
                            </div>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-semibold text-neutral-900 underline-offset-4 hover:underline dark:text-neutral-100"
                            >
                                {{ __('Forgot Password?') }}
                            </Link>
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            :class="[PILL_BUTTON, 'anim mt-6']"
                            style="--d: 360ms"
                        >
                            {{ form.processing ? __('Logging in…') : __('Login') }}
                        </Button>
                    </form>

                    <p class="anim mt-12 text-center text-sm font-medium text-neutral-500 dark:text-neutral-400" style="--d: 420ms">
                        {{ __('Not a member?') }}
                        <Link
                            :href="route('register')"
                            class="font-semibold text-[#4b9d5f] underline-offset-4 hover:underline dark:text-[#6cc182]"
                        >
                            {{ __('Register now') }}
                        </Link>
                    </p>
                </div>
            </div>

            <AuthArtwork class="anim" style="--d: 200ms" :slides="slides" />
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

@media (prefers-reduced-motion: reduce) {
    .anim {
        animation: none;
    }
}
</style>
