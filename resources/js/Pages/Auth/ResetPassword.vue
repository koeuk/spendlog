<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole } from 'lucide-vue-next';
import AuthCardLayout from '@/Layouts/AuthCardLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';

const props = defineProps({
    // Prefilled when we arrive from the forgot-password step; empty on a
    // direct visit, where the person types it alongside the code.
    email: { type: String, default: '' },
    status: { type: String, default: null },
});

const form = useForm({
    email: props.email,
    code: '',
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

    <AuthCardLayout heading="Enter your code">
        <template #icon>
            <LockKeyhole class="size-6 text-[#4b9d5f]" aria-hidden="true" />
        </template>

        <template #description>
            Type the 6-digit code we emailed you, then choose a new password.
        </template>

        <div
            v-if="status"
            class="mb-4 rounded-[20px] bg-[#eaf5e6] px-4 py-3 text-center text-sm font-medium text-[#2f6b3d] dark:bg-[#16281a] dark:text-[#8fd4a0]"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
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
            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
                {{ form.errors.email }}
            </p>

            <Label for="code" class="sr-only">6-digit code</Label>
            <Input
                id="code"
                v-model="form.code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                required
                autofocus
                autocomplete="one-time-code"
                placeholder="6-digit code"
                :aria-invalid="!!form.errors.code"
                :class="[PILL_INPUT, 'mt-3 text-center text-lg font-semibold tracking-[0.5em]']"
            />
            <p v-if="form.errors.code" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
                {{ form.errors.code }}
            </p>

            <div class="relative mt-3">
                <Label for="password" class="sr-only">New password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    required
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
            <p v-if="form.errors.password" class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400">
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
                class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400"
            >
                {{ form.errors.password_confirmation }}
            </p>

            <Button type="submit" :disabled="form.processing" :class="[PILL_BUTTON, 'mt-4']">
                {{ form.processing ? 'Saving…' : 'Reset password' }}
            </Button>
        </form>

        <template #footer>
            <Link
                :href="route('password.request')"
                class="text-sm font-medium text-neutral-500 underline-offset-4 hover:text-neutral-900 hover:underline dark:text-neutral-400 dark:hover:text-neutral-100"
            >
                Didn't get it? Send a new code
            </Link>
        </template>
    </AuthCardLayout>
</template>
