<script setup>
/*
 * Month and year behind one trigger.
 *
 * They stay two filters, not one date: a year alone means that whole year, a
 * month alone means that month in every year, and together they pin one month.
 * A single month-year picker cannot say "every July", so the pair is kept and
 * only the two triggers it used to cost are merged into one.
 *
 * The lists are rendered here rather than by nesting two SearchableSelects:
 * below sm that component becomes a bottom sheet, and a sheet opened from
 * inside this panel would count as a click outside it — the panel would close
 * under the list the user just opened.
 */
import { computed, ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { CalendarDays, Check, ChevronDown } from 'lucide-vue-next';
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
    // Both [{ value, label }], each carrying its own '' = "every one" row.
    monthOptions: { type: Array, required: true },
    yearOptions: { type: Array, required: true },
    month: { type: String, default: '' },
    year: { type: String, default: '' },
    // Shown on the trigger when neither filter is set.
    label: { type: String, default: '' },
    /*
     * Popover renders no element of its own, so a class set on this component
     * would be dropped rather than reaching the trigger. Same reason
     * SearchableSelect takes its skin by prop.
     */
    triggerClass: { type: [String, Array, Object], default: '' },
    contentClass: { type: [String, Array, Object], default: 'w-72' },
    align: { type: String, default: 'start' },
});

const emit = defineEmits(['update:month', 'update:year']);

const open = ref(false);

// Same breakpoint and the same reason as SearchableSelect: a popover anchored
// to a pill has nowhere to go at 430px, so below sm this is a bottom sheet.
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
                  'max-h-[75dvh]',
                  'pb-[env(safe-area-inset-bottom)]',
              ),
          }
        : { align: props.align, class: cn('p-0', props.contentClass) },
);

// Only when a month is actually chosen. The '' row carries a label of its own
// ("All months"), so looking it up unconditionally puts that on the trigger and
// the pill claims a month filter that is not set.
const monthLabel = computed(() =>
    props.month
        ? (props.monthOptions.find((o) => o.value === props.month)?.label ?? '')
        : '',
);

/*
 * What the trigger says. Each half is named only when it is actually filtering,
 * so the pill reads as the sentence the filter makes: "July 2026", "July" for
 * every July, "2026" for the whole year.
 */
const triggerLabel = computed(() => {
    if (props.month && props.year) {
        return `${monthLabel.value} ${props.year}`;
    }

    return monthLabel.value || props.year || props.label;
});

const isFiltering = computed(() => Boolean(props.month || props.year));

/*
 * Arrow keys within a column. The rows are radios, so moving between them is
 * expected to work without Tab; reading them off the DOM keeps this from
 * needing a ref per row in two different lists.
 */
function move(event, delta) {
    const rows = [...event.currentTarget.querySelectorAll('[role="radio"]')];
    const from = rows.indexOf(document.activeElement);
    const next = rows[(from + delta + rows.length) % rows.length];

    next?.focus();
}
</script>

<template>
    <component :is="shell" v-model:open="open">
        <component :is="shellTrigger" as-child>
            <button
                type="button"
                :class="
                    cn(
                        'inline-flex items-center gap-1.5 transition disabled:opacity-50',
                        'outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                        triggerClass,
                    )
                "
                :aria-label="label"
                :aria-expanded="open"
                @keydown.down.prevent="open = true"
            >
                <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
                <span
                    class="truncate"
                    :class="isFiltering ? '' : 'text-muted-foreground'"
                >
                    {{ triggerLabel }}
                </span>
                <ChevronDown
                    class="ms-auto size-3.5 shrink-0 text-muted-foreground transition-transform duration-200"
                    :class="open ? 'rotate-180' : ''"
                />
            </button>
        </component>

        <component :is="shellContent" v-bind="shellContentProps">
            <!-- The sheet is a dialog and owes a title; the popover is labelled
                 by its trigger and needs no header. -->
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

            <!-- Side by side rather than stacked: they are two halves of one
                 date, and a phone still has room for two narrow columns. -->
            <div class="grid grid-cols-2 divide-x divide-border">
                <section
                    role="radiogroup"
                    :aria-label="__('Month')"
                    class="min-w-0"
                    @keydown.down.prevent="move($event, 1)"
                    @keydown.up.prevent="move($event, -1)"
                >
                    <p
                        class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground"
                    >
                        {{ __('Month') }}
                    </p>
                    <div class="max-h-64 overflow-y-auto p-1">
                        <button
                            v-for="option in monthOptions"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="option.value === month"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-start text-sm transition hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:bg-accent max-sm:py-2.5"
                            :class="
                                option.value === month
                                    ? 'font-semibold text-foreground'
                                    : 'text-muted-foreground'
                            "
                            @click="emit('update:month', option.value)"
                        >
                            <span class="truncate">{{ option.label }}</span>
                            <Check
                                v-if="option.value === month"
                                class="ms-auto size-3.5 shrink-0"
                            />
                        </button>
                    </div>
                </section>

                <section
                    role="radiogroup"
                    :aria-label="__('Year')"
                    class="min-w-0"
                    @keydown.down.prevent="move($event, 1)"
                    @keydown.up.prevent="move($event, -1)"
                >
                    <p
                        class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground"
                    >
                        {{ __('Year') }}
                    </p>
                    <div class="max-h-64 overflow-y-auto p-1">
                        <button
                            v-for="option in yearOptions"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="option.value === year"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-start text-sm transition hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:bg-accent max-sm:py-2.5"
                            :class="
                                option.value === year
                                    ? 'font-semibold text-foreground'
                                    : 'text-muted-foreground'
                            "
                            @click="emit('update:year', option.value)"
                        >
                            <span class="truncate">{{ option.label }}</span>
                            <Check
                                v-if="option.value === year"
                                class="ms-auto size-3.5 shrink-0"
                            />
                        </button>
                    </div>
                </section>
            </div>
        </component>
    </component>
</template>
