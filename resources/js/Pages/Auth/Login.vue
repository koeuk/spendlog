<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import AuthScreenLayout from '@/Layouts/AuthScreenLayout.vue';
import GoogleButton from '@/Components/GoogleButton.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { AUTH_BANNER, AUTH_LINK, AUTH_MUTED, PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';
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

// False unless the deployment has Google credentials — see GoogleController.
const googleEnabled = computed(() => usePage().props.google_login === true);

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

    <AuthScreenLayout :heading="__('Welcome back!')" :slides="slides">
        <template #description>
            {{ __('Pick up your log where you left off.') }}
        </template>

        <div v-if="status" :class="[AUTH_BANNER, 'mb-6']">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <Label for="email" class="sr-only">
                    {{ __('Email or username') }}
                </Label>
                <!--
                    type="text", not "email": the browser's own validation would
                    refuse a username before the form could ever be submitted.
                    The field is still named `email` so saved password-manager
                    entries and the server's error-bag key both keep working.
                -->
                <Input
                    id="email"
                    v-model="form.email"
                    type="text"
                    required
                    autofocus
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    :placeholder="__('Email or username')"
                    :aria-invalid="!!form.errors.email"
                    :class="PILL_INPUT"
                />
                <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
                    {{ form.errors.email }}
                </p>
            </div>

            <div class="mt-3">
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

            <div class="mt-4 flex items-center justify-between px-1">
                <div class="flex items-center gap-2">
                    <Checkbox id="remember" v-model="form.remember" name="remember" />
                    <Label for="remember" class="cursor-pointer text-xs font-medium" :class="AUTH_MUTED">
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
                :class="[PILL_BUTTON, 'mt-6']"
            >
                {{ form.processing ? __('Logging in…') : __('Login') }}
            </Button>
        </form>

        <template v-if="googleEnabled">
            <!-- A labelled rule, not a bare gap: without it the Google button
                 reads as a second submit for the form above it. -->
            <div class="my-5 flex items-center gap-3">
                <span class="h-px flex-1 bg-neutral-200 dark:bg-neutral-800" />
                <span class="text-xs font-medium" :class="AUTH_MUTED">{{ __('or') }}</span>
                <span class="h-px flex-1 bg-neutral-200 dark:bg-neutral-800" />
            </div>

            <GoogleButton :label="__('Continue with Google')" />
        </template>

        <p class="mt-10 text-center text-sm font-medium" :class="AUTH_MUTED">
            {{ __('Not a member?') }}
            <Link :href="route('register')" :class="AUTH_LINK">
                {{ __('Register now') }}
            </Link>
        </p>
    </AuthScreenLayout>
</template>
