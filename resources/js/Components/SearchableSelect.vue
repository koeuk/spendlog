<script>
/*
 * Per-instance id seed, for the aria-activedescendant wiring below. A plain
 * counter rather than Vue's useId: package.json allows ^3.4.0 and useId landed
 * in 3.5, so a clean install could resolve below it. Module scope, so it counts
 * across instances rather than restarting at 1 for each. There is no SSR here
 * (see the note in useTheme.js), so client-only ids cannot mismatch a server
 * render.
 */
let nextId = 0;
</script>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';

const props = defineProps({
    // [{ value, label }]
    options: { type: Array, required: true },
    modelValue: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    // Shown on the trigger when nothing in `options` matches modelValue.
    label: { type: String, default: '' },
    searchPlaceholder: { type: String, default: '' },
    emptyText: { type: String, default: '' },
    // Search the value as well as the label. Useful when the value is itself
    // meaningful to a human ("2026-07"), noise when it is a uuid.
    matchValue: { type: Boolean, default: false },
    /*
     * Popover renders no element of its own, so a class set on this component
     * would be dropped rather than inherited by the trigger. The trigger and
     * the popup therefore take their skins by prop — each caller sits in a
     * different toolbar and has to match its neighbours.
     */
    triggerClass: { type: [String, Array, Object], default: '' },
    contentClass: { type: [String, Array, Object], default: 'w-56' },
    align: { type: String, default: 'end' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const searchRef = ref(null);

/*
 * A popover anchored to a pill in a toolbar has nowhere to go on a phone: at
 * 430px the list either runs off the top of the viewport or covers the very
 * control it belongs to. Below sm the same list is therefore presented as a
 * bottom sheet — anchored to the thumb, sized to its content, dismissed by the
 * overlay.
 *
 * The shells are swapped rather than the panel duplicated, so the list, the
 * search box and all the keyboard wiring below have exactly one definition.
 * Both shells are reka Dialog/Popover roots taking `open`, and both triggers
 * accept as-child, so the same markup slots into either.
 */
const isMobile = useMediaQuery('(max-width: 639px)');

const shell = computed(() => (isMobile.value ? Sheet : Popover));
const shellTrigger = computed(() => (isMobile.value ? SheetTrigger : PopoverTrigger));
const shellContent = computed(() => (isMobile.value ? SheetContent : PopoverContent));

const shellContentProps = computed(() =>
    isMobile.value
        ? {
              side: 'bottom',
              // Own header below, so the built-in floating X is off — it would
              // otherwise land on top of the title.
              showCloseButton: false,
              class: cn(
                  'gap-0 rounded-t-2xl p-0',
                  // dvh, not vh: with the browser chrome shown, 75vh on iOS
                  // reaches past the bottom of what is actually visible.
                  'max-h-[75dvh]',
                  // Clear of the home indicator / gesture bar.
                  'pb-[env(safe-area-inset-bottom)]',
              ),
          }
        : { align: props.align, class: cn('p-0', props.contentClass) },
);

const selected = computed(
    () => props.options.find((option) => option.value === props.modelValue) ?? null,
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.options;
    }

    return props.options.filter(
        (option) =>
            option.label.toLowerCase().includes(q) ||
            (props.matchValue && String(option.value).toLowerCase().includes(q)),
    );
});

/*
 * Keyboard navigation.
 *
 * The roles below (listbox/option) promise a list that can be walked with the
 * arrow keys, so the handlers have to exist or the ARIA is a lie to a screen
 * reader — it announces "3 of 12" for a list that cannot be moved through.
 *
 * Focus stays in the search box the whole time rather than moving onto the
 * options: typing has to keep working while navigating, which is the combobox
 * pattern, not the plain-listbox one. The active option is therefore pointed at
 * with aria-activedescendant instead of being focused.
 */
const uid = `searchable-select-${++nextId}`;
const listboxId = `${uid}-listbox`;
const activeIndex = ref(0);
const optionRefs = ref([]);

const activeOption = computed(() => filtered.value[activeIndex.value] ?? null);

const optionId = (index) => `${uid}-option-${index}`;

/**
 * Move the highlight, wrapping at both ends.
 *
 * Wrapping rather than stopping: the list is short and reachable either way, so
 * Up from the first entry landing on the last saves walking the whole thing.
 */
function move(delta) {
    const count = filtered.value.length;

    if (count === 0) {
        return;
    }

    setActive((activeIndex.value + delta + count) % count);
}

function setActive(index) {
    activeIndex.value = index;

    // The list scrolls at max-h-64, so the highlight can otherwise walk out of
    // sight. 'nearest' keeps it from re-centring the list on every keypress.
    nextTick(() => {
        optionRefs.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' });
    });
}

// Typing narrows the list out from under the highlight, so it goes back to the
// top — otherwise index 5 of the old list silently becomes a different option.
// The refs are dropped with it: they are keyed by index, and a shorter list
// would leave the tail pointing at rows that are no longer rendered.
watch(filtered, () => {
    optionRefs.value = [];
    setActive(0);
});

// Focus the box on open, and clear the last search so the full list is back.
watch(open, async (isOpen) => {
    if (!isOpen) {
        query.value = '';
        return;
    }

    // Open on what is currently selected rather than on the first row, so the
    // arrow keys start from where the user already is.
    const current = props.options.findIndex(
        (option) => option.value === props.modelValue,
    );

    setActive(current === -1 ? 0 : current);

    await nextTick();

    // Not on the sheet: focusing the box raises the on-screen keyboard, which
    // eats the lower half of the screen and pushes the list the user came here
    // to tap out of reach. Typing is still one tap away if they want it.
    if (!isMobile.value) {
        searchRef.value?.focus();
    }
});

function choose(option) {
    open.value = false;
    if (option.value !== props.modelValue) {
        emit('update:modelValue', option.value);
    }
}
</script>

<template>
    <component :is="shell" v-model:open="open">
        <component :is="shellTrigger" as-child>
            <button
                type="button"
                :class="
                    cn(
                        'inline-flex items-center justify-between gap-1.5 transition disabled:opacity-50',
                        /* The keyboard can reach this, so it has to show where
                           it is. focus-visible, not focus: a pointer user
                           clicking the pill should not be left with a ring on
                           it. The offset keeps the ring clear of the pill's own
                           border instead of sitting on top of it. */
                        'outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                        triggerClass,
                    )
                "
                :disabled="disabled"
                :aria-label="label"
                :aria-expanded="open"
                :aria-controls="open ? listboxId : undefined"
                aria-haspopup="listbox"
                @keydown.down.prevent="open = true"
            >
                <span class="truncate">{{ selected?.label ?? label }}</span>
                <!-- Points up while open, so the pill itself says whether the
                     list is showing. Tokened, not neutral-400: at 3.5 units on
                     a brand-tinted pill a fixed grey reads as a smudge. -->
                <ChevronDown
                    class="size-3.5 shrink-0 text-muted-foreground transition-transform duration-200"
                    :class="open ? 'rotate-180' : ''"
                />
            </button>
        </component>

        <component :is="shellContent" v-bind="shellContentProps">
            <!-- The sheet is a dialog, so it owes a title: reka warns without
                 one and a screen reader announces an unnamed panel. The popover
                 is labelled by its trigger instead and needs no header. -->
            <div
                v-if="isMobile"
                class="flex items-center justify-between gap-2 border-b border-border px-4 py-3"
            >
                <SheetTitle class="text-sm font-semibold text-foreground">
                    {{ label }}
                </SheetTitle>
                <button
                    type="button"
                    class="-me-1 rounded-lg px-2 py-1 text-xs font-semibold text-muted-foreground transition hover:text-foreground"
                    @click="open = false"
                >
                    {{ __('Done') }}
                </button>
            </div>

            <!-- Tokens throughout, not neutral-*: appStyles.js spells out why a
                 literal colour here paints over the admin's chosen palette and
                 makes the setting silently do nothing. -->
            <div class="flex shrink-0 items-center gap-2 border-b border-border px-3">
                <Search class="size-3.5 shrink-0 text-muted-foreground" />
                <!-- A plain input, not the shadcn one: this needs no ring or
                     border of its own inside an already-bordered popover.
                     text-base on the sheet, not text-xs: iOS Safari zooms the
                     whole page in when a font under 16px takes focus. -->
                <input
                    ref="searchRef"
                    v-model="query"
                    type="text"
                    class="w-full border-0 bg-transparent p-0 font-medium text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0 max-sm:h-12 max-sm:text-base sm:h-9 sm:text-xs"
                    :placeholder="searchPlaceholder || __('Search…')"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-controls="listboxId"
                    :aria-expanded="open"
                    :aria-activedescendant="activeOption ? optionId(activeIndex) : undefined"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.home.prevent="setActive(0)"
                    @keydown.end.prevent="setActive(filtered.length - 1)"
                    @keydown.enter.prevent="activeOption && choose(activeOption)"
                    @keydown.esc="open = false"
                />
            </div>

            <!-- min-h-0 so this, and not the sheet, is what scrolls: a flex
                 child defaults to its content's height and would otherwise push
                 the panel past the max-h instead of overflowing inside it. -->
            <ul
                :id="listboxId"
                class="overflow-y-auto overscroll-contain p-1 max-sm:min-h-0 max-sm:flex-1 sm:max-h-64"
                role="listbox"
            >
                <!-- role="none" on the wrapper: a listbox's children have to be
                     options, and a bare <li> in between breaks that tree. -->
                <li
                    v-for="(option, index) in filtered"
                    :key="option.value"
                    role="none"
                >
                    <button
                        :id="optionId(index)"
                        :ref="(el) => (optionRefs[index] = el)"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg text-start font-medium transition max-sm:min-h-11 max-sm:px-3 max-sm:py-2.5 max-sm:text-sm sm:px-2.5 sm:py-1.5 sm:text-xs"
                        :class="[
                            /* Two different states, so they are shown two
                               different ways: weight marks what is *selected*,
                               fill marks what the keyboard is *on*. Using fill
                               for both would make the selected row and the
                               cursor indistinguishable the moment they part. */
                            option.value === modelValue
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                            /* Highlight is driven by activeIndex rather than by
                               :hover, so the mouse and the arrow keys cannot
                               light up two different rows at once. */
                            index === activeIndex
                                ? 'bg-accent text-accent-foreground'
                                : '',
                        ]"
                        role="option"
                        :aria-selected="option.value === modelValue"
                        tabindex="-1"
                        @click="choose(option)"
                        @mouseenter="activeIndex = index"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <Check
                            v-if="option.value === modelValue"
                            class="size-3.5 shrink-0 text-primary"
                        />
                    </button>
                </li>

                <li
                    v-if="!filtered.length"
                    class="px-2.5 py-6 text-center text-xs text-muted-foreground"
                >
                    {{ emptyText || __('Nothing found.') }}
                </li>
            </ul>
        </component>
    </component>
</template>
