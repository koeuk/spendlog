<script setup>
import { reactiveOmit } from "@vueuse/core";
import { DialogOverlay } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  forceMount: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <DialogOverlay
    data-slot="sheet-overlay"
    :class="
      cn(
        /* Paced with the panel rather than at its own speed: at 100ms the
           backdrop was fully dark before the sheet had gone anywhere, so the
           dimming read as a separate event that happened first. Leaving
           slightly quicker than it arrives keeps the page from staying dim
           under a sheet that has already gone. */
        'bg-black/10 supports-backdrop-filter:backdrop-blur-xs fixed inset-0 z-50 data-open:animate-in data-open:fade-in-0 data-open:duration-400 data-closed:animate-out data-closed:fade-out-0 data-closed:duration-200',
        props.class,
      )
    "
    v-bind="delegatedProps"
  >
    <slot />
  </DialogOverlay>
</template>
