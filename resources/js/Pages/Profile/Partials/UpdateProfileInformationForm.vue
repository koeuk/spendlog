<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { MUTED } from '@/lib/appStyles';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    // Optional; '' clears it back to null server-side.
    username: user.username ?? '',
    email: user.email,
});
</script>

<template>
    <!--
        No header here: SettingsLayout already titles the panel, and a second
        "Profile Information" under "Profile" is the same word twice.
    -->
    <form class="space-y-5" @submit.prevent="form.patch(route('profile.update'))">
        <div>
            <Label for="name">{{ __('Name') }}</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                required
                autofocus
                autocomplete="name"
                class="mt-1"
                :aria-invalid="!!form.errors.name"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.name }}
            </p>
        </div>

        <div>
            <Label for="username">{{ __('Username') }}</Label>
            <Input
                id="username"
                v-model="form.username"
                type="text"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                class="mt-1"
                placeholder="koeuk"
                :aria-invalid="!!form.errors.username"
            />
            <p class="mt-1 text-xs" :class="MUTED">
                {{ __('Optional. Lowercase letters, numbers, underscores and hyphens. You can sign in with this instead of your email.') }}
            </p>
            <p v-if="form.errors.username" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.username }}
            </p>
        </div>

        <div>
            <Label for="email">{{ __('Email') }}</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                required
                autocomplete="username"
                class="mt-1"
                :aria-invalid="!!form.errors.email"
            />
            <p class="mt-1 text-xs" :class="MUTED">
                {{ __('Changing this signs you out of nothing, but you will need to verify the new address.') }}
            </p>
            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.email }}
            </p>
        </div>

        <div
            v-if="mustVerifyEmail && user.email_verified_at === null"
            class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <p>
                {{ __('Your email address is unverified.') }}
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="font-semibold underline underline-offset-4 hover:no-underline"
                >
                    {{ __('Re-send the verification email.') }}
                </Link>
            </p>

            <p
                v-if="status === 'verification-link-sent'"
                class="mt-2 font-medium text-[#2f6b3d] dark:text-[#8fd4a0]"
            >
                {{ __('A new verification link has been sent to your email address.') }}
            </p>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? __('Saving…') : __('Save') }}
            </Button>

            <!--
                Announced politely so a screen reader hears the save land; the
                visual "Saved." alone would be silent to it.
            -->
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 translate-x-1"
                leave-active-class="transition ease-in duration-150"
                leave-to-class="opacity-0"
            >
                <p
                    v-if="form.recentlySuccessful"
                    class="text-sm"
                    :class="MUTED"
                    role="status"
                    aria-live="polite"
                >
                    {{ __('Saved.') }}
                </p>
            </Transition>
        </div>
    </form>
</template>
