<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

/**
 * The centred shell for the short auth detours — verify email, forgot/reset
 * password, confirm password. Login and Register use the split-screen layout
 * instead; these are single-purpose tasks, so they get a single column.
 */
defineProps({
    heading: { type: String, required: true },
});

const page = usePage();

// Shared from HandleInertiaRequests, so these pages have it without a prop —
// same source AuthenticatedLayout reads. Hardcoding the name here meant an app
// called MoneyLog greeted people with "SpendLog" on exactly the screens they
// reach when they are already confused.
const branding = computed(
    () => page.props.branding ?? { name: 'SpendLog', logo: null },
);
const brandInitial = computed(() => (branding.value.name || 'S').charAt(0).toUpperCase());
</script>

<template>
    <!-- bg-background/text-foreground, not bg-white/text-neutral-900: this shell
         covers the whole viewport, so a literal white paints straight over the
         admin's body colour and the setting silently does nothing here. The
         tokens are already theme-aware, so the dark: overrides are not needed.
         Same reasoning as APP_PAGE in lib/appStyles.js. -->
    <div class="relative grid min-h-screen place-items-center bg-background px-4 py-10 font-display text-foreground">
        <div class="absolute right-4 top-4 flex items-center gap-2">
            <LocaleSwitcher />
            <ThemeToggle />
        </div>

        <div class="w-full max-w-[420px] text-center">
            <Link
                href="/"
                class="anim inline-flex items-center gap-2 text-sm font-bold tracking-tight"
                style="--d: 0ms"
            >
                <img
                    v-if="branding.logo"
                    :src="branding.logo"
                    :alt="branding.name"
                    class="size-7 shrink-0 rounded-lg object-contain"
                />
                <span
                    v-else
                    class="bg-primary text-primary-foreground grid size-7 place-items-center rounded-lg text-[13px] font-extrabold"
                >
                    {{ brandInitial }}
                </span>
                {{ branding.name }}
            </Link>

            <div
                v-if="$slots.icon"
                class="anim mx-auto mt-8 grid size-14 place-items-center rounded-2xl bg-[#f1f7ef] dark:bg-[#16281a]"
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
                class="anim mx-auto mt-3 max-w-[360px] text-sm leading-relaxed text-neutral-500 dark:text-neutral-400"
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
