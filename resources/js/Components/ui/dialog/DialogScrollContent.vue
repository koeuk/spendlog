<script setup>
import { XIcon } from "@lucide/vue";
import { reactiveOmit } from "@vueuse/core";
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui";
import { ref } from "vue";
import { cn } from "@/lib/utils";

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

// Clicking outside no longer closes the dialog — give a brief in-place zoom
// "pulse" instead, pointing the user back to the action buttons.
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
    <DialogOverlay
      class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
    >
      <DialogContent
        :class="
          cn(
            'relative z-50 grid w-full max-w-lg my-8 gap-4 border border-border bg-background p-6 shadow-lg duration-200 sm:rounded-lg md:w-full',
            pulsing && 'dialog-pulse',
            props.class,
          )
        "
        v-bind="{ ...$attrs, ...forwarded }"
        @interact-outside="onInteractOutside"
        @pointer-down-outside="
          (event) => {
            const originalEvent = event.detail.originalEvent;
            const target = originalEvent.target;
            if (
              originalEvent.offsetX > target.clientWidth ||
              originalEvent.offsetY > target.clientHeight
            ) {
              event.preventDefault();
            }
          }
        "
      >
        <slot />

        <DialogClose
          class="absolute top-4 right-4 p-0.5 transition-colors rounded-md hover:bg-secondary"
        >
          <XIcon class="w-4 h-4" />
          <span class="sr-only">Close</span>
        </DialogClose>
      </DialogContent>
    </DialogOverlay>
  </DialogPortal>
</template>

<style>
/* Unscoped: DialogContent is teleported to a portal. Animate the standalone
   `scale` property so the dialog zooms in place without shifting position. */
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
