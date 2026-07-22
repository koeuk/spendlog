<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import AuthScreenLayout from '@/Layouts/AuthScreenLayout.vue';
import GoogleButton from '@/Components/GoogleButton.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { AUTH_LINK, AUTH_MUTED, PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';
import { trans } from '@/lib/i18n';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

// False unless the deployment has Google credentials — see GoogleController.
const googleEnabled = computed(() => usePage().props.google_login === true);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// computed, not a plain array: the strings must re-resolve when the locale changes.
const slides = computed(() => [
    trans('Two taps to log a coffee. That is the whole idea.'),
    trans('Your categories, your colours, your budgets.'),
    trans('Start today — tomorrow you will already have a trend.'),
]);
</script>

<template>
    <Head title="Register" />

    <AuthScreenLayout :heading="__('Start your log.')" :slides="slides">
        <template #description>
            {{ __("Free, and about a minute to set up. We'll email you a link to confirm your address.") }}
        </template>

        <form @submit.prevent="submit">
            <div>
                <Label for="name" class="sr-only">{{ __('Name') }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('Name')"
                    :aria-invalid="!!form.errors.name"
                    :class="PILL_INPUT"
                />
                <p v-if="form.errors.name" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="mt-3">
                <Label for="email" class="sr-only">{{ __('Email') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    :placeholder="__('Email')"
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
                        autocomplete="new-password"
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

            <div class="mt-3">
                <Label for="password_confirmation" class="sr-only">
                    {{ __('Confirm password') }}
                </Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    :aria-invalid="!!form.errors.password_confirmation"
                    :class="PILL_INPUT"
                />
                <p
                    v-if="form.errors.password_confirmation"
                    class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400"
                >
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

            <Button
                type="submit"
                :disabled="form.processing"
                :class="[PILL_BUTTON, 'mt-6']"
            >
                {{ form.processing ? __('Creating account…') : __('Create account') }}
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

            <!-- Same round trip as the login screen's: the callback creates the
                 account if the address is new and signs in if it is not, so a
                 separate "sign up with Google" would be the same request under
                 a different name. -->
            <GoogleButton :label="__('Continue with Google')" />
        </template>

        <p class="mt-10 text-center text-sm font-medium" :class="AUTH_MUTED">
            {{ __('Already a member?') }}
            <Link :href="route('login')" :class="AUTH_LINK">
                {{ __('Log in') }}
            </Link>
        </p>
    </AuthScreenLayout>
</template>
