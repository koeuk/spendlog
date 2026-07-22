<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AuthArtwork from '@/Components/AuthArtwork.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { AUTH_HEADER } from '@/lib/authStyles';

/**
 * The shell shared by Login and Register: one route, two presentations.
 *
 * On a phone it is a coloured header with the heading on it and a white sheet
 * rising over the bottom of that colour, holding the fields. On a desk it is
 * the split screen it has always been — form on the left, artwork on the right.
 *
 * One DOM tree, switched with breakpoint classes, rather than two trees with
 * one hidden. The form is the whole content of these pages, so rendering it
 * twice would mean two sets of inputs with the same ids and names — a password
 * manager filling the hidden copy, and a duplicate-id violation on every visit.
 * The cost is that the wrappers carry busy class strings; the comments below
 * mark which half each belongs to.
 *
 * The header colour is the brand token, not a literal green: an admin who picks
 * a colour expects the screens their users see first to use it.
 */
defineProps({
    heading: { type: String, required: true },
    // Rotating lines beside the form on a desk. Pass a computed, so they
    // re-resolve when the locale changes.
    slides: { type: Array, default: () => [] },
});

const page = usePage();

const branding = computed(() => page.props.branding ?? { name: 'SpendLog', logo: null });
const brandInitial = computed(() => (branding.value.name || 'S').charAt(0).toUpperCase());
</script>

<template>
    <div class="min-h-screen font-display lg:bg-white lg:p-4 lg:text-neutral-900 lg:dark:bg-neutral-950 lg:dark:text-neutral-100">
        <div class="lg:grid lg:min-h-[calc(100vh-2rem)] lg:grid-cols-2 lg:gap-4">
            <!-- The form column. On a phone it is the whole screen, and the
                 colour behind it is what the sheet rises off. -->
            <!-- AUTH_HEADER carries the colour and its own white text; both are
                 undone from lg: up, where this column is an ordinary white page. -->
            <div
                class="relative flex min-h-screen flex-col lg:min-h-0 lg:justify-center lg:bg-transparent lg:px-8 lg:py-10 lg:text-inherit"
                :class="AUTH_HEADER"
            >
                <!-- The theme toggle is a bare glyph, so on the colour it needs
                     a light one; scoped to its own button rather than set on
                     this row, which would also repaint the locale switcher's
                     active pill white on its white chip and leave it blank. -->
                <div class="absolute right-4 top-4 z-10 flex items-center gap-2">
                    <LocaleSwitcher />
                    <span class="max-lg:[&_button]:text-white/85 max-lg:[&_button:hover]:bg-white/10 max-lg:[&_button:hover]:text-white">
                        <ThemeToggle />
                    </span>
                </div>

                <!-- The coloured header. Its bottom padding is what the sheet
                     overlaps, so the two are sized together: pb-16 here against
                     the sheet's -mt-8 leaves the branding clear of the fold.

                     On a phone the branding is the whole header — centred, logo
                     over name — since the heading and description are hidden
                     below lg and the sheet carries the form straight away. On a
                     desk the block is left-aligned and the heading returns. -->
                <div class="px-6 pb-16 pt-20 max-lg:text-center lg:mb-10 lg:p-0">
                    <Link
                        href="/"
                        class="anim inline-flex items-center gap-2 text-sm font-bold tracking-tight max-lg:flex-col max-lg:gap-3 max-lg:text-xl"
                        style="--d: 0ms"
                    >
                        <img
                            v-if="branding.logo"
                            :src="branding.logo"
                            :alt="branding.name"
                            class="size-7 shrink-0 rounded-lg object-contain max-lg:size-20 max-lg:rounded-3xl"
                        />
                        <!-- On the phone the mark sits on the brand colour, so
                             it inverts: a primary-on-primary badge is invisible.
                             On the desk it is the usual filled badge. -->
                        <span
                            v-else
                            class="grid size-7 place-items-center rounded-lg text-[13px] font-extrabold max-lg:size-20 max-lg:rounded-3xl max-lg:bg-white/15 max-lg:text-3xl lg:bg-primary lg:text-primary-foreground"
                        >
                            {{ brandInitial }}
                        </span>
                        {{ branding.name }}
                    </Link>

                    <!-- Heading and description are the desk story only: on the
                         phone the centred branding above stands alone, so both
                         are hidden rather than leaving a subtitle with no title. -->
                    <h1
                        class="anim mt-8 text-[2.6rem] font-extrabold leading-[1.05] tracking-[-0.03em] max-lg:hidden lg:mt-10 lg:text-5xl"
                        style="--d: 60ms"
                    >
                        {{ heading }}
                    </h1>

                    <p
                        v-if="$slots.description"
                        class="anim mt-3 text-sm leading-relaxed max-lg:hidden lg:text-neutral-500 lg:dark:text-neutral-400"
                        style="--d: 120ms"
                    >
                        <slot name="description" />
                    </p>
                </div>

                <!-- The sheet.
                     flex-1 so it reaches the bottom of a tall phone rather than
                     ending under the last field with the colour showing beneath,
                     and rounded only at the top because the bottom edge is the
                     bottom of the screen. All of it is undone from lg: up, where
                     the form sits on the page like it always did. -->
                <div
                    class="anim flex-1 rounded-t-[32px] bg-white px-6 pb-10 pt-8 max-lg:-mt-8 dark:bg-neutral-950 lg:flex-none lg:rounded-none lg:bg-transparent lg:p-0 lg:dark:bg-transparent"
                    style="--d: 160ms"
                >
                    <div class="mx-auto w-full max-w-[380px] lg:mx-0">
                        <slot />
                    </div>
                </div>
            </div>

            <AuthArtwork v-if="slides.length" class="anim max-lg:hidden" style="--d: 200ms" :slides="slides" />
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
