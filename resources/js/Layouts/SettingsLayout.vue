<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Palette, ShieldCheck, UserRound } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { trans } from '@/lib/i18n';

defineProps({
    heading: { type: String, required: true },
    description: { type: String, default: '' },
});

const page = usePage();

const isAdmin = computed(() => Boolean(page.props.auth?.is_admin));

// Branding is admin-only server-side too — this just keeps it out of the way
// for everyone else.
const items = computed(() =>
    [
        { key: 'profile', label: trans('Profile'), href: route('profile.edit'), icon: UserRound, pattern: 'profile.*' },
        { key: 'password', label: trans('Password'), href: route('password.edit'), icon: ShieldCheck, pattern: 'password.edit' },
        isAdmin.value
            ? { key: 'branding', label: trans('Appearance'), href: route('branding.edit'), icon: Palette, pattern: 'branding.*' }
            : null,
    ].filter(Boolean),
);

function isActive(pattern) {
    return route().current(pattern);
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                {{ __('Settings') }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-8 lg:flex-row">
                    <!-- Sidebar -->
                    <aside class="lg:w-56 lg:shrink-0">
                        <nav class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                            <Link
                                v-for="item in items"
                                :key="item.key"
                                :href="item.href"
                                :aria-current="isActive(item.pattern) ? 'page' : undefined"
                                class="flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="
                                    isActive(item.pattern)
                                        ? 'bg-gray-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'
                                "
                            >
                                <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                                {{ item.label }}
                            </Link>
                        </nav>
                    </aside>

                    <!-- Panel -->
                    <div class="min-w-0 flex-1">
                        <div class="rounded-lg bg-white p-6 shadow-sm sm:p-8 dark:bg-neutral-900">
                            <header>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100">
                                    {{ heading }}
                                </h3>
                                <p
                                    v-if="description"
                                    class="mt-1 text-sm text-gray-500 dark:text-neutral-400"
                                >
                                    {{ description }}
                                </p>
                            </header>

                            <div class="mt-6">
                                <slot />
                            </div>
                        </div>

                        <slot name="after" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
