<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChartColumn, ChartSpline } from 'lucide-vue-next';
import { monotonePath } from '@/lib/curve';
import { EYEBROW, FIGURE, MUTED, SEGMENT, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';

/**
 * Training volume over time.
 *
 * A sibling of SpendingTrendChart rather than a reuse of it: that component
 * formats every value as currency and owns the `trend`/`at` query parameters of
 * the spending dashboard. Generalising it would have meant threading a formatter
 * and a param map through a component the whole finance side depends on, to save
 * a chart that is mostly a path and two loops. The curve itself *is* shared —
 * monotonePath comes from lib/curve.js, so both charts bend identically.
 *
 * Consumes the same bucket contract WorkoutTrend emits:
 * {key, label, caption, value, is_current, is_future}.
 */
const props = defineProps({
    series: { type: Object, required: true },
    granularity: { type: String, required: true },
    anchor: { type: String, default: null },
    options: { type: Array, default: () => [] },
    // Carried through on reload so the breakdown month picker is not dropped.
    baseQuery: { type: Object, default: () => ({}) },
});

const PERIODS = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
    { key: 'year', label: 'Year' },
    { key: 'all', label: 'All' },
];

// Bars vs line is pure presentation, so it stays client-side. Which period to
// show is data, so it goes to the server.
const view = ref('bar');
const loading = ref(false);

const buckets = computed(() => props.series?.buckets ?? []);
const peak = computed(() => Math.max(1, ...buckets.value.map((b) => b.value ?? 0)));

/** Reloads only the trend props — the cards and breakdown are left alone. */
function load(granularity, anchor = null) {
    router.get(
        route('exercise.dashboard'),
        // Dropping the anchor when granularity changes: an anchor is written in
        // that granularity's own format, so a week value is meaningless to year.
        anchor
            ? { ...props.baseQuery, trend: granularity, trend_anchor: anchor }
            : { ...props.baseQuery, trend: granularity },
        {
            only: ['trend', 'trend_granularity', 'trend_anchor', 'trend_options'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

// Compact on the axis: "12.4t" keeps a 31-bar month readable where "12,400 kg"
// would not fit.
function compact(kg) {
    if (kg >= 1000) {
        return `${Math.round(kg / 100) / 10}t`;
    }

    return `${Math.round(kg)}`;
}

/*
 * The line path, in a 0–100 viewBox so it scales with the card.
 *
 * Future buckets are excluded rather than plotted as zero — a line that dives to
 * the floor for the rest of the month claims the person stopped training, when
 * in fact those days have not happened.
 */
const linePath = computed(() => {
    const points = buckets.value
        .map((bucket, index) => ({ bucket, index }))
        .filter(({ bucket }) => !bucket.is_future)
        .map(({ bucket, index }) => ({
            x: buckets.value.length > 1 ? (index / (buckets.value.length - 1)) * 100 : 50,
            y: 100 - ((bucket.value ?? 0) / peak.value) * 100,
        }));

    return points.length > 1 ? monotonePath(points) : '';
});
</script>

<template>
    <div>
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p :class="EYEBROW">{{ __('Volume') }}</p>
                <p class="mt-1 text-2xl" :class="FIGURE">
                    {{ Math.round(series?.total ?? 0).toLocaleString() }}
                    <span class="text-base font-bold" :class="MUTED">kg</span>
                </p>
                <p class="mt-0.5 text-xs" :class="MUTED">{{ series?.label }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div :class="SEGMENT">
                    <button
                        v-for="period in PERIODS"
                        :key="period.key"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold transition"
                        :class="granularity === period.key ? SEGMENT_ON : SEGMENT_OFF"
                        @click="load(period.key)"
                    >
                        {{ __(period.label) }}
                    </button>
                </div>

                <div :class="SEGMENT">
                    <button
                        type="button"
                        class="px-2.5 py-1.5 transition"
                        :class="view === 'bar' ? SEGMENT_ON : SEGMENT_OFF"
                        :aria-label="__('Bars')"
                        @click="view = 'bar'"
                    >
                        <ChartColumn class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="px-2.5 py-1.5 transition"
                        :class="view === 'line' ? SEGMENT_ON : SEGMENT_OFF"
                        :aria-label="__('Line')"
                        @click="view = 'line'"
                    >
                        <ChartSpline class="size-4" />
                    </button>
                </div>
            </div>
        </header>

        <!-- The period dropdown: which week/month/year, once a granularity is
             chosen. Hidden for "all", which is a single span. -->
        <div v-if="granularity !== 'all' && options.length > 1" class="mt-4">
            <select
                class="h-9 rounded-full border border-border bg-card/70 px-3 text-xs font-semibold text-foreground"
                :value="anchor"
                @change="load(granularity, $event.target.value)"
            >
                <option v-for="option in options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>

        <div class="mt-6 transition-opacity" :class="loading ? 'opacity-40' : ''">
            <p v-if="!buckets.length" class="py-12 text-center text-sm" :class="MUTED">
                {{ __('Nothing logged in this period yet.') }}
            </p>

            <template v-else>
                <!-- Bars -->
                <div v-if="view === 'bar'" class="flex h-40 items-end gap-1">
                    <div
                        v-for="bucket in buckets"
                        :key="bucket.key"
                        class="group relative flex h-full flex-1 flex-col justify-end"
                        :title="`${bucket.caption} — ${Math.round(bucket.value).toLocaleString()} kg`"
                    >
                        <div
                            class="rounded-t-md transition-[height] duration-500 ease-out"
                            :class="[
                                bucket.is_current ? 'bg-primary' : 'bg-primary/35',
                                // A future bucket is drawn as nothing at all, not
                                // as a zero-height bar with a hover label.
                                bucket.is_future ? 'opacity-0' : '',
                            ]"
                            :style="{ height: `${Math.max(bucket.value > 0 ? 3 : 0, (bucket.value / peak) * 100)}%` }"
                        />
                    </div>
                </div>

                <!-- Line -->
                <svg
                    v-else
                    class="h-40 w-full overflow-visible"
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <path
                        v-if="linePath"
                        :d="linePath"
                        fill="none"
                        stroke="var(--color-primary)"
                        stroke-width="2"
                        vector-effect="non-scaling-stroke"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>

                <div class="mt-2 flex gap-1">
                    <span
                        v-for="bucket in buckets"
                        :key="bucket.key"
                        class="flex-1 text-center text-[10px] tabular-nums"
                        :class="MUTED"
                    >
                        {{ bucket.label }}
                    </span>
                </div>

                <p class="mt-3 text-right text-[11px]" :class="MUTED">
                    {{ __('Peak') }}: {{ compact(peak) }} kg
                </p>
            </template>
        </div>
    </div>
</template>
