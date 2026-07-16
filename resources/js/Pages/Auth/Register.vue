<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import AuthArtwork from '@/Components/AuthArtwork.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const slides = [
    'Two taps to log a coffee. That is the whole idea.',
    'Your categories, your colours, your budgets.',
    'Start today — tomorrow you will already have a trend.',
];
</script>

<template>
    <Head title="Register" />

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
                        Start your log.
                    </h1>

                    <p class="anim mt-3 text-sm leading-relaxed text-neutral-500" style="--d: 120ms">
                        Free, and about a minute to set up. We'll email you a link
                        to confirm your address.
                    </p>

                    <form class="mt-8" @submit.prevent="submit">
                        <div class="anim" style="--d: 180ms">
                            <Label for="name" class="sr-only">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Name"
                                :aria-invalid="!!form.errors.name"
                                :class="PILL_INPUT"
                            />
                            <p v-if="form.errors.name" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="anim mt-3" style="--d: 220ms">
                            <Label for="email" class="sr-only">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="username"
                                placeholder="Email"
                                :aria-invalid="!!form.errors.email"
                                :class="PILL_INPUT"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="anim mt-3" style="--d: 260ms">
                            <Label for="password" class="sr-only">Password</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Password"
                                    :aria-invalid="!!form.errors.password"
                                    :class="[PILL_INPUT, 'pr-12']"
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

                        <div class="anim mt-3" style="--d: 300ms">
                            <Label for="password_confirmation" class="sr-only">
                                Confirm password
                            </Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm password"
                                :aria-invalid="!!form.errors.password_confirmation"
                                :class="PILL_INPUT"
                            />
                            <p
                                v-if="form.errors.password_confirmation"
                                class="mt-1.5 px-5 text-xs font-medium text-red-600"
                            >
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            :class="[PILL_BUTTON, 'anim mt-6']"
                            style="--d: 360ms"
                        >
                            {{ form.processing ? 'Creating account…' : 'Create account' }}
                        </Button>
                    </form>

                    <p class="anim mt-12 text-center text-sm font-medium text-neutral-500" style="--d: 420ms">
                        Already a member?
                        <Link
                            :href="route('login')"
                            class="font-semibold text-[#4b9d5f] underline-offset-4 hover:underline"
                        >
                            Log in
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
