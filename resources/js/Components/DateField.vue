<script setup>
import { computed, ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import {
    CalendarDate,
    DateFormatter,
    getLocalTimeZone,
    today,
} from '@internationalized/date';
import { Button } from '@/Components/ui/button';
import { Calendar } from '@/Components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { Sheet, SheetContent, SheetTrigger } from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';
import { trans } from '@/lib/i18n';

/**
 * A date field: one button that opens the calendar — a popover on a desk, a
 * bottom sheet on a phone, where a seven-column grid anchored to a full-width
 * trigger has nowhere to sit at 430px.
 *
 * Lifted from ExpenseForm so the income, goal and ledger forms share one
 * picker rather than three copies of it. The value is the plain 'YYYY-MM-DD'
 * string the server wants; '' means unset.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    // The bounds the server enforces: no future date for money that has
    // already moved, no past date for a deadline being set today.
    noFuture: { type: Boolean, default: false },
    noPast: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const formatter = new DateFormatter('en-US', { dateStyle: 'medium' });

const date = computed({
    get() {
        if (!props.modelValue) {
            return undefined;
        }

        const [year, month, day] = props.modelValue.split('-').map(Number);

        return new CalendarDate(year, month, day);
    },
    set(value) {
        emit('update:modelValue', value ? value.toString() : '');
    },
});

const label = computed(() =>
    date.value
        ? formatter.format(date.value.toDate(getLocalTimeZone()))
        : props.placeholder || trans('Pick a date'),
);

const now = today(getLocalTimeZone());
const maxDate = props.noFuture ? now : undefined;
const minDate = props.noPast ? now : undefined;

const isMobile = useMediaQuery('(max-width: 639px)');

const open = ref(false);

const shell = computed(() => (isMobile.value ? Sheet : Popover));
const trigger = computed(() => (isMobile.value ? SheetTrigger : PopoverTrigger));
const content = computed(() => (isMobile.value ? SheetContent : PopoverContent));

const contentProps = computed(() =>
    isMobile.value
        ? {
              side: 'bottom',
              showCloseButton: false,
              class: cn(
                  'gap-0 rounded-t-2xl',
                  // The calendar sizes itself, so the sheet only has to centre
                  // it and stay clear of the home indicator.
                  'flex items-center justify-center p-4',
                  'pb-[max(1rem,env(safe-area-inset-bottom))]',
              ),
          }
        : { class: 'w-auto p-0' },
);

/**
 * Close on pick. A popover can be left open — the date is visible behind it.
 * A sheet covers the form, so staying open would hide the field it just filled.
 */
function onPicked() {
    open.value = false;
}
</script>

<template>
    <component :is="shell" v-model:open="open">
        <component :is="trigger" as-child>
            <!-- Sized and rounded as a field, not a button: it sits in the same
                 column as the inputs around it. -->
            <Button
                type="button"
                variant="outline"
                class="mt-1 h-10 w-full justify-start rounded-xl font-normal max-sm:h-11"
            >
                {{ label }}
            </Button>
        </component>
        <component :is="content" v-bind="contentProps">
            <Calendar
                v-model="date"
                :max-value="maxDate"
                :min-value="minDate"
                initial-focus
                @update:model-value="onPicked"
            />
        </component>
    </component>
</template>
