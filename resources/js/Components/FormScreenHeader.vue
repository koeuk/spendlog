<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { MUTED } from '@/lib/appStyles';

/**
 * The header of a full-screen create/edit view.
 *
 * A form long enough to need its own route needs a way back that is not the
 * browser chrome: on a phone installed to the home screen there is no chrome to
 * use. Back is a Link rather than history.back() so it has a real destination —
 * arriving on the form from a deep link or a refresh still leaves somewhere to
 * go, which history.back() cannot promise.
 *
 * Sticky, because these forms scroll: the title says which record you are in and
 * that is worth as much at the bottom of a long form as at the top.
 */
defineProps({
    backHref: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    backLabel: { type: String, default: 'Back' },
});
</script>

<template>
    <header
        class="sticky top-0 z-10 -mx-4 mb-4 flex items-center gap-1 border-b border-border bg-background/80 px-2 py-2 backdrop-blur-xl sm:mx-0 sm:rounded-2xl sm:border sm:px-3"
    >
        <Link
            :href="backHref"
            :aria-label="backLabel"
            class="grid size-11 shrink-0 place-items-center rounded-full text-foreground transition hover:bg-muted"
        >
            <ArrowLeft class="size-5" aria-hidden="true" />
        </Link>

        <!-- min-w-0 so a long item name truncates instead of pushing the row
             wider than the viewport. -->
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-base font-semibold leading-tight">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="truncate text-xs leading-tight" :class="MUTED">
                {{ subtitle }}
            </p>
        </div>

        <!-- Trailing actions (Save, overflow) belong to the page, not the
             header: only the page knows whether the form is submittable. -->
        <slot name="actions" />
    </header>
</template>
