<script setup>
import { computed, ref } from 'vue';
import {
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { CircleAlert, TriangleAlert } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

/**
 * Confirmation for an action that cannot be undone.
 *
 * Built on AlertDialog rather than Dialog. The difference is not cosmetic: it
 * renders role="alertdialog", and it will not close on an outside click — a
 * stray click next to a destructive prompt should not dismiss it, which a plain
 * Dialog allows.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    confirmLabel: { type: String, required: true },
    cancelLabel: { type: String, default: 'Cancel' },
    // Disables both buttons and shows the pending label, so a slow request
    // cannot be fired twice.
    processing: { type: Boolean, default: false },
    processingLabel: { type: String, default: '' },
    // The confirm button's look. Destructive by default — deleting is what
    // this dialog was built for — but a confirmation can also guard something
    // constructive (verifying an email), where a red button says the wrong thing.
    variant: { type: String, default: 'destructive' },
    // Optional lucide component for the badge beside the title. Left unset,
    // the variant picks one: a warning triangle for destructive prompts, a
    // neutral alert circle otherwise.
    icon: { type: [Object, Function], default: null },
});

const badgeIcon = computed(
    () => props.icon ?? (props.variant === 'destructive' ? TriangleAlert : CircleAlert),
);

const emit = defineEmits(['update:open', 'confirm']);

const cancelRef = ref(null);

// An outside click already can't dismiss an AlertDialog — give a brief in-place
// zoom "pulse" so the click is acknowledged and points back to the buttons.
const pulsing = ref(false);
let pulseTimer;

function pulse(event) {
    event.preventDefault();
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
}

/**
 * Focus Cancel, not Confirm, when the dialog opens.
 *
 * The dialog exists because the action is destructive, so the default target of
 * a reflexive Enter should be the way out — not the delete.
 */
function focusCancel(event) {
    event.preventDefault();
    cancelRef.value?.$el?.focus?.();
}
</script>

<template>
    <AlertDialogRoot :open="open" @update:open="emit('update:open', $event)">
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 fixed inset-0 z-50 bg-black/50 duration-200 ease-[cubic-bezier(0.16,1,0.3,1)]"
            />

            <AlertDialogContent
                :class="[
                    'bg-popover text-popover-foreground data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 ease-[cubic-bezier(0.16,1,0.3,1)] ring-foreground/10 fixed top-1/2 left-1/2 z-50 grid w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl p-6 text-sm ring-1 duration-300 outline-none sm:max-w-md',
                    pulsing && 'dialog-pulse',
                ]"
                @open-auto-focus="focusCancel"
                @interact-outside="pulse"
            >
                <div class="flex items-start gap-3">
                    <!-- Tinted to match the confirm button, so the badge and
                         the action it warns about read as one thing. -->
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full"
                        :class="
                            variant === 'destructive'
                                ? 'bg-red-500/10 text-red-600 dark:bg-red-400/10 dark:text-red-400'
                                : 'bg-primary/10 text-primary'
                        "
                        aria-hidden="true"
                    >
                        <component :is="badgeIcon" class="size-5" />
                    </span>

                    <div class="min-w-0 space-y-1.5 pt-0.5">
                        <AlertDialogTitle class="text-base font-bold tracking-[-0.02em]">
                            {{ title }}
                        </AlertDialogTitle>
                        <AlertDialogDescription
                            v-if="description"
                            class="text-sm text-neutral-500 dark:text-neutral-400"
                        >
                            {{ description }}
                        </AlertDialogDescription>
                    </div>
                </div>

                <!-- For confirmations that need input before the action can run
                     (e.g. re-entering a password). Empty for the common case. -->
                <slot />

                <div class="flex justify-end gap-2">
                    <AlertDialogCancel ref="cancelRef" as-child>
                        <Button variant="outline" size="sm" :disabled="processing">
                            {{ cancelLabel }}
                        </Button>
                    </AlertDialogCancel>

                    <!--
                        Not AlertDialogAction: that closes the dialog on click, so
                        a failed request would leave the prompt gone and the row
                        still there with nothing said. The caller closes it once
                        the request actually succeeds.
                    -->
                    <Button
                        :variant="variant"
                        size="sm"
                        :disabled="processing"
                        @click="emit('confirm')"
                    >
                        {{ processing && processingLabel ? processingLabel : confirmLabel }}
                    </Button>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>

<style>
/* Unscoped: the dialog is teleported to a portal. Animate the standalone
   `scale` property so it zooms in place without shifting position. */
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
