<script>
/**
 * Where the nav pill sat on the page we came from.
 *
 * This lives in a plain <script> on purpose: <script setup> is the component's
 * setup function and runs afresh for every instance, so a variable declared
 * there would reset on each navigation — the exact thing this has to outlive.
 * A normal <script> block is evaluated once, when the module loads.
 */
let lastPill = null;
</script>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { useWindowScroll } from '@vueuse/core';
import AmbientBackdrop from '@/Components/AmbientBackdrop.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Toaster } from '@/Components/ui/sonner';
import { useFlashToasts } from '@/composables/useFlashToasts';
import { useTheme } from '@/composables/useTheme';
import { useBrandColors } from '@/composables/useBrandColors';
import { APP_PAGE, CARD } from '@/lib/appStyles';
import { ChevronDown, Menu, X } from 'lucide-vue-next';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

/*
 * The bar only earns its glass once there is content behind it to bend. At the
 * top of the page it stays invisible so the header reads as part of the page.
 *
 * useWindowScroll listens passively and reads on rAF, so this does not fight the
 * scroll thread. The 8px threshold keeps a trackpad's sub-pixel jitter from
 * flickering the background on and off at rest.
 */
const { y: scrollY } = useWindowScroll();
const scrolled = computed(() => scrollY.value > 8);

const { isDark } = useTheme();

const page = usePage();

// Shared from HandleInertiaRequests, so every page has it without a prop.
const branding = computed(
    () => page.props.branding ?? { name: 'SpendLog', logo: null, plain_background: false },
);
const brandInitial = computed(() => (branding.value.name || 'S').charAt(0).toUpperCase());

useFlashToasts();

// Re-applies the admin's colours when they change mid-session — blade only
// applies them on a full document load.
useBrandColors(isDark);

// Mirrors the policies, as SettingsLayout does. Every one of these pages
// authorizes its own view permission server-side, so an unfiltered link is not
// a shortcut — it is a link to a 403.
const granted = computed(() => page.props.auth?.permissions ?? []);
const can = (permission) => granted.value.includes(permission);

const links = computed(() =>
    [
        { label: 'Dashboard', route: 'dashboard', active: 'dashboard', permission: 'dashboard.view' },
        { label: 'Expenses', route: 'expenses.index', active: 'expenses.*', permission: 'expenses.view' },
        { label: 'Budgets', route: 'budgets.index', active: 'budgets.*', permission: 'budgets.view' },
        { label: 'Reports', route: 'reports.index', active: 'reports.*', permission: 'reports.view' },
        { label: 'Categories', route: 'categories.index', active: 'categories.*', permission: 'categories.view' },
    ].filter((link) => can(link.permission)),
);

/*
 * The sliding nav pill.
 *
 * Measured from the DOM rather than computed from the links, because the pill
 * has to track the rendered label — which changes width with the locale and the
 * font, neither of which we can know up front.
 *
 * Module scope, deliberately: every page wraps this layout in its own template,
 * so Inertia tears the nav down and rebuilds it on each visit. A ref would reset
 * and the pill would simply appear at the new tab. Holding the last position
 * outside the component lets the fresh nav start where the old one ended and
 * animate from there — the slide survives the remount.
 *
 * If the layout is ever made persistent (defineOptions({ layout })), this keeps
 * working; it just stops being load-bearing.
 */
const navRef = ref(null);
const pill = ref({ left: 0, width: 0 });
// Off for the first frame, so the pill appears in place instead of flying in
// from the left edge on a cold load.
const pillAnimates = ref(false);

const pillStyle = computed(() => ({
    transform: `translateX(${pill.value.left}px)`,
    width: `${pill.value.width}px`,
    // Width 0 means no link matched (e.g. Settings) — fade out rather than
    // leaving a zero-width sliver parked at the first tab.
    opacity: pill.value.width > 0 ? 1 : 0,
}));

function activePillBox() {
    const active = navRef.value?.querySelector('[aria-current="page"]');

    // Width 0 = no tab matches this route (Settings, say). Keep the last left so
    // the pill fades out in place rather than sliding to the first tab first.
    return active
        ? { left: active.offsetLeft, width: active.offsetWidth }
        : { left: pill.value.left, width: 0 };
}

function measurePill() {
    pill.value = activePillBox();
    lastPill = pill.value;
}

let observer;

onMounted(() => {
    const target = activePillBox();
    const moved = lastPill && lastPill.left !== target.left;

    if (moved && target.width > 0) {
        // Start where the previous page's nav left off, unanimated...
        pillAnimates.value = false;
        pill.value = lastPill;

        // ...let that paint, then turn the transition on and move. Two frames:
        // enabling the transition and setting the target in one go would let the
        // browser collapse both into a single style resolution and skip the
        // animation entirely — which is exactly what a teleport looks like.
        requestAnimationFrame(() => {
            pillAnimates.value = true;
            requestAnimationFrame(() => {
                pill.value = target;
            });
        });
    } else {
        pill.value = target;
        requestAnimationFrame(() => (pillAnimates.value = true));
    }

    lastPill = target;

    // Catches everything a resize listener would, plus label widths changing
    // when the locale switches or the webfont finishes loading.
    observer = new ResizeObserver(measurePill);
    observer.observe(navRef.value);
});

onBeforeUnmount(() => observer?.disconnect());

// Inertia swaps the page without remounting the layout, so the active link
// changes with no lifecycle hook to hang this on.
watch(() => page.url, () => nextTick(measurePill));
</script>

<template>
    <div :class="APP_PAGE">
        <!-- Off once a background colour is chosen: the wash lies over the
             page, so it would tint the chosen colour into a gradient rather
             than leave it as the colour that was picked. -->
        <AmbientBackdrop v-if="!branding.plain_background" />

        <div class="mx-auto max-w-6xl px-3 pb-10 lg:px-4">
            <!--
                The bar floats on the page rather than spanning it edge to edge,
                echoing the panel geometry of the auth screens.

                The wrapper is what sticks, not the <nav>: it cancels the
                container's gutter with -mx/px so the glass can reach the panel
                edges while the nav's contents stay on the same grid as the
                cards below. Nothing here changes size on scroll — only colour,
                border, shadow and blur animate, so the page never reflows and
                the links never shift under the pointer.
            -->
            <div
                class="sticky top-0 z-40 -mx-3 px-3 transition-[background-color,border-color,box-shadow,backdrop-filter] duration-300 ease-out lg:-mx-4 lg:px-4"
                :class="
                    scrolled
                        ? 'rounded-b-[28px] border-b border-neutral-200/70 bg-white/70 shadow-[0_8px_24px_-12px_rgba(15,23,42,0.15)] backdrop-blur-xl backdrop-saturate-150 dark:border-white/10 dark:bg-neutral-900/60 dark:shadow-[0_8px_24px_-12px_rgba(0,0,0,0.6)]'
                        : 'border-b border-transparent'
                "
            >
            <nav class="flex h-20 items-center justify-between gap-4">
                <div class="flex items-center gap-6">
                    <Link
                        :href="route('dashboard')"
                        class="flex shrink-0 items-center gap-2 text-sm font-bold tracking-tight"
                    >
                        <!-- An uploaded logo replaces the lettermark; without one
                             we fall back to the app name's initial. -->
                        <img
                            v-if="branding.logo"
                            :src="branding.logo"
                            :alt="branding.name"
                            class="size-7 shrink-0 rounded-lg object-contain"
                        />
                        <!-- The mark wears the brand colour: it stands in for the
                             logo, so it is the one thing that should obviously be
                             the admin's colour. Theme-aware by default, like every
                             other use of the token. -->
                        <span
                            v-else
                            class="bg-primary text-primary-foreground grid size-7 place-items-center rounded-lg text-[13px] font-extrabold"
                        >
                            {{ brandInitial }}
                        </span>
                        <span class="hidden sm:inline">{{ branding.name }}</span>
                    </Link>

                    <div ref="navRef" class="relative hidden items-center gap-1 md:flex">
                        <!-- One pill for the whole nav, slid to the active link.
                             aria-hidden: it is decoration, and the link already
                             carries aria-current="page". -->
                        <span
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-y-0 left-0 rounded-full bg-primary will-change-transform motion-reduce:transition-none"
                            :class="pillAnimates ? 'transition-[transform,width,opacity] duration-300 ease-out' : ''"
                            :style="pillStyle"
                        />
                        <NavLink
                            v-for="link in links"
                            :key="link.route"
                            :href="route(link.route)"
                            :active="route().current(link.active)"
                        >
                            {{ __(link.label) }}
                        </NavLink>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="hidden items-center gap-2 sm:flex">
                        <LocaleSwitcher />
                        <ThemeToggle />
                    </div>

                    <div class="hidden md:block">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full py-1.5 pe-2 ps-3 text-sm font-semibold text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                                >
                                    {{ $page.props.auth.user.name }}

                                    <span
                                        v-if="$page.props.auth.is_admin"
                                        class="rounded-full bg-neutral-900 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white dark:bg-neutral-100 dark:text-neutral-900"
                                    >
                                        {{ __('Admin') }}
                                    </span>

                                    <ChevronDown class="size-4 text-neutral-400" />
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('settings')">
                                    {{ __('Settings') }}
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    {{ __('Log Out') }}
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>

                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 md:hidden dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                        :aria-expanded="showingNavigationDropdown"
                        :aria-label="showingNavigationDropdown ? 'Close menu' : 'Open menu'"
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                    >
                        <component :is="showingNavigationDropdown ? X : Menu" class="size-5" />
                    </button>
                </div>
            </nav>

            <!-- Mobile menu -->
            <div
                v-show="showingNavigationDropdown"
                :class="[CARD, 'anim mb-3 p-2 md:hidden']"
            >
                <div class="flex flex-col gap-0.5">
                    <ResponsiveNavLink
                        v-for="link in links"
                        :key="link.route"
                        :href="route(link.route)"
                        :active="route().current(link.active)"
                    >
                        {{ __(link.label) }}
                    </ResponsiveNavLink>
                </div>

                <div class="mt-2 border-t border-neutral-100 pt-2 dark:border-neutral-800">
                    <div class="px-4 py-2">
                        <p class="text-sm font-semibold">
                            {{ $page.props.auth.user.name }}
                        </p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $page.props.auth.user.email }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <ResponsiveNavLink :href="route('settings')">
                            {{ __('Settings') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                            {{ __('Log Out') }}
                        </ResponsiveNavLink>
                    </div>

                    <div class="flex items-center gap-2 px-4 pb-1 pt-3 sm:hidden">
                        <LocaleSwitcher />
                        <ThemeToggle />
                    </div>
                </div>
            </div>
            </div>
            <!-- /sticky bar — the mobile menu lives inside it so an open menu
                 scrolls with the bar rather than being left behind. -->

            <header v-if="$slots.header" class="anim pb-6 pt-2" style="--d: 40ms">
                <slot name="header" />
            </header>

            <main>
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
</template>
