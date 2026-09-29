<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';
import AuthCardLayout from '@/Layouts/AuthCardLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';

const props = defineProps({
    status: { type: String },
});

const email = computed(() => usePage().props.auth?.user?.email);

const form = useForm({ code: '' });

// Its own form, so a resend error or spinner never lands on the code field's.
const resend = useForm({});

const submit = () => {
    form.post(route('verification.verify'), {
        onError: () => form.reset('code'),
    });
};

const resendCode = () => {
    resend.post(route('verification.send'), { preserveScroll: true });
};

const codeSent = computed(() => props.status === 'verification-code-sent');
</script>

<template>
    <Head title="Verify your email" />

    <AuthCardLayout heading="Check your inbox">
        <template #icon>
            <MailCheck class="size-6 text-[#4b9d5f]" aria-hidden="true" />
        </template>

        <template #description>
            We sent a 6-digit code to
            <span v-if="email" class="font-semibold text-neutral-900 dark:text-neutral-100">{{ email }}</span>
            <span v-else>your email address</span>.
            Type it below to confirm it's you.
        </template>

        <div
            v-if="codeSent"
            class="mb-4 rounded-xl bg-[#eaf5e6] px-4 py-3 text-center text-sm font-medium text-[#2f6b3d] dark:bg-[#16281a] dark:text-[#8fd4a0]"
        >
            A new code is on its way.
        </div>

        <form @submit.prevent="submit">
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
                :aria-invalid="!!(form.errors.code || resend.errors.code)"
                :class="[PILL_INPUT, 'text-center text-lg font-semibold tracking-[0.5em]']"
            />
            <p
                v-if="form.errors.code || resend.errors.code"
                class="mt-1.5 px-5 text-xs font-medium text-red-600 dark:text-red-400"
            >
                {{ form.errors.code || resend.errors.code }}
            </p>

            <Button type="submit" :disabled="form.processing" :class="[PILL_BUTTON, 'mt-4']">
                {{ form.processing ? 'Verifying…' : 'Verify email' }}
            </Button>
        </form>

        <button
            type="button"
            :disabled="resend.processing"
            class="mt-4 w-full text-center text-sm font-medium text-[#4b9d5f] underline-offset-4 hover:underline disabled:opacity-60"
            @click="resendCode"
        >
            {{ resend.processing ? 'Sending…' : "Didn't get it? Send a new code" }}
        </button>

        <template #footer>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm font-medium text-neutral-500 underline-offset-4 hover:text-neutral-900 hover:underline dark:text-neutral-400 dark:hover:text-neutral-100"
            >
                Log out
            </Link>
        </template>
    </AuthCardLayout>
</template>
