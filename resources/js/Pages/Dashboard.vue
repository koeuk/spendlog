<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import SpendingTrendChart from '@/Components/SpendingTrendChart.vue';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { ACTIVE, CARD, CARD_TINT, EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { ArrowRight, Lightbulb, TriangleAlert } from 'lucide-vue-next';

const props = defineProps({
    today: { type: Object, required: true },
    summary: { type: Object, required: true },
    breakdown: { type: Array, required: true },
    // 'week' | 'month' | 'year' — which span the breakdown card is showing.
    breakdown_period: { type: String, default: 'month' },
    // 'YYYY-MM' — which month's budgets the Budgets card is showing, plus the
    // options its two selects offer.
    budget_month: { type: String, required: true },
    budget_months: { type: Array, default: () => [] },
    budget_years: { type: Array, default: () => [] },
    // The real current month, for the page heading — see the controller.
    current_month: { type: String, required: true },
    trend: { type: Object, required: true },
    recent: { type: Array, required: true },
    // { warning, advice } resolved to the active locale, or null when the admin
    // has the feature off or blank.
    guidance: { type: Object, default: null },
});

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const BREAKDOWN_PERIODS = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
    { key: 'year', label: 'Year' },
];

const breakdownLoading = ref(false);
const budgetLoading = ref(false);

/*
 * This page has three independent controls — the trend period, the breakdown
 * period and the budget month — and router.get replaces the whole query string.
 * So every reload has to resend the other two, or changing one would drop the
 * others' params. Each control reloads only its own props, which hides that:
 * the other cards keep their contents and only spring back to their defaults on
 * the next full page load.
 */
const chartBaseQuery = computed(() => ({
    breakdown: props.breakdown_period,
    budget_month: props.budget_month,
}));

function reload(changes, only, flag) {
    router.get(
        route('dashboard'),
        {
            ...chartBaseQuery.value,
            trend: props.trend.granularity,
            ...(props.trend.anchor ? { at: props.trend.anchor } : {}),
            ...changes,
        },
        {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (flag.value = true),
            onFinish: () => (flag.value = false),
        },
    );
}

function loadBreakdown(period) {
    reload({ breakdown: period }, ['breakdown', 'breakdown_period'], breakdownLoading);
}

// Both selects rebuild the same 'YYYY-MM', so one loader serves them.
function loadBudgetMonth(month) {
    reload(
        { budget_month: month },
        ['summary', 'budget_month', 'budget_years'],
        budgetLoading,
    );
}

const budgetMonthPart = computed(() => props.budget_month.split('-')[1]);
const budgetYearPart = computed(() => props.budget_month.split('-')[0]);

// SearchableSelect wants {value, label} with string values; years arrive as ints.
const budgetMonthOptions = computed(() => props.budget_months);
const budgetYearOptions = computed(() =>
    props.budget_years.map((year) => ({ value: String(year), label: String(year) })),
);

// Both pickers wear the same quiet skin — SearchableSelect renders no element of
// its own, so the trigger takes its class by prop.
//
// The edge is drawn at rest, not conjured on hover: these are controls, and one
// that only looks clickable once the pointer is already on it has to be found
// before it can be used.
const BUDGET_PICKER =
    'h-7 rounded-full border border-border bg-card/70 px-2.5 text-xs font-semibold ' +
    'transition-colors duration-200 hover:bg-muted';

// The empty state names the span that came up empty, so "nothing here" does not
// read as "you have never logged anything".
const breakdownEmptyText = computed(
    () =>
        ({
            week: trans('Nothing logged this week yet.'),
            month: trans('Nothing logged this month yet.'),
            year: trans('Nothing logged this year yet.'),
        })[props.breakdown_period] ?? trans('Nothing logged yet.'),
);

function formatMonth(month) {
    const [year, m] = month.split('-').map(Number);
    return new Date(year, m - 1, 1).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    });
}

function formatDay(date) {
    const [year, m, d] = date.split('-').map(Number);
    return new Date(year, m - 1, d).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

const overall = computed(() => props.summary.overall);

// Only categories the user actually budgeted — an unset budget has nothing to track.
const budgeted = computed(() =>
    props.summary.categories.filter((category) => category.budget !== null),
);

const statusText = {
    over: 'text-red-600 dark:text-red-400',
    warning: 'text-amber-600 dark:text-amber-400',
    ok: 'text-[#4b9d5f] dark:text-[#6cc182]',
    none: 'text-neutral-400',
};
</script>

<template>
    <Head :title="__('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p :class="EYEBROW">{{ formatMonth(current_month) }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                    {{ __('Dashboard') }}
                </h1>
            </div>
        </template>

        <div class="space-y-3">
            <!-- Admin-authored guidance. Each line only renders if its text is
                 set, so an admin can show just one of the two. -->
            <div
                v-if="guidance"
                :class="[CARD, 'anim space-y-3 p-6 sm:p-7']"
                style="--d: 40ms"
            >
                <div v-if="guidance.warning" class="flex items-start gap-3">
                    <TriangleAlert class="mt-0.5 size-5 shrink-0 text-amber-500 dark:text-amber-400" />
                    <p class="text-sm leading-relaxed text-amber-900 dark:text-amber-200">
                        {{ guidance.warning }}
                    </p>
                </div>
                <div v-if="guidance.advice" class="flex items-start gap-3">
                    <Lightbulb class="mt-0.5 size-5 shrink-0 text-emerald-500 dark:text-emerald-400" />
                    <p class="text-sm leading-relaxed" :class="MUTED">
                        {{ guidance.advice }}
                    </p>
                </div>
            </div>

            <!-- Hero: the month, and how much room is left in it -->
            <div class="grid gap-3 lg:grid-cols-3">
                <div :class="[CARD_TINT, 'anim p-6 sm:p-8 lg:col-span-2']" style="--d: 60ms">
                    <p :class="EYEBROW">{{ __('This month') }}</p>

                    <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span :class="[FIGURE, 'text-[2.6rem] leading-none sm:text-5xl']">
                            {{ money.format(overall.spent) }}
                        </span>
                        <span
                            v-if="overall.budget !== null"
                            class="text-sm font-medium text-neutral-500 dark:text-neutral-400"
                        >
                            {{ __('of :amount', { amount: money.format(overall.budget) }) }}
                        </span>
                    </div>

                    <div v-if="overall.budget !== null" class="mt-6">
                        <BudgetProgress
                            :status="overall.status"
                            :bar-percent="overall.bar_percent"
                        />
                        <div class="mt-2.5 flex items-center justify-between text-xs font-semibold">
                            <span :class="statusText[overall.status]">
                                {{
                                    overall.remaining < 0
                                        ? __(':amount over budget', {
                                              amount: money.format(Math.abs(overall.remaining)),
                                          })
                                        : __(':amount left', {
                                              amount: money.format(overall.remaining),
                                          })
                                }}
                            </span>
                            <span :class="statusText[overall.status]">{{ overall.percent }}%</span>
                        </div>
                    </div>

                    <Link
                        v-else
                        :href="route('budgets.index')"
                        class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-[#4b9d5f] underline-offset-4 hover:underline dark:text-[#6cc182]"
                    >
                        {{ __('Set a budget') }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div
                    :class="[CARD, 'anim flex flex-col justify-between p-6 sm:p-8']"
                    style="--d: 120ms"
                >
                    <p :class="EYEBROW">{{ __('Today') }}</p>
                    <span :class="[FIGURE, 'mt-2 text-[2.6rem] leading-none']">
                        {{ money.format(today.total) }}
                    </span>
                    <Link
                        :href="route('expenses.index')"
                        class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-900 underline-offset-4 hover:underline dark:text-neutral-100"
                    >
                        {{ __('Add an expense') }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>

            <!-- Trend: how this period is tracking, at three zoom levels -->
            <div :class="[CARD, 'anim p-6 sm:p-7']" style="--d: 150ms">
                <!-- Carries the other controls' params through, so changing the
                     chart period does not reset the cards below it. -->
                <SpendingTrendChart :trend="trend" :base-query="chartBaseQuery" />
            </div>

            <!-- Breakdown + budgets -->
            <div class="grid gap-3 lg:grid-cols-2">
                <div :class="[CARD, 'anim p-6 sm:p-7']" style="--d: 180ms">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold tracking-tight">
                            {{ __('Where it went') }}
                        </h2>

                        <!-- Same segmented pill the trend chart and Reports use.
                             No "All": this card is about where money is going
                             now, and an all-time split flattens that. -->
                        <div
                            class="inline-flex rounded-full border border-neutral-200 bg-white/70 p-0.5 dark:border-neutral-700 dark:bg-neutral-800/70"
                            role="group"
                            :aria-label="__('Period')"
                        >
                            <button
                                v-for="option in BREAKDOWN_PERIODS"
                                :key="option.key"
                                type="button"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold transition-colors duration-200"
                                :class="
                                    breakdown_period === option.key
                                        ? ACTIVE
                                        : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'
                                "
                                :aria-pressed="breakdown_period === option.key"
                                @click="loadBreakdown(option.key)"
                            >
                                {{ __(option.label) }}
                            </button>
                        </div>
                    </div>

                    <p v-if="!breakdown.length" :class="[MUTED, 'py-10 text-center text-sm']">
                        {{ breakdownEmptyText }}
                    </p>

                    <ul
                        v-else
                        class="mt-5 space-y-4 transition-opacity duration-200"
                        :class="breakdownLoading && 'opacity-50'"
                    >
                        <li v-for="row in breakdown" :key="row.uuid">
                            <div class="mb-2 flex items-baseline justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <component
                                        :is="categoryIcon(row.icon)"
                                        v-if="categoryIcon(row.icon)"
                                        class="size-4 shrink-0 text-neutral-400"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate font-medium">{{ row.name }}</span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums">
                                    {{ money.format(row.spent) }}
                                    <span :class="[MUTED, 'ms-1 text-xs font-medium']">
                                        {{ row.share }}%
                                    </span>
                                </span>
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800"
                                role="img"
                                :aria-label="`${row.name}: ${row.share}%`"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="categoryColor(row.color).bar"
                                    :style="{ width: `${row.share}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                </div>

                <div :class="[CARD, 'anim p-6 sm:p-7']" style="--d: 240ms">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold tracking-tight">{{ __('Budgets') }}</h2>

                        <div class="flex items-center gap-1">
                            <!-- Month and year, not a week/month/year span: a
                                 budget IS a monthly amount, so the only question
                                 is which month. Same two selects as the Budgets
                                 page, fed from the same server-built lists. -->
                            <SearchableSelect
                                :options="budgetMonthOptions"
                                :model-value="budgetMonthPart"
                                :label="__('Month')"
                                :search-placeholder="__('Search month')"
                                :empty-text="__('No month found.')"
                                :trigger-class="BUDGET_PICKER"
                                content-class="w-44"
                                @update:model-value="loadBudgetMonth(`${budgetYearPart}-${$event}`)"
                            />

                            <SearchableSelect
                                :options="budgetYearOptions"
                                :model-value="budgetYearPart"
                                :label="__('Year')"
                                :search-placeholder="__('Search year')"
                                :empty-text="__('No year found.')"
                                :trigger-class="[BUDGET_PICKER, 'tabular-nums']"
                                content-class="w-32"
                                @update:model-value="loadBudgetMonth(`${$event}-${budgetMonthPart}`)"
                            />

                            <Link
                                :href="route('budgets.index', { month: budget_month })"
                                :class="[MUTED, 'ms-1 text-xs font-semibold underline-offset-4 hover:underline']"
                            >
                                {{ __('Manage') }}
                            </Link>
                        </div>
                    </div>

                    <p
                        v-if="!budgeted.length"
                        :class="[MUTED, 'py-10 text-center text-sm']"
                    >
                        {{ __('No budget set') }}
                    </p>

                    <ul
                        v-else
                        class="mt-5 space-y-4 transition-opacity duration-200"
                        :class="budgetLoading && 'opacity-50'"
                    >
                        <li v-for="row in budgeted" :key="row.uuid">
                            <div class="mb-2 flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate font-medium">{{ row.name }}</span>
                                <span
                                    class="shrink-0 text-xs font-semibold tabular-nums"
                                    :class="statusText[row.status]"
                                >
                                    {{ money.format(row.spent) }}
                                    <span class="font-medium text-neutral-400">
                                        / {{ money.format(row.budget) }}
                                    </span>
                                </span>
                            </div>
                            <BudgetProgress :status="row.status" :bar-percent="row.bar_percent" />
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Recent -->
            <div :class="[CARD, 'anim overflow-hidden']" style="--d: 300ms">
                <div class="flex items-center justify-between gap-3 px-6 pb-4 pt-6 sm:px-7">
                    <h2 class="text-base font-bold tracking-tight">{{ __('Recent') }}</h2>
                    <Link
                        :href="route('expenses.index')"
                        :class="[MUTED, 'text-xs font-semibold underline-offset-4 hover:underline']"
                    >
                        {{ __('View all') }}
                    </Link>
                </div>

                <p v-if="!recent.length" :class="[MUTED, 'px-6 pb-10 pt-4 text-center text-sm']">
                    {{ __('No expenses yet, add your first one.') }}
                </p>

                <ul v-else class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <li
                        v-for="expense in recent"
                        :key="expense.uuid"
                        class="flex items-center gap-3 px-6 py-3.5 sm:px-7"
                    >
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full ring-1 ring-inset"
                            :class="categoryColor(expense.color).badge"
                        >
                            <component
                                :is="categoryIcon(expense.icon)"
                                v-if="categoryIcon(expense.icon)"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                class="size-2 rounded-full"
                                :class="categoryColor(expense.color).dot"
                            />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ expense.item }}</p>
                            <p :class="[MUTED, 'truncate text-xs']">{{ expense.category }}</p>
                        </div>

                        <span :class="[MUTED, 'shrink-0 text-xs font-medium']">
                            {{ formatDay(expense.spent_on) }}
                        </span>
                        <span class="w-20 shrink-0 text-end text-sm font-semibold tabular-nums">
                            {{ money.format(expense.price) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
