<script setup>
import { computed } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { cn } from '@/lib/utils';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import { Sheet, SheetContent } from '@/Components/ui/sheet';

/**
 * A short form that is a centred dialog on a desk and a bottom sheet on a phone.
 *
 * A dialog on a phone is centred in a viewport a thumb cannot reach the middle
 * of, and it animates from nowhere in particular. A sheet rises from the edge
 * the hand is already at. Below sm the shell is therefore swapped, not
 * restyled — same reasoning, and the same swap, as SearchableSelect.
 *
 * The shells are exchanged rather than the body duplicated, so the form inside
 * has exactly one definition. Both are reka Dialog roots taking `open`, so the
 * slot markup drops into either unchanged.
 *
 * For forms long enough to want their own screen — several fields, or a picker
 * that opens a second layer — reach for a route instead. A sheet is still a
 * modal, and nesting a picker inside one has the same nowhere-to-go problem a
 * dialog does. See Pages/Expenses/Form.vue.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    // Applied to the desktop dialog only; the sheet is always full-width.
    contentClass: { type: String, default: 'sm:max-w-md' },
});

defineEmits(['update:open']);

const isMobile = useMediaQuery('(max-width: 639px)');

const shell = computed(() => (isMobile.value ? Sheet : Dialog));
const shellContent = computed(() => (isMobile.value ? SheetContent : DialogContent));

const shellContentProps = computed(() =>
    isMobile.value
        ? {
              side: 'bottom',
              class: cn(
                  'rounded-t-2xl',
                  // dvh, not vh: with the browser chrome shown, 90vh reaches
                  // past the bottom of what is actually visible on iOS.
                  'max-h-[90dvh] overflow-y-auto',
                  // Clear of the home indicator / gesture bar.
                  'pb-[max(1rem,env(safe-area-inset-bottom))]',
              ),
          }
        : { class: props.contentClass },
);
</script>

<template>
    <component
        :is="shell"
        :open="open"
        @update:open="$emit('update:open', $event)"
    >
        <component :is="shellContent" v-bind="shellContentProps">
            <slot />
        </component>
    </component>
</template>
