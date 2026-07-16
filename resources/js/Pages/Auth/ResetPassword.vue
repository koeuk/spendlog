<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole } from 'lucide-vue-next';
import AuthCardLayout from '@/Layouts/AuthCardLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset password" />

    <AuthCardLayout heading="Choose a new password">
        <template #icon>
            <LockKeyhole class="size-6 text-[#4b9d5f]" aria-hidden="true" />
        </template>

        <template #description>
            Pick something you'll remember. You'll be signed in with it right away.
        </template>

        <form @submit.prevent="submit">
            <!--
                The email comes from the reset link and is shown read-only:
                editing it here would only ever invalidate the token.
            -->
            <Label for="email" class="sr-only">Email</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                required
                readonly
                autocomplete="username"
                :aria-invalid="!!form.errors.email"
                :class="[PILL_INPUT, 'cursor-not-allowed bg-neutral-50 text-neutral-500']"
            />
            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                {{ form.errors.email }}
            </p>

            <div class="relative mt-3">
                <Label for="password" class="sr-only">New password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autofocus
                    autocomplete="new-password"
                    placeholder="New password"
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

            <Label for="password_confirmation" class="sr-only">Confirm password</Label>
            <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="new-password"
                placeholder="Confirm new password"
                :aria-invalid="!!form.errors.password_confirmation"
                :class="[PILL_INPUT, 'mt-3']"
            />
            <p
                v-if="form.errors.password_confirmation"
                class="mt-1.5 px-5 text-xs font-medium text-red-600"
            >
                {{ form.errors.password_confirmation }}
            </p>

            <Button type="submit" :disabled="form.processing" :class="[PILL_BUTTON, 'mt-4']">
                {{ form.processing ? 'Saving…' : 'Reset password' }}
            </Button>
        </form>
    </AuthCardLayout>
</template>
