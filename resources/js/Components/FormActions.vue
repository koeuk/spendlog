<script setup>
/**
 * The action bar at the foot of a form screen.
 *
 * `mt-auto sticky bottom-0` inside a full-height flex column, rather than
 * `fixed`. Fixed was the obvious answer and the wrong one: a `backdrop-filter`
 * ancestor — which every CARD carries — makes itself the containing block for
 * fixed descendants, so the bar pinned to the bottom of the card instead of the
 * viewport and landed mid-screen over the last field. Sticky has no such
 * failure mode, and it also sidesteps iOS Safari's habit of mismeasuring the
 * viewport for fixed elements while the address bar collapses.
 *
 * mt-auto puts it on the bottom edge when the form is shorter than the screen;
 * sticky keeps it there while a longer one scrolls underneath. Both cases land
 * in the same place, which is the whole point.
 *
 * Two equal columns on a phone, because a thumb reaching the bottom of the
 * screen should not have to aim. From sm: up they shrink to their content and
 * sit right, where a full-width pair would look like a mistake.
 *
 * Cancel first in the DOM and on the left: the primary action goes to the right
 * on Android and in every dialog in this app, and tab order should reach the
 * safe option before the committing one.
 */
</script>

<template>
    <!-- The negative margin cancels the layout column's gutter so the glass
         reaches the screen edges, while the buttons stay on the same grid as
         the fields above. -->
    <div
        class="sticky bottom-0 -mx-3 mt-auto border-t border-border/60 bg-background/90 px-3 pt-3 backdrop-blur-xl pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:-mx-4 lg:px-4"
    >
        <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
            <slot name="cancel" />
            <slot name="submit" />
        </div>
    </div>
</template>
