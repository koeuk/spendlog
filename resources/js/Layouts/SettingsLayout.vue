<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Palette, ShieldCheck, SwatchBook, UserRound, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD, EYEBROW, MUTED, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

defineProps({
    heading: { type: String, required: true },
    description: { type: String, default: '' },
});

const page = usePage();

const isAdmin = computed(() => Boolean(page.props.auth?.is_admin));

// The admin pages are admin-only server-side too — this just keeps them out of
// the way for everyone else.
const items = computed(() =>
    [
        { key: 'profile', label: trans('Profile'), href: route('profile.edit'), icon: UserRound, pattern: 'profile.*' },
        { key: 'password', label: trans('Password'), href: route('password.edit'), icon: ShieldCheck, pattern: 'password.edit' },
        isAdmin.value
            ? { key: 'users', label: trans('Users'), href: route('users.index'), icon: Users, pattern: 'users.*' }
            : null,
        isAdmin.value
            ? { key: 'branding', label: trans('Appearance'), href: route('branding.edit'), icon: Palette, pattern: 'branding.*' }
            : null,
        isAdmin.value
            ? { key: 'colors', label: trans('Colours'), href: route('colors.edit'), icon: SwatchBook, pattern: 'colors.*' }
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
                <nav class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
                    <Link
                        v-for="item in items"
                        :key="item.key"
                        :href="item.href"
                        :aria-current="isActive(item.pattern) ? 'page' : undefined"
                        class="flex shrink-0 items-center gap-2.5 px-3.5 py-2.5 text-sm font-semibold transition"
                        :class="isActive(item.pattern) ? SEGMENT_ON : SEGMENT_OFF"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 space-y-3">
                <div :class="[CARD, 'anim p-6 sm:p-8']" style="--d: 60ms">
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
