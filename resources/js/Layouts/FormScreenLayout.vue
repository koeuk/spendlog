<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ArrowLeft } from 'lucide-vue-next';
import AmbientBackdrop from '@/Components/AmbientBackdrop.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Toaster } from '@/Components/ui/sonner';
import { useTheme } from '@/composables/useTheme';
import { useBrandColors } from '@/composables/useBrandColors';
import { APP_PAGE, CARD } from '@/lib/appStyles';

/**
 * A create/edit screen: one route, two presentations.
 *
 * On a phone it is a pushed screen — no brand bar, no tab bar, no card, just the
 * way back and the form. A form reached from a list is a detour, and the app
 * frame works against that: five tabs are five ways to abandon a half-filled
 * form, and the header spends the top of the screen on a workspace switcher
 * nobody needs mid-task. Native apps push these over the tab bar, so this does
 * too.
 *
 * On a desk none of that applies. A pointer is precise, the chrome costs
 * nothing at 1440px, and a lone form floating in whitespace reads as a broken
 * page rather than a deliberate one — so above sm the app shell and the card
 * come back and the screen looks like every other page.
 *
 * The branch is a whole layout swap, which means crossing 640px mid-form
 * remounts the fields and clears them. That only happens when someone drags a
 * window across the breakpoint with a form open; the alternative is rendering
 * both trees and hiding one, which doubles the DOM on every visit to save a
 * case that essentially does not occur.
 */
defineProps({
    backHref: { type: String, required: true },
    title: { type: String, required: true },
    backLabel: { type: String, default: 'Back' },
});

const isMobile = useMediaQuery('(max-width: 639px)');

const page = usePage();
const { isDark } = useTheme();

// Only the phone branch owns the page shell. The desktop branch is inside
// AuthenticatedLayout, which already applies both.
useBrandColors(isDark);

const branding = computed(
    () => page.props.branding ?? { name: 'SpendLog', logo: null, plain_background: false },
);
</script>

<template>
    <!-- Phone: the pushed screen. -->
    <div v-if="isMobile" :class="APP_PAGE">
        <AmbientBackdrop v-if="!branding.plain_background" />

        <!--
            screen-push is on the column, not on the page root: the root holds
            AmbientBackdrop, which is fixed, and a transformed ancestor makes a
            fixed child position against it instead of the viewport — the
            backdrop would travel with the form and leave a bare strip down the
            edge. The backdrop stays put and the screen slides over it, which is
            the effect anyway.
        -->
        <div class="screen-push mx-auto flex min-h-screen max-w-2xl flex-col px-3">
            <!--
                The bar spans the screen rather than floating as a card: this is
                chrome, not content, and it is the only chrome here.

                Sticky and blurred so a long form scrolls under it — the title is
                what says which record you are in, and that is worth as much at
                the bottom of the form as at the top.
            -->
            <header
                class="sticky top-0 z-40 -mx-3 flex items-center gap-3 border-b border-border/60 bg-background/80 px-3 py-3 backdrop-blur-xl"
            >
                <!-- A circle, not a bare glyph: it reads as a control at a
                     glance, and it is the 44px target the arrow alone was not. -->
                <Link
                    :href="backHref"
                    :aria-label="backLabel"
                    class="grid size-11 shrink-0 place-items-center rounded-full border border-border bg-card/70 text-foreground transition hover:bg-muted"
                >
                    <ArrowLeft class="size-5" aria-hidden="true" />
                </Link>

                <h1 class="min-w-0 flex-1 truncate text-center text-lg font-semibold leading-tight">
                    {{ title }}
                </h1>

                <!-- Balances the back button so the title is centred on the bar
                     rather than on the space left over beside it. -->
                <div class="flex size-11 shrink-0 items-center justify-end">
                    <slot name="actions" />
                </div>
            </header>

            <!-- A flex column, so the form can claim the full height and push
                 its action bar onto the bottom edge with mt-auto. -->
            <main class="flex flex-1 flex-col pt-5">
                <slot />
            </main>
        </div>

        <Toaster
            :theme="isDark ? 'dark' : 'light'"
            position="top-right"
            rich-colors
            close-button
        />
    </div>

    <!-- Desk: an ordinary page in the app, exactly like the rest of them. -->
    <AuthenticatedLayout v-else>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="backHref"
                    :aria-label="backLabel"
                    class="grid size-10 shrink-0 place-items-center rounded-full border border-border bg-card/70 text-foreground transition hover:bg-muted"
                >
                    <ArrowLeft class="size-5" aria-hidden="true" />
                </Link>

                <h1 class="truncate text-2xl font-bold tracking-tight">
                    {{ title }}
                </h1>

                <div class="ms-auto"><slot name="actions" /></div>
            </div>
        </template>

        <!-- The full page column, like every other screen in the app. The card
             was capped at 2xl before, which left a form sitting in a third of a
             1440px window with two thirds of empty page beside it — the lone
             narrow panel in an app whose lists all run the full width.

             Reading length is a property of the fields, not of the card, so it
             is handled where it belongs: the form's own grid keeps inputs to
             sensible spans, rather than the shell squeezing everything. -->
        <div :class="[CARD, 'p-6 sm:p-8']">
            <slot />
        </div>
    </AuthenticatedLayout>
</template>
