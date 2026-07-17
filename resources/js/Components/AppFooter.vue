<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { MUTED } from '@/lib/appStyles';

const page = usePage();

// Published footer pages, shared from HandleInertiaRequests — { slug, title }.
const pages = computed(() => page.props.footer_pages ?? []);

const appName = computed(() => page.props.branding?.name || 'MoneyLog');

// A fixed timestamp is passed in from the server-rendered year would be ideal,
// but the copyright year is fine to read on the client — it is not persisted.
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-16 border-t border-border pt-6 pb-2">
        <div class="flex flex-col items-center gap-3 text-xs sm:flex-row sm:justify-between">
            <!-- Links: the editable pages, then Help. Only published pages appear,
                 so an unwritten page leaves no dead link. -->
            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                <Link
                    v-for="p in pages"
                    :key="p.slug"
                    :href="route('pages.show', p.slug)"
                    class="transition-colors hover:text-foreground"
                    :class="MUTED"
                >
                    {{ p.title }}
                </Link>
                <Link
                    :href="route('help')"
                    class="transition-colors hover:text-foreground"
                    :class="MUTED"
                >
                    {{ __('Help') }}
                </Link>
            </nav>

            <p :class="MUTED">
                {{ __('© :year :name. All rights reserved.', { year, name: appName }) }}
            </p>
        </div>
    </footer>
</template>
