<script setup>
import { computed } from 'vue';

/**
 * The colour field the glass cards refract. Without it the frosted surfaces
 * have nothing to bend and read as plain panels.
 *
 * Two looks:
 *  - stock (no tint): the soft green/blue/peach wash under a 70% white veil —
 *    the White preset's signature look;
 *  - tinted: a two-colour gradient derived from the chosen background preset,
 *    at the wash's old full strength, so picking Sage or Sky turns the whole
 *    room that colour instead of rendering flat.
 *
 * Fixed, not scrolling: the wash is the room, not part of the page — and a
 * fixed layer is composited once instead of repainting under every card on
 * every scroll frame.
 */
const props = defineProps({
    // Body-colour hex when a background preset is active; null keeps the stock
    // wash.
    tint: { type: String, default: null },
});

/**
 * The presets are near-white pastels (#f3f7f3…), invisible as blobs on their
 * own page. The hue is real though — so the blobs keep it and push saturation
 * up and lightness down until the colour actually shows.
 */
function hexToHsl(hex) {
    const value = hex.replace('#', '');

    if (!/^[0-9a-fA-F]{6}$/.test(value)) {
        return null;
    }

    const r = parseInt(value.slice(0, 2), 16) / 255;
    const g = parseInt(value.slice(2, 4), 16) / 255;
    const b = parseInt(value.slice(4, 6), 16) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    const d = max - min;

    if (d === 0) {
        return { h: 0, s: 0 };
    }

    const s = d / (1 - Math.abs(2 * l - 1));
    let h;

    if (max === r) {
        h = ((g - b) / d) % 6;
    } else if (max === g) {
        h = (b - r) / d + 2;
    } else {
        h = (r - g) / d + 4;
    }

    return { h: (Math.round(h * 60) + 360) % 360, s: s * 100 };
}

const blobs = computed(() => {
    if (!props.tint) {
        return null;
    }

    const hsl = hexToHsl(props.tint);

    if (!hsl) {
        return null;
    }

    // Greys (Silver) stay grey — boosting their trace saturation would invent
    // a hue the admin never picked.
    const sat = hsl.s < 5 ? 8 : Math.min(50, Math.max(35, hsl.s * 2.5));
    const light = (hue, l) => `hsl(${(hue + 360) % 360} ${sat}% ${l}%)`;
    const dark = (hue) => `hsl(${(hue + 360) % 360} ${Math.min(30, sat)}% 14%)`;

    // Three colours, like the stock wash: the preset's own hue leads and its
    // two neighbours back it — close enough in hue to read as that colour,
    // never as a rainbow.
    return [
        { light: light(hsl.h, 86), dark: dark(hsl.h) },
        { light: light(hsl.h + 30, 89), dark: dark(hsl.h + 30) },
        { light: light(hsl.h - 30, 89), dark: dark(hsl.h - 30) },
    ];
});
</script>

<template>
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <template v-if="blobs">
            <!-- The old wash strength on purpose — the preset was picked to be
                 seen, unlike the stock wash that only hints. Split into light
                 and dark sets because inline backgrounds cannot carry dark:
                 variants. -->
            <div
                class="absolute -left-32 -top-40 size-[640px] rounded-full opacity-70 blur-3xl dark:hidden"
                :style="{ backgroundColor: blobs[0].light }"
            />
            <div
                class="absolute -right-40 top-24 size-[560px] rounded-full opacity-60 blur-3xl dark:hidden"
                :style="{ backgroundColor: blobs[1].light }"
            />
            <div
                class="absolute -bottom-48 left-1/3 size-[600px] rounded-full opacity-60 blur-3xl dark:hidden"
                :style="{ backgroundColor: blobs[2].light }"
            />

            <div
                class="absolute -left-32 -top-40 hidden size-[640px] rounded-full opacity-40 blur-3xl dark:block"
                :style="{ backgroundColor: blobs[0].dark }"
            />
            <div
                class="absolute -right-40 top-24 hidden size-[560px] rounded-full opacity-35 blur-3xl dark:block"
                :style="{ backgroundColor: blobs[1].dark }"
            />
            <div
                class="absolute -bottom-48 left-1/3 hidden size-[600px] rounded-full opacity-35 blur-3xl dark:block"
                :style="{ backgroundColor: blobs[2].dark }"
            />
        </template>

        <template v-else>
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
        </template>
    </div>
</template>
