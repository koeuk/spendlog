<script setup>
import { XIcon } from "@lucide/vue";
import { reactiveOmit } from "@vueuse/core";
import {
  DialogClose,
  DialogContent,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui";
import { ref } from "vue";
import { cn } from "@/lib/utils";
import { Button } from '@/Components/ui/button';
import DialogOverlay from "./DialogOverlay.vue";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  forceMount: { type: Boolean, required: false },
  disableOutsidePointerEvents: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  showCloseButton: { type: Boolean, required: false, default: true },
});
const emits = defineEmits([
  "escapeKeyDown",
  "pointerDownOutside",
  "focusOutside",
  "interactOutside",
  "openAutoFocus",
  "closeAutoFocus",
]);

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);

// Clicking outside no longer closes the dialog — instead give a brief
// zoom/bounce "pulse" so the click feels acknowledged and points the user
// back to the action buttons.
const pulsing = ref(false);
let pulseTimer;

const onInteractOutside = (e) => {
  e.preventDefault();
  // Restart the animation even on rapid repeated clicks.
  pulsing.value = false;
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      pulsing.value = true;
    });
  });
  clearTimeout(pulseTimer);
  pulseTimer = setTimeout(() => {
    pulsing.value = false;
  }, 300);
};
</script>

<template>
  <DialogPortal>
    <DialogOverlay />
    <DialogContent
      data-slot="dialog-content"
      v-bind="{ ...$attrs, ...forwarded }"
      @interact-outside="onInteractOutside"
      :class="
        cn(
          // max-h + overflow-y-auto are load-bearing, not cosmetic: the dialog is
          // centred with `fixed` + -translate-y-1/2, so content taller than the
          // viewport grows past *both* edges at once. Being fixed, neither the page
          // nor the dialog scrolls — the header and the footer buttons become
          // permanently unreachable rather than merely below the fold. svh, not vh,
          // so a mobile browser's collapsing address bar cannot reintroduce it.
          'bg-popover text-popover-foreground data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 ease-[cubic-bezier(0.16,1,0.3,1)] ring-foreground/10 grid max-h-[calc(100svh-2rem)] max-w-[calc(100%-2rem)] gap-6 overflow-y-auto overscroll-contain rounded-xl p-6 text-sm ring-1 duration-300 sm:max-w-md fixed top-1/2 left-1/2 z-50 w-full -translate-x-1/2 -translate-y-1/2 outline-none',
          pulsing && 'dialog-pulse',
          props.class,
        )
      "
    >
      <slot />

      <DialogClose v-if="showCloseButton" data-slot="dialog-close" as-child>
        <Button variant="ghost" class="absolute top-4 right-4" size="icon-sm">
          <XIcon />
          <span class="sr-only">Close</span>
        </Button>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>

<style>
/* Unscoped: DialogContent is teleported to a portal, so a scoped attribute
   selector wouldn't reach it. Keyed to the .dialog-pulse class we toggle. */
/* Animate the standalone `scale` property (not `transform`) so it composes
   with Tailwind's centering translate — the dialog zooms in place instead of
   jumping out of position. */
@keyframes dialog-pulse {
  0% {
    scale: 1;
  }
  45% {
    scale: 1.02;
  }
  100% {
    scale: 1;
  }
}

.dialog-pulse {
  animation: dialog-pulse 300ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
</style>
