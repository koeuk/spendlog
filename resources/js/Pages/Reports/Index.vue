<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SpendingTrendChart from '@/Components/SpendingTrendChart.vue';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { ACTIVE, CARD, CARD_TINT, EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';
import { ArrowDownRight, ArrowUpRight } from 'lucide-vue-next';

const props = defineProps({
    granularity: { type: String, required: true },
    anchor: { type: String, required: true },
    options: { type: Array, required: true },
    series: { type: Object, required: true },
    breakdown: { type: Array, required: true },
    stats: { type: Object, required: true },
});

const PERIODS = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
    { key: 'year', label: 'Year' },
];

// Everything on this page describes the chosen period, so a change reloads all
// of it — unlike the dashboard, where only the chart follows the picker.
const RELOAD_KEYS = ['granularity', 'anchor', 'options', 'series', 'breakdown', 'stats'];

const loading = ref(false);

function load(granularity, anchor = null) {
    router.get(
        route('reports.index'),
        anchor ? { period: granularity, at: anchor } : { period: granularity },
        {
            only: RELOAD_KEYS,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

// The chart component wants one object; the page receives the pieces flat.
const trend = computed(() => ({
    granularity: props.granularity,
    anchor: props.anchor,
    options: props.options,
    series: props.series,
}));

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const isEmpty = computed(() => props.breakdown.length === 0);

// Spending less than last period is good news, so down is the positive colour —
// the opposite of the usual "up is good" reflex.
const change = computed(() => {
    if (props.stats.change_percent === null) {
        return null;
    }

    const up = props.stats.change_percent > 0;

    return {
        up,
        icon: up ? ArrowUpRight : ArrowDownRight,
        text: `${up ? '+' : ''}${props.stats.change_percent}%`,
        tone: up
            ? 'text-red-600 dark:text-red-400'
            : 'text-[#4b9d5f] dark:text-[#6cc182]',
    };
});
</script>

<template>
    <Head :title="__('Reports')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ series.label }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ __('Reports') }}
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        :value="anchor"
                        class="h-9 rounded-full border-neutral-200 bg-white/70 py-0 pe-8 ps-3 text-xs font-semibold text-neutral-700 focus:border-neutral-400 focus:ring-0 dark:border-neutral-700 dark:bg-neutral-800/70 dark:text-neutral-200"
                        :aria-label="__('Period')"
                        :disabled="loading"
                        @change="load(granularity, $event.target.value)"
                    >
                        <option v-for="option in options" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <div
                        class="inline-flex rounded-full border border-neutral-200 bg-white/70 p-0.5 dark:border-neutral-700 dark:bg-neutral-800/70"
                        role="group"
                        :aria-label="__('Period')"
                    >
                        <button
                            v-for="option in PERIODS"
                            :key="option.key"
                            type="button"
                            class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors duration-200"
                            :class="
                                granularity === option.key
                                    ? ACTIVE
                                    : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'
                            "
                            :aria-pressed="granularity === option.key"
                            @click="load(option.key)"
                        >
                            {{ __(option.label) }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div
            class="space-y-3 transition-opacity duration-200"
            :class="loading ? 'opacity-60' : 'opacity-100'"
        >
            <!-- Headline figures -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div :class="[CARD_TINT, 'anim p-6']" style="--d: 60ms">
                    <p :class="EYEBROW">{{ __('Total spent') }}</p>
                    <p :class="[FIGURE, 'mt-2 text-3xl']">{{ money.format(stats.total) }}</p>
                    <p v-if="change" class="mt-2 flex items-center gap-1 text-xs font-semibold" :class="change.tone">
                        <component :is="change.icon" class="size-3.5" />
                        {{ change.text }}
                        <span :class="[MUTED, 'font-medium']">
                            {{ __('vs :period', { period: stats.previous_label }) }}
                        </span>
                    </p>
                    <p v-else :class="[MUTED, 'mt-2 text-xs font-medium']">
                        {{ __('No earlier period to compare.') }}
                    </p>
                </div>

                <div :class="[CARD, 'anim p-6']" style="--d: 100ms">
                    <p :class="EYEBROW">{{ __('Daily average') }}</p>
                    <p :class="[FIGURE, 'mt-2 text-3xl']">
                        {{ money.format(stats.daily_average) }}
                    </p>
                    <p :class="[MUTED, 'mt-2 text-xs font-medium']">
                        {{ __('Over elapsed days only.') }}
                    </p>
                </div>

                <div :class="[CARD, 'anim p-6']" style="--d: 140ms">
                    <p :class="EYEBROW">{{ __('Transactions') }}</p>
                    <p :class="[FIGURE, 'mt-2 text-3xl']">{{ stats.count }}</p>
                </div>

                <div :class="[CARD, 'anim p-6']" style="--d: 180ms">
                    <p :class="EYEBROW">{{ __('Top category') }}</p>
                    <p v-if="breakdown.length" class="mt-2 flex items-center gap-2">
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-full ring-1 ring-inset"
                            :class="categoryColor(breakdown[0].color).badge"
                        >
                            <component
                                :is="categoryIcon(breakdown[0].icon)"
                                v-if="categoryIcon(breakdown[0].icon)"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-base font-bold">
                                {{ breakdown[0].name }}
                            </span>
                            <span :class="[MUTED, 'block text-xs font-medium tabular-nums']">
                                {{ money.format(breakdown[0].total) }} · {{ breakdown[0].share }}%
                            </span>
                        </span>
                    </p>
                    <p v-else :class="[MUTED, 'mt-2 text-sm']">—</p>
                </div>
            </div>

            <!-- Chart: its own controls are hidden, the page header drives it -->
            <div :class="[CARD, 'anim p-6 sm:p-7']" style="--d: 220ms">
                <SpendingTrendChart
                    :trend="trend"
                    route-name="reports.index"
                    :reload-keys="RELOAD_KEYS"
                    :show-controls="false"
                />
            </div>

            <!-- Where it went, in full -->
            <div :class="[CARD, 'anim overflow-hidden']" style="--d: 260ms">
                <div class="px-6 pb-4 pt-6 sm:px-7">
                    <h2 class="text-base font-bold tracking-tight">{{ __('By category') }}</h2>
                </div>

                <p v-if="isEmpty" :class="[MUTED, 'px-6 pb-10 pt-4 text-center text-sm']">
                    {{ __('Nothing logged in this period.') }}
                </p>

                <!-- Its own scroller: the card clips overflow to keep its rounded
                     corners, which silently cropped the Amount column on a phone. -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                    <thead>
                        <tr :class="[MUTED, 'border-y border-neutral-100 text-xs dark:border-neutral-800']">
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold sm:px-7">
                                {{ __('Category') }}
                            </th>
                            <th scope="col" class="hidden px-3 py-2.5 text-end font-semibold sm:table-cell">
                                {{ __('Transactions') }}
                            </th>
                            <th scope="col" class="hidden px-3 py-2.5 text-end font-semibold sm:table-cell">
                                {{ __('Average') }}
                            </th>
                            <th scope="col" class="hidden px-3 py-2.5 text-end font-semibold sm:table-cell">{{ __('Share') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold sm:px-7">
                                {{ __('Amount') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        <tr v-for="row in breakdown" :key="row.uuid">
                            <th scope="row" class="px-4 py-3 text-start font-medium sm:px-7">
                                <span class="flex min-w-0 items-center gap-2.5">
                                    <span
                                        class="grid size-7 shrink-0 place-items-center rounded-full ring-1 ring-inset"
                                        :class="categoryColor(row.color).badge"
                                    >
                                        <component
                                            :is="categoryIcon(row.icon)"
                                            v-if="categoryIcon(row.icon)"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span
                                            v-else
                                            class="size-1.5 rounded-full"
                                            :class="categoryColor(row.color).dot"
                                        />
                                    </span>
                                    <span class="truncate text-sm">{{ row.name }}</span>
                                </span>
                            </th>
                            <td :class="[MUTED, 'hidden px-3 py-3 text-end text-sm tabular-nums sm:table-cell']">
                                {{ row.count }}
                            </td>
                            <td :class="[MUTED, 'hidden px-3 py-3 text-end text-sm tabular-nums sm:table-cell']">
                                {{ money.format(row.average) }}
                            </td>
                            <td class="hidden px-3 py-3 text-end sm:table-cell">
                                <span class="flex items-center justify-end gap-2">
                                    <!-- A bar beside the number: the eye compares lengths
                                         faster than it compares decimals. -->
                                    <span
                                        class="hidden h-1.5 w-16 overflow-hidden rounded-full bg-neutral-100 sm:block dark:bg-neutral-800"
                                    >
                                        <span
                                            class="block h-full rounded-full"
                                            :class="categoryColor(row.color).bar"
                                            :style="{ width: `${row.share}%` }"
                                        />
                                    </span>
                                    <span :class="[MUTED, 'w-10 text-end text-xs font-medium tabular-nums']">
                                        {{ row.share }}%
                                    </span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end text-sm font-semibold tabular-nums sm:px-7">
                                {{ money.format(row.total) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-neutral-200 dark:border-neutral-700">
                            <th scope="row" class="px-4 py-3 text-start text-sm font-bold sm:px-7">
                                {{ __('Total spent') }}
                            </th>
                            <td :class="[MUTED, 'hidden px-3 py-3 text-end text-sm tabular-nums sm:table-cell']">
                                {{ stats.count }}
                            </td>
                            <td class="hidden sm:table-cell" />
                            <td class="hidden sm:table-cell" />
                            <td class="px-4 py-3 text-end text-sm font-bold tabular-nums sm:px-7">
                                {{ money.format(stats.total) }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
