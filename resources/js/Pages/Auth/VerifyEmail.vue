<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

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

    <div class="grid min-h-screen place-items-center bg-white px-4 py-10 font-display text-neutral-900">
        <div class="w-full max-w-[420px] text-center">
            <div class="anim mx-auto grid size-14 place-items-center rounded-2xl bg-[#f1f7ef]" style="--d: 0ms">
                <MailCheck class="size-6 text-[#4b9d5f]" aria-hidden="true" />
            </div>

            <h1
                class="anim mt-6 text-3xl font-extrabold leading-tight tracking-[-0.03em]"
                style="--d: 60ms"
            >
                Check your inbox
            </h1>

            <p class="anim mt-3 text-sm leading-relaxed text-neutral-500" style="--d: 120ms">
                We sent you a link to confirm your email address. Click it and
                you're in. Didn't get it? We'll happily send another.
            </p>

            <div
                v-if="verificationLinkSent"
                class="anim mt-6 rounded-2xl bg-[#eaf5e6] px-4 py-3 text-sm font-medium text-[#2f6b3d]"
                style="--d: 0ms"
            >
                A new verification link is on its way.
            </div>

            <form class="anim mt-8" style="--d: 180ms" @submit.prevent="submit">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="h-[54px] w-full rounded-full text-sm font-semibold active:translate-y-0 active:scale-[0.99]"
                >
                    {{ form.processing ? 'Sending…' : 'Resend verification email' }}
                </Button>
            </form>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="anim mt-6 text-sm font-medium text-neutral-500 underline-offset-4 hover:text-neutral-900 hover:underline"
                style="--d: 240ms"
            >
                Log out
            </Link>
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
