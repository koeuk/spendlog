<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';
import AuthCardLayout from '@/Layouts/AuthCardLayout.vue';
import { Button } from '@/Components/ui/button';
import { PILL_BUTTON } from '@/lib/authStyles';

const props = defineProps({
    status: { type: String },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <Head title="Verify your email" />

    <AuthCardLayout heading="Check your inbox">
        <template #icon>
            <MailCheck class="size-6 text-[#4b9d5f]" aria-hidden="true" />
        </template>

        <template #description>
            We sent you a link to confirm your email address. Click it and you're
            in. Didn't get it? We'll happily send another.
        </template>

        <div
            v-if="verificationLinkSent"
            class="mb-4 rounded-2xl bg-[#eaf5e6] px-4 py-3 text-center text-sm font-medium text-[#2f6b3d]"
        >
            A new verification link is on its way.
        </div>

        <form @submit.prevent="submit">
            <Button type="submit" :disabled="form.processing" :class="PILL_BUTTON">
                {{ form.processing ? 'Sending…' : 'Resend verification email' }}
            </Button>
        </form>

        <template #footer>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm font-medium text-neutral-500 underline-offset-4 hover:text-neutral-900 hover:underline"
            >
                Log out
            </Link>
        </template>
    </AuthCardLayout>
</template>
