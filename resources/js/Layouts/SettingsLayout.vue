<script>
/**
 * Where the marker sat on the settings page we came from.
 *
 * Every settings page wraps this layout in its own template, so Inertia tears
 * the nav down and builds a new one on each visit — the element does not
 * survive, which a probe confirms. A ref would therefore reset on arrival and
 * the marker would simply be *there*, under the new tab, having travelled
 * nowhere.
 *
 * Holding the last position outside the component lets the fresh nav start
 * where the old one ended and animate from there. Same technique, and the same
 * reason, as lastPill in AuthenticatedLayout.
 */
let lastMarker = null;
</script>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CircleHelp, Dumbbell, FileText, HandCoins, Palette, ShieldCheck, SwatchBook, UserRound, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD, EYEBROW, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

defineProps({
    heading: { type: String, required: true },
    description: { type: String, default: '' },
    /**
     * Drop the panel's own card on a phone, for pages whose content is already
     * cards.
     *
     * Most settings pages put plain form fields in this panel, and the card is
     * what holds them together. The few that render a list of cards instead
     * (Footer pages, FAQs) end up with a card inside a card — two borders and
     * two blurs a few pixels apart, which at 430px reads as a rendering fault
     * rather than as a hierarchy.
     *
     * Phones only. From sm: up the panel is inset from a wide page and the
     * nesting looks intentional, which is where the outer card earns the
     * grouping it provides.
     */
    flush: { type: Boolean, default: false },
});

const page = usePage();

const isAdmin = computed(() => Boolean(page.props.auth?.is_admin));

// Mirrors the policies. A non-admin granted users.view should see the item; a
// role check alone would hide it from them.
const granted = computed(() => page.props.auth?.permissions ?? []);
const can = (permission) => granted.value.includes(permission);

// The admin pages are admin-only server-side too — this just keeps them out of
// the way for everyone else.
const items = computed(() =>
    [
        { key: 'profile', label: trans('Profile'), href: route('profile.edit'), icon: UserRound, pattern: 'profile.*' },
        { key: 'password', label: trans('Password'), href: route('password.edit'), icon: ShieldCheck, pattern: 'password.edit' },
        can('users.view')
            ? { key: 'users', label: trans('Users'), href: route('users.index'), icon: Users, pattern: 'users.*' }
            : null,
        can('settings.branding')
            ? { key: 'branding', label: trans('Appearance'), href: route('branding.edit'), icon: Palette, pattern: 'branding.*' }
            : null,
        can('settings.branding')
            ? { key: 'colors', label: trans('Colours'), href: route('colors.edit'), icon: SwatchBook, pattern: 'colors.*' }
            : null,
        can('settings.branding')
            ? { key: 'spending', label: trans('Spending'), href: route('spending.edit'), icon: HandCoins, pattern: 'spending.*' }
            : null,
        // Follows the module, not the admin flag: whoever was granted exercise
        // needs their unit preference, admin or not.
        can('exercise.view')
            ? { key: 'exercise', label: trans('Exercise'), href: route('exercise-settings.edit'), icon: Dumbbell, pattern: 'exercise-settings.*' }
            : null,
        can('settings.faq')
            ? { key: 'faqs', label: trans('Help / FAQ'), href: route('faqs.index'), icon: CircleHelp, pattern: 'faqs.*' }
            : null,
        can('settings.pages')
            ? { key: 'pages', label: trans('Footer pages'), href: route('pages.index'), icon: FileText, pattern: 'pages.*' }
            : null,
    ].filter(Boolean),
);

function isActive(pattern) {
    return route().current(pattern);
}

/*
 * The moving indicator behind the active tab.
 *
 * One element that slides, rather than a background on each link: nine tabs
 * lighting up and going dark independently read as nine separate things
 * happening, where a single marker travelling to the new tab reads as one.
 *
 * It lives inside the scroller and is measured with offsetLeft/offsetTop, so it
 * scrolls with the links for free and needs no correction for scrollLeft. Both
 * axes are set every time because the nav is a row on a phone and a column from
 * lg: up — the same marker serves both, and a resize between them is just
 * another measurement.
 */
const navRef = ref(null);
const marker = ref({ x: 0, y: 0, w: 0, h: 0 });
// Held off for the first frame so the marker appears under the current tab
// instead of sliding in from the corner on a cold load.
const markerAnimates = ref(false);
const hasMarker = computed(() => marker.value.w > 0);

const markerStyle = computed(() => ({
    transform: `translate(${marker.value.x}px, ${marker.value.y}px)`,
    width: `${marker.value.w}px`,
    height: `${marker.value.h}px`,
    opacity: hasMarker.value ? 1 : 0,
}));

function activeBox() {
    const active = navRef.value?.querySelector('[aria-current="page"]');

    // Width 0 = nothing matched. Keep the last position and fade out, rather
    // than collapsing to a sliver parked at the first tab.
    return active
        ? {
              x: active.offsetLeft,
              y: active.offsetTop,
              w: active.offsetWidth,
              h: active.offsetHeight,
          }
        : { ...marker.value, w: 0 };
}

function measure() {
    marker.value = activeBox();
    lastMarker = marker.value;
}

/*
 * Which edges are still hiding tabs.
 *
 * The fade is only drawn on a side there is actually more to reach — a
 * permanent gradient on both ends dims the first and last tab for no reason,
 * and says "scrollable" just as loudly when it is not.
 */
const atStart = ref(true);
const atEnd = ref(true);

/*
 * The fade, as a mask rather than a gradient in the page colour.
 *
 * A `from-background` overlay only disappears against a plain background: over
 * the ambient wash it was a white block sitting on green. Masking fades the
 * tabs themselves to transparent, so it is right on any backdrop and in either
 * theme.
 *
 * No breakpoint guard needed. From lg: up the nav is a column with
 * overflow-visible, so nothing scrolls, both edges report true and this returns
 * no mask at all.
 */
const FADE = '1.5rem';

const maskStyle = computed(() => {
    if (atStart.value && atEnd.value) {
        return {};
    }

    const from = atStart.value ? '0px' : FADE;
    const to = atEnd.value ? '0px' : FADE;
    const gradient = `linear-gradient(to right, transparent 0, #000 ${from}, #000 calc(100% - ${to}), transparent 100%)`;

    return { maskImage: gradient, WebkitMaskImage: gradient };
});

function readEdges() {
    const nav = navRef.value;

    if (!nav) {
        return;
    }

    // 1px of slack: fractional scroll positions never land exactly on the end.
    atStart.value = nav.scrollLeft <= 1;
    atEnd.value = nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 1;
}

/**
 * Bring the current tab into view.
 *
 * Without this, opening Footer pages — the last of nine — showed a nav scrolled
 * to the start with no sign the active tab existed. block: 'nearest' keeps this
 * to the horizontal axis; without it the browser is free to scroll the window
 * as well and the page would open part-way down.
 */
function revealActive(behavior) {
    navRef.value
        ?.querySelector('[aria-current="page"]')
        ?.scrollIntoView({ inline: 'center', block: 'nearest', behavior });
}

let observer;

onMounted(() => {
    const target = activeBox();
    // A remount carrying a different position means a tab was clicked. Compare
    // both axes: the row runs horizontally on a phone and vertically from lg: up.
    const moved = lastMarker && (lastMarker.x !== target.x || lastMarker.y !== target.y);

    if (moved && target.w > 0) {
        // Start where the previous page's nav left off, unanimated...
        markerAnimates.value = false;
        marker.value = lastMarker;

        // ...let that paint, then turn the transition on and move. Two frames,
        // because enabling the transition and setting the target in one go lets
        // the browser collapse both into a single style resolution and skip the
        // animation — which looks exactly like the teleport this replaces.
        requestAnimationFrame(() => {
            markerAnimates.value = true;
            requestAnimationFrame(() => {
                marker.value = target;
            });
        });
    } else {
        marker.value = target;
        requestAnimationFrame(() => (markerAnimates.value = true));
    }

    lastMarker = target;

    readEdges();
    // 'auto', not 'smooth': on a fresh load there is nothing to follow, so an
    // animated scroll is just the page appearing to settle late.
    revealActive('auto');
    nextTick(readEdges);

    // Catches the row/column switch at lg, and label widths changing when the
    // locale switches or the webfont lands.
    observer = new ResizeObserver(() => {
        measure();
        readEdges();
    });
    observer.observe(navRef.value);
});

onBeforeUnmount(() => observer?.disconnect());

// Inertia swaps the panel without remounting this layout, so the active tab
// changes with no lifecycle hook to hang the re-measure on.
watch(
    () => page.url,
    () =>
        nextTick(() => {
            measure();
            revealActive('smooth');
        }),
);
</script>

<template>
    <!-- No brand bar on a phone. Settings is somewhere you go to change one
         thing and leave, and the bar's own contents — the workspace switcher,
         the burger — are the least of what you came for. The More tab that got
         you here still holds everything the burger did. -->
    <AuthenticatedLayout hide-nav-on-mobile>
        <template #header>
            <div>
                <p :class="EYEBROW">{{ __('Account') }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                    {{ __('Settings') }}
                </h1>
            </div>
        </template>

        <!-- Width and gutters come from the layout's one container, so the
             column never resizes when navigating between pages. -->
        <div class="flex flex-col gap-4 lg:flex-row lg:gap-6">
            <!--
                The nav scrolls horizontally on narrow screens rather than
                stacking: three pills above the panel would push the content
                the user came for below the fold on a phone.
            -->
            <aside class="anim lg:w-52 lg:shrink-0" style="--d: 0ms">
                <!--
                    The mask goes on this wrapper, not on the scroller: the
                    wrapper does not scroll, so the fade is pinned to the edges
                    of the row instead of riding along with the tabs.
                -->
                <div class="relative" :style="maskStyle">
                    <!--
                        The scrollbar is hidden, not absent: the row still
                        scrolls by touch and by wheel. A native bar under nine
                        pills is a second horizontal line arguing with the marker,
                        and the fades already say the row continues.

                        scroll-smooth so revealActive glides rather than jumps.
                    -->
                    <nav
                        ref="navRef"
                        class="relative flex gap-1 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] lg:flex-col lg:overflow-visible lg:pb-0 [&::-webkit-scrollbar]:hidden"
                        @scroll="readEdges"
                    >
                        <!--
                            Behind the links, never over them: it carries the
                            colour, they carry the text. transform rather than
                            left/top so the slide runs on the compositor.
                        -->
                        <span
                            class="pointer-events-none absolute left-0 top-0 z-0 rounded-full bg-primary"
                            :class="
                                markerAnimates
                                    ? 'transition-[transform,width,height,opacity] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none'
                                    : ''
                            "
                            :style="markerStyle"
                            aria-hidden="true"
                        />

                        <Link
                            v-for="item in items"
                            :key="item.key"
                            :href="item.href"
                            :aria-current="isActive(item.pattern) ? 'page' : undefined"
                            class="relative z-10 flex shrink-0 items-center gap-2.5 rounded-full px-3.5 py-2.5 text-sm font-semibold transition-colors duration-200"
                            :class="
                                isActive(item.pattern)
                                    ? 'text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
            </aside>

            <div class="min-w-0 flex-1 space-y-3">
                <!-- The card is still applied and then undone below sm, rather
                     than composed per breakpoint: CARD is one string of six
                     utilities, and splitting it into sm:-prefixed copies here
                     is a second definition of the surface to keep in step with
                     appStyles. -->
                <div
                    :class="[
                        CARD,
                        'anim p-6 sm:p-8',
                        flush
                            ? 'max-sm:rounded-none max-sm:border-0 max-sm:bg-transparent max-sm:p-0 max-sm:shadow-none max-sm:backdrop-blur-none'
                            : '',
                    ]"
                    style="--d: 60ms"
                >
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold tracking-[-0.02em]">
                                {{ heading }}
                            </h2>
                            <p v-if="description" class="mt-1 text-sm" :class="MUTED">
                                {{ description }}
                            </p>
                        </div>

                        <!-- For a panel-level action, e.g. "Add user". -->
                        <slot name="actions" />
                    </header>

                    <div class="mt-6">
                        <slot />
                    </div>
                </div>

                <slot name="after" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
