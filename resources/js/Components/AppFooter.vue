<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const page = usePage();

// A fixed set of footer links, always shown in this order. About and Policy
// point at the editable footer pages; an unpublished one lands on a short
// placeholder rather than 404-ing (see PageController::show).
const links = computed(() => [
    { label: trans('About'), href: route('pages.show', 'about') },
    { label: trans('Policy'), href: route('pages.show', 'privacy') },
    { label: trans('Help / FAQ'), href: route('help') },
]);

const appName = computed(() => page.props.branding?.name || 'MoneyLog');

// The copyright year is fine to read on the client — it is not persisted.
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-16 border-t border-border pt-6 pb-2">
        <div class="flex flex-col items-center gap-3 text-xs sm:flex-row sm:justify-between">
            <!-- Fixed links, always shown. -->
            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="transition-colors hover:text-foreground"
                    :class="MUTED"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <p :class="MUTED">
                {{ __('© :year :name. All rights reserved.', { year, name: appName }) }}
            </p>
        </div>
    </footer>
</template>
