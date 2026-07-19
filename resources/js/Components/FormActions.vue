<script setup>
/**
 * The action bar at the foot of a form screen.
 *
 * sticky bottom-0, not fixed: it belongs to the form, so it scrolls into place
 * on a short form and pins itself on a long one. The category screen is what
 * asked for this — fifty icon tiles put Save below the fold, so saving meant
 * scrolling past the whole grid to find the button.
 *
 * Two equal columns on a phone, because a thumb reaching the bottom of the
 * screen should not have to aim. From sm: up they shrink to their content and
 * sit right, where a full-width pair would look like a mistake.
 *
 * Cancel first in the DOM and on the left: the primary action goes to the right
 * on Android and in every dialog in this app, and tab order should reach the
 * safe option before the committing one. On a phone the two are the same width,
 * so the only thing telling them apart is position and weight — which is why
 * they are never allowed to swap.
 */
</script>

<template>
    <div
        class="sticky bottom-0 -mx-4 mt-6 rounded-b-[28px] border-t border-border/60 bg-background/85 px-4 py-3 backdrop-blur-xl sm:-mx-6 sm:px-6 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
    >
        <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
            <slot name="cancel" />
            <slot name="submit" />
        </div>
    </div>
</template>
