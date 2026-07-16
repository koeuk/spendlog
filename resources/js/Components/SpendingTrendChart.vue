<script setup>
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChartColumn, ChartSpline } from 'lucide-vue-next';
import { monotonePath } from '@/lib/curve';
import { EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';

const props = defineProps({
    // { granularity, anchor, options: [{value,label}], series: {label,total,buckets} }
    trend: { type: Object, required: true },
    // Which page owns the chart. The dashboard refreshes only its trend key;
    // the report has to refresh its table and totals alongside it.
    routeName: { type: String, default: 'dashboard' },
    reloadKeys: { type: Array, default: () => ['trend'] },
    // The report puts the period controls in its own header instead.
    showControls: { type: Boolean, default: true },
});

const PERIODS = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
    { key: 'year', label: 'Year' },
];

const VIEWS = [
    { key: 'bar', label: 'Bars', icon: ChartColumn },
    { key: 'line', label: 'Line', icon: ChartSpline },
];

// Bars vs line is pure presentation, so it stays client-side. Which period to
// show is data, so it goes to the server.
const view = ref('line');

const series = computed(() => props.trend.series);

const loading = ref(false);

/**
 * Reloads only the `trend` prop — the rest of the dashboard is untouched, so
 * changing period does not re-run the budget and category queries.
 */
function load(granularity, anchor = null) {
    router.get(
        route(props.routeName),
        // Dropping `at` when the granularity changes: an anchor is written in
        // that granularity's own format, so a week value is meaningless to year.
        anchor ? { trend: granularity, at: anchor } : { trend: granularity },
        {
            only: props.reloadKeys,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

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
 * The tallest bucket defines the scale. Guarded against an all-zero period,
 * which would otherwise divide by zero and render everything full height.
 */
const max = computed(() =>
    Math.max(...series.value.buckets.map((b) => b.value), 0) || 1,
);

/* ---------------------------------------------------------------- bars ---- */

function heightFor(bucket) {
    if (bucket.is_future || bucket.value <= 0) {
        return 0;
    }

    // Floor at 2%: a real but tiny expense must still leave a mark, or the day
    // reads as empty.
    return Math.max((bucket.value / max.value) * 100, 2);
}

/* ---------------------------------------------------------------- line ---- */

// A fixed viewBox stretched to the box; the stroke keeps its width via
// vector-effect, and the dots are DOM, so nothing ends up oval.
const VB = { w: 1000, h: 300, pad: 12 };

/** Only elapsed buckets: a line running to zero across future days would read as a crash. */
const elapsed = computed(() => series.value.buckets.filter((b) => !b.is_future));

const points = computed(() => {
    const buckets = series.value.buckets;
    const last = buckets.length - 1 || 1;
    const usable = VB.h - VB.pad * 2;

    return elapsed.value.map((bucket) => {
        const index = buckets.indexOf(bucket);

        return {
            index,
            bucket,
            // Anchored to the bucket's slot on the full axis, so line and bars
            // line up with the same labels.
            x: (index / last) * VB.w,
            y: VB.pad + usable - (bucket.value / max.value) * usable,
        };
    });
});

const linePath = computed(() => monotonePath(points.value));

const areaPath = computed(() => {
    if (points.value.length < 2) {
        return '';
    }

    const first = points.value[0];
    const last = points.value[points.value.length - 1];

    return `${linePath.value} L ${last.x} ${VB.h} L ${first.x} ${VB.h} Z`;
});

/** The dot sits on the hovered bucket, or on the latest elapsed one at rest. */
const marker = computed(() => {
    if (!points.value.length) {
        return null;
    }

    const point =
        points.value.find((p) => p.index === hovered.value) ??
        (hovered.value === null ? points.value[points.value.length - 1] : null);

    if (!point) {
        return null;
    }

    return {
        left: (point.x / VB.w) * 100,
        top: (point.y / VB.h) * 100,
        value: money.format(point.bucket.value),
        index: point.index,
    };
});

/* ------------------------------------------------------------- hovering --- */

const hovered = ref(null);

const tooltip = computed(() => {
    if (hovered.value === null) {
        return null;
    }

    const bucket = series.value.buckets[hovered.value];

    return {
        caption: bucket.caption,
        // A future bucket has no total — reporting "$0.00" would claim nothing
        // was spent, when the day simply has not happened.
        value: bucket.is_future ? '—' : money.format(bucket.value),
        // Percent across the plot, so the card can be any width. Clamped so the
        // first and last buckets do not push the tooltip past the card edge.
        left: Math.min(
            Math.max(((hovered.value + 0.5) / series.value.buckets.length) * 100, 8),
            92,
        ),
        // The crosshair tracks the true position, unclamped.
        rail: (hovered.value / (series.value.buckets.length - 1 || 1)) * 100,
    };
});

/**
 * In the line view the label rides just above the marker, as a point label
 * should — parked at the top of the plot it sat on the peak it was describing.
 *
 * Floored at 22% so a tall point does not push it off the top; the bar view
 * keeps the fixed position, since a bar has no single point to sit over.
 */
const tooltipTop = computed(() => {
    if (view.value !== 'line' || !marker.value) {
        return null;
    }

    return Math.max(marker.value.top, 22);
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

            <!-- Filters in one row above the plot: which period, then how it is drawn. -->
            <div v-if="showControls" class="flex flex-wrap items-center gap-2">
<!--
                    Ours rather than a native <select>: the OS popup is drawn
                    outside the page and ignores the theme, so it lands as a white
                    system menu on a dark chart. Trade-off noted: on a phone this
                    loses the native picker wheel.
                -->
                <Select
                    :model-value="trend.anchor"
                    :disabled="loading"
                    @update:model-value="load(trend.granularity, $event)"
                >
                    <SelectTrigger
                        class="h-8 w-auto min-w-32 gap-1.5 rounded-full border-neutral-200 bg-white/70 px-3 text-xs font-semibold shadow-none dark:border-neutral-700 dark:bg-neutral-800/70"
                        :aria-label="__('Period')"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in trend.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

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
                            trend.granularity === option.key
                                ? 'bg-primary text-primary-foreground'
                                : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'
                        "
                        :aria-pressed="trend.granularity === option.key"
                        @click="load(option.key)"
                    >
                        {{ __(option.label) }}
                    </button>
                </div>

                <div
                    class="inline-flex rounded-full border border-neutral-200 bg-white/70 p-0.5 dark:border-neutral-700 dark:bg-neutral-800/70"
                    role="group"
                    :aria-label="__('View')"
                >
                    <button
                        v-for="option in VIEWS"
                        :key="option.key"
                        type="button"
                        class="grid size-7 place-items-center rounded-full transition-colors duration-200"
                        :class="
                            view === option.key
                                ? 'bg-primary text-primary-foreground'
                                : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'
                        "
                        :aria-pressed="view === option.key"
                        :aria-label="__(option.label)"
                        :title="__(option.label)"
                        @click="view = option.key"
                    >
                        <component :is="option.icon" class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Plot -->
        <div
            class="relative mt-6 transition-opacity duration-200"
            :class="loading ? 'opacity-50' : 'opacity-100'"
        >
            <!-- One recessive reference line at the peak; a full grid would out-shout the marks. -->
            <div
                class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-neutral-200 dark:border-neutral-800"
            >
                <span
                    :class="[MUTED, 'absolute -top-2 right-0 text-[10px] font-medium tabular-nums']"
                >
                    {{ compact.format(max) }}
                </span>
            </div>

            <!-- Bars -->
            <div v-if="view === 'bar'" class="flex h-40 items-end gap-[2px]" @mouseleave="hovered = null">
                <div
                    v-for="(bucket, index) in series.buckets"
                    :key="bucket.key"
                    class="group relative flex h-full flex-1 cursor-default items-end"
                    @mouseenter="hovered = index"
                >
                    <!-- The whole column is the hit target, not the bar, so a $0 day is still hoverable. -->
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

            <!-- Line + area -->
            <div v-else class="relative h-40" @mouseleave="hovered = null">
                <!-- currentColor drives stroke and gradient alike, so dark mode is one class. -->
                <svg
                    class="h-full w-full overflow-visible text-[#4b9d5f] dark:text-[#52a869]"
                    :viewBox="`0 0 ${VB.w} ${VB.h}`"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient :id="`trend-${trend.granularity}`" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="currentColor" stop-opacity="0.28" />
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <path v-if="areaPath" :d="areaPath" :fill="`url(#trend-${trend.granularity})`" />
                    <path
                        v-if="linePath"
                        :d="linePath"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>

                <!-- Crosshair -->
                <div
                    v-if="tooltip"
                    class="pointer-events-none absolute inset-y-0 w-px bg-neutral-300 dark:bg-neutral-600"
                    :style="{ left: `${tooltip.rail}%` }"
                />

                <!-- Marker: DOM, not SVG, so the stretched viewBox cannot squash it oval. -->
                <div
                    v-if="marker"
                    class="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 transition-[left,top] duration-200 ease-out"
                    :style="{ left: `${marker.left}%`, top: `${marker.top}%` }"
                >
                    <span
                        class="block size-[9px] rounded-full border-2 border-[#4b9d5f] bg-white dark:border-[#52a869] dark:bg-neutral-900"
                    />
                </div>

                <!-- Hit targets: one invisible column per bucket, so hovering is
                     forgiving instead of demanding the pointer find a 2px line. -->
                <div class="absolute inset-0 flex">
                    <div
                        v-for="(bucket, index) in series.buckets"
                        :key="bucket.key"
                        class="h-full flex-1 cursor-default"
                        @mouseenter="hovered = index"
                    />
                </div>
            </div>

            <!-- Axis -->
            <div class="mt-2 flex gap-[2px] border-t border-neutral-200 pt-2 dark:border-neutral-800">
                <span
                    v-for="bucket in series.buckets"
                    :key="bucket.key"
                    class="flex-1 text-center text-[10px] font-medium tabular-nums"
                    :class="[
                        bucket.is_current ? 'text-neutral-900 dark:text-neutral-100' : MUTED,
                        // Not yet happened: recede, so the empty space past today
                        // reads as unwritten rather than as a run of zeros.
                        bucket.is_future ? 'opacity-40' : '',
                    ]"
                >
                    {{ bucket.label }}
                </span>
            </div>

            <!-- Tooltip: text tokens only, with the value carrying no series colour.
                 Sits inside the plot, not above it — floated above, it landed on
                 top of the period buttons. -->
            <div
                v-if="tooltip"
                class="pointer-events-none absolute z-10 -translate-x-1/2 whitespace-nowrap rounded-xl border border-neutral-200 bg-white px-2.5 py-1.5 shadow-lg transition-[left,top] duration-200 ease-out dark:border-neutral-700 dark:bg-neutral-900"
                :class="tooltipTop === null ? 'top-1' : '-translate-y-[calc(100%+12px)]'"
                :style="{
                    left: `${tooltip.left}%`,
                    ...(tooltipTop === null ? {} : { top: `${tooltipTop}%` }),
                }"
                role="status"
            >
                <p class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400">
                    {{ tooltip.caption }}
                </p>
                <p class="text-sm font-bold tabular-nums">{{ tooltip.value }}</p>
            </div>
        </div>

        <!-- The same numbers for anyone who cannot read the plot. -->
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
