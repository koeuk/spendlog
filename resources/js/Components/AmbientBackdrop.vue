<script setup>
/**
 * The colour field the glass cards refract. Without it the frosted surfaces
 * have nothing to bend and read as plain panels.
 *
 * Fixed, not scrolling: the wash is the room, not part of the page — and a
 * fixed layer is composited once instead of repainting under every card on
 * every scroll frame.
 */
</script>

<template>
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <!-- Same green family as the auth artwork, so the app reads as one product.
             Kept faint — the wash should be sensed behind the glass, not seen. -->
        <div
            class="absolute -left-32 -top-40 size-[640px] rounded-full bg-[#dcefd6] opacity-40 blur-3xl dark:bg-[#12301c] dark:opacity-30"
        />
        <div
            class="absolute -right-40 top-24 size-[560px] rounded-full bg-[#e6f0ff] opacity-35 blur-3xl dark:bg-[#101b2e] dark:opacity-30"
        />
        <div
            class="absolute -bottom-48 left-1/3 size-[600px] rounded-full bg-[#fdeee0] opacity-35 blur-3xl dark:bg-[#2a1c12] dark:opacity-25"
        />

        <!-- A 70% white veil over the blobs: the page reads as white with only
             the last 30% of the wash tinting through. -->
        <div class="absolute inset-0 bg-white/70 dark:bg-neutral-950/70" />
    </div>
</template>
