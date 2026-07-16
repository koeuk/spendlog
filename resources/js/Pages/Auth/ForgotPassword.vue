<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import AuthCardLayout from '@/Layouts/AuthCardLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot password" />

    <AuthCardLayout heading="Forgot your password?">
        <template #icon>
            <KeyRound class="size-6 text-[#4b9d5f]" aria-hidden="true" />
        </template>

        <template #description>
            No problem. Tell us your email address and we'll send you a link to
            choose a new one.
        </template>

        <div
            v-if="status"
            class="mb-4 rounded-2xl bg-[#eaf5e6] px-4 py-3 text-center text-sm font-medium text-[#2f6b3d]"
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
                autofocus
                autocomplete="username"
                placeholder="Email"
                :aria-invalid="!!form.errors.email"
                :class="PILL_INPUT"
            />
            <p v-if="form.errors.email" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                {{ form.errors.email }}
            </p>

            <Button type="submit" :disabled="form.processing" :class="[PILL_BUTTON, 'mt-4']">
                {{ form.processing ? 'Sending…' : 'Email password reset link' }}
            </Button>
        </form>

        <template #footer>
            <Link
                :href="route('login')"
                class="text-sm font-medium text-neutral-500 underline-offset-4 hover:text-neutral-900 hover:underline"
            >
                Back to log in
            </Link>
        </template>
    </AuthCardLayout>
</template>
