<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { MUTED } from '@/lib/appStyles';

const props = defineProps({
    // The meta half of a paginated payload — see PaginatesLists::paginationMeta.
    meta: { type: Object, required: true },
    // Reload only what the list needs, so paging a table does not re-run the
    // rest of the page's queries.
    only: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['navigating']);

const options = { preserveState: true, preserveScroll: true, replace: true };

const range = computed(() => ({
    // Null when the page is empty; "0–0 of 0" reads better than "null–null".
    from: props.meta.from ?? 0,
    to: props.meta.to ?? 0,
    total: props.meta.total ?? 0,
}));

const hasPages = computed(() => (props.meta.last_page ?? 1) > 1);

function visit(url, extra = {}) {
    if (!url) {
        return;
    }

    emit('navigating', true);

    router.get(url, extra, {
        ...options,
        only: props.only.length ? props.only : undefined,
        onFinish: () => emit('navigating', false),
    });
}

/**
 * Changing the page size returns to page 1 — page 7 of 20-per-page is not page 7
 * of 200-per-page, and landing past the end would show an empty list.
 *
 * The current URL carries the page's other state (period, filters), so it is
 * reused rather than rebuilt from props this component cannot know about.
 */
function setPerPage(size) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', size);
    url.searchParams.delete('page');

    visit(url.toString());
}
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 px-4 py-3 sm:px-7 dark:border-neutral-800"
    >
        <div class="flex items-center gap-2">
            <select
                :value="meta.per_page"
                class="h-8 rounded-full border-neutral-200 bg-white/70 py-0 pe-7 ps-2.5 text-xs font-semibold text-neutral-700 focus:border-neutral-400 focus:ring-0 dark:border-neutral-700 dark:bg-neutral-800/70 dark:text-neutral-200"
                :aria-label="__('Per page')"
                :disabled="disabled"
                @change="setPerPage($event.target.value)"
            >
                <option v-for="size in meta.per_page_options" :key="size" :value="size">
                    {{ size }}
                </option>
            </select>
            <span :class="[MUTED, 'text-xs font-medium']">{{ __('per page') }}</span>
        </div>

        <div class="flex items-center gap-3">
            <span :class="[MUTED, 'text-xs font-medium tabular-nums']">
                {{ __('Showing :from–:to of :total', range) }}
            </span>

            <div v-if="hasPages" class="flex items-center gap-1">
                <button
                    type="button"
                    class="grid size-7 place-items-center rounded-full border border-neutral-200 text-neutral-600 transition hover:bg-neutral-50 disabled:opacity-40 disabled:hover:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    :disabled="!meta.prev_page_url || disabled"
                    :aria-label="__('Newer')"
                    @click="visit(meta.prev_page_url)"
                >
                    <ChevronLeft class="size-4" />
                </button>

                <span :class="[MUTED, 'min-w-16 text-center text-xs font-medium tabular-nums']">
                    {{ meta.current_page }} / {{ meta.last_page }}
                </span>

                <button
                    type="button"
                    class="grid size-7 place-items-center rounded-full border border-neutral-200 text-neutral-600 transition hover:bg-neutral-50 disabled:opacity-40 disabled:hover:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    :disabled="!meta.next_page_url || disabled"
                    :aria-label="__('Older')"
                    @click="visit(meta.next_page_url)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
