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
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { trans } from '@/lib/i18n';

/**
 * Pick a category, or name one that does not exist yet.
 *
 * A plain Select cannot do this: it only offers what it was given, so an expense
 * in an unlisted category means abandoning the dialog, creating the category, and
 * starting again — which is how everything ends up under "Other". Here the search
 * box doubles as the create field.
 *
 * The form carries both keys and the server takes exactly one:
 *   category_uuid — an existing row
 *   new_category  — a name to create
 */
const props = defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    // Mirrors CategoryPolicy::create. False hides the create affordance rather
    // than letting someone hit a 403 after typing.
    canCreate: { type: Boolean, default: true },
});

const open = ref(false);
const search = ref('');
const searchInput = ref(null);

/*
 * A popover anchored to a full-width trigger has nowhere to go on a phone: it
 * opened over the field it belongs to and ran past the action bar below. Below
 * sm the same list is a bottom sheet instead — anchored to the thumb, sized to
 * its content, dismissed by the overlay.
 *
 * The shells are swapped rather than the panel duplicated, so the list, the
 * search box and the create affordance keep one definition. Both are reka
 * Dialog/Popover roots taking `open`, and both triggers accept as-child, so the
 * same markup slots into either. Same approach as SearchableSelect.
 */
const isMobile = useMediaQuery('(max-width: 639px)');

const shell = computed(() => (isMobile.value ? Sheet : Popover));
const shellTrigger = computed(() => (isMobile.value ? SheetTrigger : PopoverTrigger));
const shellContent = computed(() => (isMobile.value ? SheetContent : PopoverContent));

const shellContentProps = computed(() =>
    isMobile.value
        ? {
              side: 'bottom',
              // The panel has its own search row as a header; the built-in
              // floating X would land on top of it.
              showCloseButton: false,
              class: cn(
                  'gap-0 rounded-t-2xl p-0',
                  // dvh, not vh: with the browser chrome shown, 80vh reaches
                  // past the bottom of what is actually visible on iOS.
                  'flex max-h-[80dvh] flex-col',
                  'pb-[env(safe-area-inset-bottom)]',
              ),
          }
        : { align: 'start', class: 'w-[--reka-popover-trigger-width] p-0' },
);

const selected = computed(() =>
    props.categories.find((c) => c.uuid === props.form.category_uuid) ?? null,
);

const matches = computed(() => {
    const q = search.value.trim().toLowerCase();

    return q
        ? props.categories.filter((c) => c.name.toLowerCase().includes(q))
        : props.categories;
});

// Only offer to create what does not already exist — case-insensitively, matching
// the server's check, so the button never promises something validation rejects.
const creatable = computed(() => {
    const q = search.value.trim();

    if (!q || !props.canCreate) {
        return null;
    }

    const taken = props.categories.some(
        (c) => c.name.toLowerCase() === q.toLowerCase(),
    );

    return taken ? null : q;
});

function choose(category) {
    props.form.category_uuid = category.uuid;
    props.form.new_category = '';
    search.value = '';
    open.value = false;
}

function create() {
    if (!creatable.value) {
        return;
    }

    // No uuid exists yet — the server creates the row and links it.
    props.form.new_category = creatable.value;
    props.form.category_uuid = '';
    open.value = false;
}

function clearPending() {
    props.form.new_category = '';
}

async function onOpen(value) {
    open.value = value;

    if (value) {
        search.value = '';
        await nextTick();
        searchInput.value?.$el?.focus();
    }
}

const label = computed(() => {
    if (props.form.new_category) {
        return props.form.new_category;
    }

    return selected.value?.name ?? '';
});
</script>

<template>
    <div>
        <component :is="shell" :open="open" @update:open="onOpen">
            <component :is="shellTrigger" as-child>
                <Button
                    id="category"
                    type="button"
                    variant="outline"
                    class="mt-1 h-10 w-full justify-between rounded-xl font-normal max-sm:h-11"
                    :aria-invalid="!!(form.errors.category_uuid || form.errors.new_category)"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <!-- A pending new category has no colour yet: the server
                             defaults it to slate, so show that rather than nothing. -->
                        <template v-if="form.new_category">
                            <span class="size-2 shrink-0 rounded-full bg-slate-400" />
                            <span class="truncate">{{ label }}</span>
                            <span class="shrink-0 text-xs text-neutral-400">{{ __('new') }}</span>
                        </template>
                        <template v-else-if="selected">
                            <component
                                :is="categoryIcon(selected.icon)"
                                v-if="categoryIcon(selected.icon)"
                                class="size-4 shrink-0"
                            />
                            <span
                                v-else
                                class="size-2 shrink-0 rounded-full"
                                :class="categoryColor(selected.color).dot"
                            />
                            <span class="truncate">{{ label }}</span>
                        </template>
                        <span v-else class="text-neutral-400">{{ __('Choose') }}</span>
                    </span>
                    <ChevronDown class="size-4 shrink-0 opacity-50" />
                </Button>
            </component>

            <component :is="shellContent" v-bind="shellContentProps">
                <!-- The sheet is a dialog, so it owes a name and a description:
                     reka warns without them and a screen reader announces an
                     unnamed panel. Hidden rather than shown — the search row is
                     this panel's header. The popover is labelled by its trigger
                     and needs neither. -->
                <VisuallyHidden v-if="isMobile">
                    <SheetTitle>{{ __('Category') }}</SheetTitle>
                    <SheetDescription>
                        {{ canCreate ? __('Search the list, or type a name to create a category.') : __('Search the list to pick a category.') }}
                    </SheetDescription>
                </VisuallyHidden>

                <div class="shrink-0 border-b border-neutral-100 p-2 dark:border-neutral-800">
                    <Input
                        ref="searchInput"
                        v-model="search"
                        class="max-sm:h-12 max-sm:text-base sm:h-8"
                        autocomplete="off"
                        :placeholder="canCreate ? trans('Search or type a new one…') : trans('Search…')"
                        @keydown.enter.prevent="creatable ? create() : matches[0] && choose(matches[0])"
                    />
                </div>

                <div class="overflow-y-auto overscroll-contain p-1 max-sm:min-h-0 max-sm:flex-1 sm:max-h-56">
                    <button
                        v-for="category in matches"
                        :key="category.uuid"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg text-sm transition hover:bg-neutral-100 max-sm:min-h-11 max-sm:px-3 max-sm:py-2.5 sm:px-2 sm:py-1.5 dark:hover:bg-neutral-800"
                        @click="choose(category)"
                    >
                        <component
                            :is="categoryIcon(category.icon)"
                            v-if="categoryIcon(category.icon)"
                            class="size-4 shrink-0"
                        />
                        <span
                            v-else
                            class="size-2 shrink-0 rounded-full"
                            :class="categoryColor(category.color).dot"
                        />
                        <span class="truncate">{{ category.name }}</span>
                        <Check
                            v-if="selected?.uuid === category.uuid"
                            class="ms-auto size-4 shrink-0"
                        />
                    </button>

                    <p
                        v-if="!matches.length && !creatable"
                        class="px-2 py-6 text-center text-sm text-neutral-400"
                    >
                        {{ __('No categories found.') }}
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
                            {{ __('Create “:name”', { name: creatable }) }}
                        </span>
                    </button>
                </div>
            </component>
        </component>

        <!-- Says out loud that submitting will add to the shared list, rather
             than surprising everyone with a new category later. -->
        <p v-if="form.new_category" class="mt-1 flex items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400">
            {{ __('Will be added to categories for everyone.') }}
            <button
                type="button"
                class="underline underline-offset-2 hover:text-neutral-900 dark:hover:text-neutral-100"
                @click="clearPending"
            >
                {{ __('Undo') }}
            </button>
        </p>

        <p
            v-if="form.errors.category_uuid || form.errors.new_category"
            class="mt-1 text-sm text-red-600 dark:text-red-400"
        >
            {{ form.errors.category_uuid || form.errors.new_category }}
        </p>
    </div>
</template>
