<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    // 'ok' | 'warning' | 'over' | 'none' — computed server-side by BudgetSummary.
    status: { type: String, default: 'none' },
    barPercent: { type: Number, default: 0 },
    /**
     * 'lg' thickens the track for the dashboard hero, where the bar is the
     * card's subject rather than a footnote to a row. Every other caller gets
     * the original 8px by default, so the budgets list is untouched.
     */
    size: { type: String, default: 'sm' },
    /**
     * Where an even spend would have reached by now, 0–100, or null for no
     * marker. Decorative on its own — whoever passes it is expected to say the
     * same thing in words nearby, since a tick mark is not readable by itself.
     */
    marker: { type: Number, default: null },
    /**
     * Fill from empty on mount instead of appearing already full. Off by
     * default: in a list of a dozen budget rows a dozen simultaneous fills is
     * noise, and only the hero earns the entrance.
     */
    animate: { type: Boolean, default: false },
});

const STATUS_CLASSES = {
    ok: 'bg-green-500',
    warning: 'bg-amber-500',
    over: 'bg-red-500',
    none: 'bg-gray-300',
};

const barClass = computed(() => STATUS_CLASSES[props.status] ?? STATUS_CLASSES.none);

/*
 * Starts true when not animating, so the bar paints at its real width on the
 * first frame and nothing moves. When animating it flips on the frame after
 * mount — two paints, which is what gives the transition something to move
 * between rather than collapsing into the final width.
 */
const filled = ref(!props.animate);

onMounted(() => {
    if (props.animate) {
        requestAnimationFrame(() => (filled.value = true));
    }
});

const width = computed(() => (filled.value ? props.barPercent : 0));

// Kept off both ends so the tick never half-escapes the rounded track.
const markerLeft = computed(() => Math.min(Math.max(props.marker ?? 0, 1), 99));
</script>

<template>
    <div class="relative">
        <div
            class="w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
            :class="size === 'lg' ? 'h-3' : 'h-2'"
            role="progressbar"
            :aria-valuenow="barPercent"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full transition-[width] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                :class="barClass"
                :style="{ width: `${width}%` }"
            />
        </div>

        <!-- The even-pace tick. Sits above the fill rather than inside it so it
             stays legible whichever side of it the bar has reached, and is
             aria-hidden because the sentence under the bar carries the meaning. -->
        <span
            v-if="marker !== null"
            aria-hidden="true"
            class="pointer-events-none absolute -top-0.5 w-0.5 -translate-x-1/2 rounded-full bg-neutral-900/30 dark:bg-white/40"
            :class="size === 'lg' ? 'h-4' : 'h-3'"
            :style="{ left: `${markerLeft}%` }"
        />
    </div>
</template>
