<script setup>
import { computed, nextTick, ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { Check, ChevronDown, Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { VisuallyHidden } from 'reka-ui';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';
import { trans } from '@/lib/i18n';

/**
 * Pick where an income came from, or name a source that has not been used yet.
 *
 * The same control as CategoryPicker, for a simpler thing: a source is not a
 * row anywhere, only the string stored on each income, so the list offered is
 * what this person has typed before and "creating" one is just using a new
 * string. The form carries a single `source` key either way.
 */
const props = defineProps({
    form: { type: Object, required: true },
    // The sources already used, most frequent first.
    sources: { type: Array, required: true },
});

const open = ref(false);
const search = ref('');
const searchInput = ref(null);

// A popover anchored to a full-width trigger has nowhere to go on a phone, so
// below sm the same list is a bottom sheet — the swap CategoryPicker makes.
const isMobile = useMediaQuery('(max-width: 639px)');

const shell = computed(() => (isMobile.value ? Sheet : Popover));
const shellTrigger = computed(() => (isMobile.value ? SheetTrigger : PopoverTrigger));
const shellContent = computed(() => (isMobile.value ? SheetContent : PopoverContent));

const shellContentProps = computed(() =>
    isMobile.value
        ? {
              side: 'bottom',
              showCloseButton: false,
              class: cn(
                  'gap-0 rounded-t-2xl p-0',
                  'flex max-h-[80dvh] flex-col',
                  'pb-[env(safe-area-inset-bottom)]',
              ),
          }
        : { align: 'start', class: 'w-[--reka-popover-trigger-width] p-0' },
);

const matches = computed(() => {
    const q = search.value.trim().toLowerCase();

    return q ? props.sources.filter((s) => s.toLowerCase().includes(q)) : props.sources;
});

// Whether what is chosen is one of the known sources. Compared loosely, so
// typing "salary" against an existing "Salary" is a pick, not a new source.
const known = (name) => props.sources.some((s) => s.toLowerCase() === name.trim().toLowerCase());

const isNew = computed(() => Boolean(props.form.source) && !known(props.form.source));

// Only offer to add what does not already exist; a match is picked instead.
const creatable = computed(() => {
    const q = search.value.trim();

    return q && !known(q) ? q : null;
});

function choose(source) {
    props.form.source = source;
    search.value = '';
    open.value = false;
}

function create() {
    if (!creatable.value) {
        return;
    }

    props.form.source = creatable.value;
    open.value = false;
}

async function onOpen(value) {
    open.value = value;

    if (value) {
        search.value = '';
        await nextTick();
        searchInput.value?.$el?.focus();
    }
}
</script>

<template>
    <div>
        <component :is="shell" :open="open" @update:open="onOpen">
            <component :is="shellTrigger" as-child>
                <Button
                    id="source"
                    type="button"
                    variant="outline"
                    class="mt-1 h-10 w-full justify-between rounded-xl font-normal max-sm:h-11"
                    :aria-invalid="!!form.errors.source"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <template v-if="form.source">
                            <span class="truncate">{{ form.source }}</span>
                            <span v-if="isNew" class="shrink-0 text-xs text-neutral-400">{{ __('new') }}</span>
                        </template>
                        <span v-else class="text-neutral-400">{{ __('Choose') }}</span>
                    </span>
                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                </Button>
            </component>

            <component :is="shellContent" v-bind="shellContentProps">
                <VisuallyHidden v-if="isMobile">
                    <SheetTitle>{{ __('Source') }}</SheetTitle>
                    <SheetDescription>{{ __('Search the list, or type a new source.') }}</SheetDescription>
                </VisuallyHidden>

                <div class="shrink-0 border-b border-neutral-100 p-2 dark:border-neutral-800">
                    <Input
                        ref="searchInput"
                        v-model="search"
                        class="max-sm:h-12 max-sm:text-base sm:h-8"
                        autocomplete="off"
                        :placeholder="trans('Search or type a new one…')"
                        @keydown.enter.prevent="creatable ? create() : matches[0] && choose(matches[0])"
                    />
                </div>

                <div class="overflow-y-auto overscroll-contain p-1 max-sm:min-h-0 max-sm:flex-1 sm:max-h-56">
                    <button
                        v-for="source in matches"
                        :key="source"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg text-sm transition hover:bg-neutral-100 max-sm:min-h-11 max-sm:px-3 max-sm:py-2.5 sm:px-2 sm:py-1.5 dark:hover:bg-neutral-800"
                        @click="choose(source)"
                    >
                        <span class="truncate">{{ source }}</span>
                        <Check
                            v-if="form.source === source"
                            class="ms-auto size-4 shrink-0"
                        />
                    </button>

                    <p
                        v-if="!matches.length && !creatable"
                        class="px-2 py-6 text-center text-sm text-neutral-400"
                    >
                        {{ sources.length ? __('No sources found.') : __('Type a source to add it.') }}
                    </p>
                </div>

                <div v-if="creatable" class="shrink-0 border-t border-neutral-100 p-1 dark:border-neutral-800">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg text-sm font-medium transition hover:bg-neutral-100 max-sm:min-h-11 max-sm:px-3 max-sm:py-2.5 sm:px-2 sm:py-2 dark:hover:bg-neutral-800"
                        @click="create"
                    >
                        <Plus class="size-4 shrink-0" />
                        <span class="truncate">
                            {{ __('Add “:name”', { name: creatable }) }}
                        </span>
                    </button>
                </div>
            </component>
        </component>

        <!-- A new source is private to this person and offered again next time. -->
        <p v-if="isNew" class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            {{ __('Will be offered as a source next time.') }}
        </p>

        <p
            v-if="form.errors.source"
            class="mt-1 text-sm text-red-600 dark:text-red-400"
        >
            {{ form.errors.source }}
        </p>
    </div>
</template>
