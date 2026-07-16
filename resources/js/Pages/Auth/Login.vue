<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import AuthArtwork from '@/Components/AuthArtwork.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

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

const slides = [
    'Log what you spend, the moment you spend it.',
    'See where the money actually goes each month.',
    'Set a budget. Know before you overshoot it.',
];

const pill =
    'h-[54px] rounded-full border-neutral-200 bg-white px-5 text-sm shadow-none transition placeholder:text-neutral-400 hover:border-neutral-300 focus-visible:border-neutral-900 focus-visible:ring-neutral-900/10';
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen bg-white p-3 font-display text-neutral-900 lg:p-4">
        <div class="grid min-h-[calc(100vh-1.5rem)] gap-4 lg:min-h-[calc(100vh-2rem)] lg:grid-cols-2">
            <div class="flex items-center justify-center px-4 py-10 sm:px-8">
                <div class="w-full max-w-[380px]">
                    <Link
                        href="/"
                        class="anim mb-10 inline-flex items-center gap-2 text-sm font-bold tracking-tight"
                        style="--d: 0ms"
                    >
                        <span class="grid size-7 place-items-center rounded-lg bg-neutral-900 text-[13px] font-extrabold text-white">
                            S
                        </span>
                        SpendLog
                    </Link>

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
                            <Label for="email" class="sr-only">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Email"
                                :aria-invalid="!!form.errors.email"
                                :class="pill"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="anim mt-3" style="--d: 240ms">
                            <Label for="password" class="sr-only">Password</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Password"
                                    :aria-invalid="!!form.errors.password"
                                    :class="[pill, 'pr-12']"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPassword"
                                    class="absolute right-1.5 top-1.5 size-[42px] rounded-full text-neutral-400 hover:bg-neutral-50 hover:text-neutral-700"
                                    @click="showPassword = !showPassword"
                                >
                                    <component :is="showPassword ? Eye : EyeOff" class="size-[18px]" />
                                </Button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="anim mt-4 flex items-center justify-between px-1" style="--d: 300ms">
                            <div class="flex items-center gap-2">
                                <Checkbox id="remember" v-model="form.remember" name="remember" />
                                <Label for="remember" class="cursor-pointer text-xs font-medium text-neutral-500">
                                    Remember me
                                </Label>
                            </div>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-semibold text-neutral-900 underline-offset-4 hover:underline"
                            >
                                Forgot Password?
                            </Link>
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="anim mt-6 h-[54px] w-full rounded-full text-sm font-semibold active:translate-y-0 active:scale-[0.99]"
                            style="--d: 360ms"
                        >
                            {{ form.processing ? 'Logging in…' : 'Login' }}
                        </Button>
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
