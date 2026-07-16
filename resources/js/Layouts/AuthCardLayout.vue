<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The centred shell for the short auth detours — verify email, forgot/reset
 * password, confirm password. Login and Register use the split-screen layout
 * instead; these are single-purpose tasks, so they get a single column.
 */
defineProps({
    heading: { type: String, required: true },
});
</script>

<template>
    <div class="grid min-h-screen place-items-center bg-white px-4 py-10 font-display text-neutral-900">
        <div class="w-full max-w-[420px] text-center">
            <Link
                href="/"
                class="anim inline-flex items-center gap-2 text-sm font-bold tracking-tight"
                style="--d: 0ms"
            >
                <span class="grid size-7 place-items-center rounded-lg bg-neutral-900 text-[13px] font-extrabold text-white">
                    S
                </span>
                SpendLog
            </Link>

            <div
                v-if="$slots.icon"
                class="anim mx-auto mt-8 grid size-14 place-items-center rounded-2xl bg-[#f1f7ef]"
                style="--d: 60ms"
            >
                <slot name="icon" />
            </div>

            <h1
                class="anim text-3xl font-extrabold leading-tight tracking-[-0.03em]"
                :class="$slots.icon ? 'mt-6' : 'mt-8'"
                style="--d: 120ms"
            >
                {{ heading }}
            </h1>

            <p
                v-if="$slots.description"
                class="anim mx-auto mt-3 max-w-[360px] text-sm leading-relaxed text-neutral-500"
                style="--d: 160ms"
            >
                <slot name="description" />
            </p>

            <div class="anim mt-8 text-left" style="--d: 200ms">
                <slot />
            </div>

            <div v-if="$slots.footer" class="anim mt-8" style="--d: 260ms">
                <slot name="footer" />
            </div>
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
