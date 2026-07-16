<script setup>
import { computed, ref } from 'vue';
import { EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';

const props = defineProps({
    // { week: {label, total, buckets[]}, month: {...}, year: {...} }
    trend: { type: Object, required: true },
});

const PERIODS = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
    { key: 'year', label: 'Year' },
];

const period = ref('month');
const series = computed(() => props.trend[period.value]);

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

// Compact on the axis: "$1.2k" keeps a 31-bar month readable where "$1,240.00"
// would not fit.
const compact = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    notation: 'compact',
    maximumFractionDigits: 1,
});

/**
 * The tallest bar defines the scale. Guarded against an all-zero period, which
 * would otherwise divide by zero and render every bar full height.
 */
const max = computed(() =>
    Math.max(...series.value.buckets.map((b) => b.value), 0) || 1,
);

function heightFor(bucket) {
    if (bucket.is_future || bucket.value <= 0) {
        return 0;
    }

    // Floor at 2%: a real but tiny expense must still leave a mark, or the day
    // reads as empty.
    return Math.max((bucket.value / max.value) * 100, 2);
}

const hovered = ref(null);

const tooltip = computed(() => {
    if (hovered.value === null) {
        return null;
    }

    const bucket = series.value.buckets[hovered.value];

    return {
        caption: bucket.caption,
        value: money.format(bucket.value),
        // Percent across the plot, so the card can be any width.
        left: ((hovered.value + 0.5) / series.value.buckets.length) * 100,
    };
});
</script>

<template>
    <div>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p :class="EYEBROW">{{ __('Spending') }}</p>
                <p class="mt-1.5 flex items-baseline gap-2">
                    <span :class="[FIGURE, 'text-2xl']">
                        {{ money.format(series.total) }}
                    </span>
                    <span :class="[MUTED, 'text-xs font-medium']">
                        {{ series.label }}
                    </span>
                </p>
            </div>

            <!-- One row of filters above the plot, matching the app's segmented controls -->
            <div
                class="inline-flex rounded-full border border-neutral-200 bg-white/70 p-0.5 dark:border-neutral-700 dark:bg-neutral-800/70"
                role="group"
                :aria-label="__('Spending')"
            >
                <button
                    v-for="option in PERIODS"
                    :key="option.key"
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-semibold transition-colors duration-200"
                    :class="
                        period === option.key
                            ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                            : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'
                    "
                    :aria-pressed="period === option.key"
                    @click="period = option.key"
                >
                    {{ __(option.label) }}
                </button>
            </div>
        </div>

        <!-- Plot -->
        <div class="relative mt-6">
            <!-- One recessive reference line at the peak; a full grid would out-shout the bars. -->
            <div
                class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-neutral-200 dark:border-neutral-800"
            >
                <span
                    :class="[MUTED, 'absolute -top-2 right-0 bg-transparent text-[10px] font-medium tabular-nums']"
                >
                    {{ compact.format(max) }}
                </span>
            </div>

            <div
                class="flex h-40 items-end gap-[2px]"
                @mouseleave="hovered = null"
            >
                <div
                    v-for="(bucket, index) in series.buckets"
                    :key="bucket.key"
                    class="group relative flex h-full flex-1 cursor-default items-end"
                    @mouseenter="hovered = index"
                >
                    <!-- Track: the full column is the hit target, not the bar,
                         so a $0 day is still hoverable. -->
                    <div
                        class="absolute inset-0 rounded-t-[4px] transition-colors duration-150"
                        :class="hovered === index ? 'bg-neutral-900/[0.04] dark:bg-white/[0.06]' : ''"
                    />

                    <div
                        class="relative w-full rounded-t-[4px] bg-[#4b9d5f] transition-[height,opacity] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] dark:bg-[#52a869]"
                        :class="[
                            hovered !== null && hovered !== index ? 'opacity-45' : 'opacity-100',
                            bucket.is_current ? 'ring-2 ring-inset ring-white/40 dark:ring-black/20' : '',
                        ]"
                        :style="{ height: `${heightFor(bucket)}%` }"
                    />
                </div>
            </div>

            <!-- Axis -->
            <div class="mt-2 flex gap-[2px] border-t border-neutral-200 pt-2 dark:border-neutral-800">
                <span
                    v-for="bucket in series.buckets"
                    :key="bucket.key"
                    class="flex-1 text-center text-[10px] font-medium tabular-nums"
                    :class="bucket.is_current ? 'text-neutral-900 dark:text-neutral-100' : MUTED"
                >
                    {{ bucket.label }}
                </span>
            </div>

            <!-- Tooltip: text tokens only, with the value carrying no series colour. -->
            <div
                v-if="tooltip"
                class="pointer-events-none absolute -top-2 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-xl border border-neutral-200 bg-white px-2.5 py-1.5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900"
                :style="{ left: `${tooltip.left}%` }"
                role="status"
            >
                <p class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400">
                    {{ tooltip.caption }}
                </p>
                <p class="text-sm font-bold tabular-nums">{{ tooltip.value }}</p>
            </div>
        </div>

        <!-- The same numbers for anyone who cannot read the bars. -->
        <table class="sr-only">
            <caption>
                {{ __('Spending') }} — {{ series.label }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('Date') }}</th>
                    <th scope="col">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="bucket in series.buckets" :key="bucket.key">
                    <th scope="row">{{ bucket.caption }}</th>
                    <td>{{ money.format(bucket.value) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
