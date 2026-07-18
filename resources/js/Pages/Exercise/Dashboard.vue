<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Dumbbell, Flame, Layers, Timer, Trophy } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConsistencyHeatmap from '@/Components/Exercise/ConsistencyHeatmap.vue';
import ExerciseBadge from '@/Components/Exercise/ExerciseBadge.vue';
import VolumeTrendChart from '@/Components/Exercise/VolumeTrendChart.vue';
import { CARD, CARD_TINT, EYEBROW, FIGURE, MUTED, PILL_ACTION } from '@/lib/appStyles';
import { exerciseColor, formatDuration, formatWeight } from '@/lib/exerciseStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    summary: { type: Object, required: true },
    streak: { type: Number, default: 0 },
    records: { type: Array, default: () => [] },
    breakdown: { type: Array, default: () => [] },
    trend: { type: Object, required: true },
    trend_granularity: { type: String, required: true },
    trend_anchor: { type: String, default: null },
    trend_options: { type: Array, default: () => [] },
    heatmap: { type: Object, required: true },
    month: { type: String, required: true },
    breakdown_month: { type: String, required: true },
    months: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();

// The viewer's unit. Weights are stored in kilograms; this is display only.
const unit = computed(() => page.props.default_weight_unit ?? 'kg');

/*
 * The two month pickers reload only their own props, so moving the breakdown
 * month does not re-run the trend query — and vice versa. Each carries the
 * other's current value so router.get's full query-string replacement does not
 * silently reset it.
 */
const baseQuery = computed(() => ({
    month: props.month,
    breakdown_month: props.breakdown_month,
}));

function setMonth(key, value) {
    router.get(
        route('exercise.dashboard'),
        { ...baseQuery.value, [key]: value },
        {
            only: key === 'month'
                ? ['summary', 'month']
                : ['breakdown', 'breakdown_month'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

/** 'YYYY-MM' split for the two selects, and reassembled on change. */
function parts(value) {
    const [year, month] = (value ?? '').split('-');

    return { year, month };
}

const cards = computed(() => [
    {
        key: 'sessions',
        label: trans('Sessions'),
        value: props.summary.sessions,
        icon: Dumbbell,
    },
    {
        key: 'volume',
        label: trans('Volume'),
        value: formatWeight(props.summary.volume_kg, unit.value),
        icon: Layers,
    },
    {
        key: 'time',
        label: trans('Time'),
        value: formatDuration(props.summary.duration_seconds),
        icon: Timer,
    },
    {
        key: 'sets',
        label: trans('Sets'),
        value: props.summary.sets,
        icon: Trophy,
    },
]);
</script>

<template>
    <Head :title="__('Exercise')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ __('Exercise') }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ __('Training') }}
                    </h1>
                </div>

                <Link
                    v-if="can.create"
                    :href="route('exercise.workouts.index')"
                    class="bg-primary text-primary-foreground inline-flex items-center gap-2 transition hover:opacity-90"
                    :class="PILL_ACTION"
                >
                    <Dumbbell class="size-4" />
                    {{ __('Log a workout') }}
                </Link>
            </div>
        </template>

        <div class="space-y-3">
            <!-- Streak first: it is the one figure that changes behaviour, and
                 it is about today rather than the month being browsed. -->
            <div :class="[CARD_TINT, 'anim flex items-center gap-4 p-6']" style="--d: 0ms">
                <span class="grid size-12 shrink-0 place-items-center rounded-full bg-primary/15">
                    <Flame class="size-6 text-primary" aria-hidden="true" />
                </span>

                <div>
                    <p :class="EYEBROW">{{ __('Current streak') }}</p>
                    <p class="mt-0.5 text-3xl" :class="FIGURE">
                        {{ streak }}
                        <span class="text-base font-bold" :class="MUTED">
                            {{ streak === 1 ? __('day') : __('days') }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Month figures -->
            <div :class="[CARD, 'anim p-6']" style="--d: 60ms">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <p :class="EYEBROW">{{ __('This month') }}</p>

                    <div class="flex gap-2">
                        <select
                            class="h-9 rounded-full border border-border bg-card/70 px-3 text-xs font-semibold"
                            :value="parts(month).month"
                            @change="setMonth('month', `${parts(month).year}-${$event.target.value}`)"
                        >
                            <option v-for="m in months" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>

                        <select
                            class="h-9 rounded-full border border-border bg-card/70 px-3 text-xs font-semibold"
                            :value="parts(month).year"
                            @change="setMonth('month', `${$event.target.value}-${parts(month).month}`)"
                        >
                            <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
                        </select>
                    </div>
                </header>

                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div v-for="card in cards" :key="card.key">
                        <div class="flex items-center gap-1.5" :class="MUTED">
                            <component :is="card.icon" class="size-3.5" aria-hidden="true" />
                            <span class="text-[11px] font-semibold uppercase tracking-wide">
                                {{ card.label }}
                            </span>
                        </div>
                        <p class="mt-1 text-2xl" :class="FIGURE">{{ card.value }}</p>
                    </div>
                </div>
            </div>

            <!-- Volume over time -->
            <div :class="[CARD, 'anim p-6']" style="--d: 120ms">
                <VolumeTrendChart
                    :series="trend"
                    :granularity="trend_granularity"
                    :anchor="trend_anchor"
                    :options="trend_options"
                    :base-query="baseQuery"
                />
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <!-- Muscle-group split -->
                <div :class="[CARD, 'anim p-6']" style="--d: 180ms">
                    <header class="flex flex-wrap items-center justify-between gap-3">
                        <p :class="EYEBROW">{{ __('Muscle groups') }}</p>

                        <select
                            class="h-9 rounded-full border border-border bg-card/70 px-3 text-xs font-semibold"
                            :value="parts(breakdown_month).month"
                            @change="
                                setMonth(
                                    'breakdown_month',
                                    `${parts(breakdown_month).year}-${$event.target.value}`,
                                )
                            "
                        >
                            <option v-for="m in months" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                    </header>

                    <p v-if="!breakdown.length" class="py-10 text-center text-sm" :class="MUTED">
                        {{ __('No sets logged this month.') }}
                    </p>

                    <ul v-else class="mt-5 space-y-3">
                        <li v-for="row in breakdown" :key="row.group">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-semibold">{{ row.label }}</span>
                                <span :class="MUTED">
                                    {{ __(':count sets', { count: row.sets }) }}
                                    <span v-if="row.volume_kg > 0">
                                        · {{ formatWeight(row.volume_kg, unit) }}
                                    </span>
                                </span>
                            </div>

                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-muted">
                                <!-- The class comes from the palette map, not
                                     from `bg-${row.color}-500`: Tailwind scans
                                     source text, so a name built at runtime is
                                     never generated and the bar renders bare. -->
                                <div
                                    class="h-full rounded-full transition-[width] duration-500 ease-out"
                                    :class="exerciseColor(row.color).bar"
                                    :style="{ width: `${row.share}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Personal records -->
                <div :class="[CARD, 'anim p-6']" style="--d: 240ms">
                    <p :class="EYEBROW">{{ __('Personal records') }}</p>

                    <p v-if="!records.length" class="py-10 text-center text-sm" :class="MUTED">
                        {{ __('Log a weighted set to start tracking records.') }}
                    </p>

                    <ul v-else class="mt-5 space-y-3">
                        <li
                            v-for="record in records"
                            :key="record.name"
                            class="flex items-center justify-between gap-3"
                        >
                            <ExerciseBadge
                                :name="record.name"
                                :color="record.color"
                                :icon="record.icon"
                                class="min-w-0 text-sm font-semibold"
                            />

                            <div class="shrink-0 text-right">
                                <p class="text-sm font-bold tabular-nums">
                                    {{ formatWeight(record.weight_kg, unit) }}
                                </p>
                                <p class="text-[11px]" :class="MUTED">
                                    {{ __(':reps reps', { reps: record.reps }) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Consistency -->
            <div :class="[CARD, 'anim p-6']" style="--d: 300ms">
                <ConsistencyHeatmap :heatmap="heatmap" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
