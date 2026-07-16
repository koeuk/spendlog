<script setup>
import { ref, computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Toaster } from '@/Components/ui/sonner';
import { useFlashToasts } from '@/composables/useFlashToasts';
import { useTheme } from '@/composables/useTheme';
import { APP_PAGE } from '@/lib/appStyles';
import { ChevronDown, Menu, X } from 'lucide-vue-next';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

const { isDark } = useTheme();

const page = usePage();

// Shared from HandleInertiaRequests, so every page has it without a prop.
const branding = computed(() => page.props.branding ?? { name: 'SpendLog', logo: null });
const brandInitial = computed(() => (branding.value.name || 'S').charAt(0).toUpperCase());

useFlashToasts();

const links = [
    { label: 'Dashboard', route: 'dashboard', active: 'dashboard' },
    { label: 'Expenses', route: 'expenses.index', active: 'expenses.*' },
    { label: 'Budgets', route: 'budgets.index', active: 'budgets.*' },
    { label: 'Categories', route: 'categories.index', active: 'categories.*' },
];
</script>

<template>
    <div :class="APP_PAGE">
        <div class="mx-auto max-w-6xl px-3 pb-10 lg:px-4">
            <!-- The bar floats on the page rather than spanning it edge to edge,
                 echoing the panel geometry of the auth screens. -->
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
                        <span
                            v-else
                            class="grid size-7 place-items-center rounded-lg bg-neutral-900 text-[13px] font-extrabold text-white dark:bg-neutral-100 dark:text-neutral-900"
                        >
                            {{ brandInitial }}
                        </span>
                        <span class="hidden sm:inline">{{ branding.name }}</span>
                    </Link>

                    <div class="hidden items-center gap-1 md:flex">
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
                class="anim mb-3 rounded-[28px] border border-neutral-200/70 bg-white p-2 md:hidden dark:border-neutral-800 dark:bg-neutral-900"
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
