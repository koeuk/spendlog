<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import SpendingTrendChart from '@/Components/SpendingTrendChart.vue';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { CARD, CARD_BRAND, CARD_TINT, EYEBROW, EYEBROW_ON_BRAND, FIGURE, FIGURE_ON_BRAND, MUTED, MUTED_ON_BRAND, PILL_ACTION } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { ArrowRight, Lightbulb, Plus, TriangleAlert } from 'lucide-vue-next';

const props = defineProps({
    today: { type: Object, required: true },
    summary: { type: Object, required: true },
    breakdown: { type: Array, required: true },
    // 'YYYY-MM' — which month the breakdown card is showing, plus the options
    // its two selects offer.
    breakdown_month: { type: String, required: true },
    breakdown_months: { type: Array, default: () => [] },
    breakdown_years: { type: Array, default: () => [] },
    // The Budgets card's own rows, for its own month. Separate from `summary`
    // so picking a month here cannot move the hero above.
    budgets: { type: Array, default: () => [] },
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

const breakdownLoading = ref(false);
const budgetLoading = ref(false);

/*
 * This page has three independent controls — the trend period, the breakdown
 * month and the budget month — and router.get replaces the whole query string.
 * So every reload has to resend the other two, or changing one would drop the
 * others' params. Each control reloads only its own props, which hides that:
 * the other cards keep their contents and only spring back to their defaults on
 * the next full page load.
 */
const chartBaseQuery = computed(() => ({
    breakdown_month: props.breakdown_month,
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

// Both selects rebuild the same 'YYYY-MM', so one loader serves each card.
function loadBreakdownMonth(month) {
    reload(
        { breakdown_month: month },
        ['breakdown', 'breakdown_month', 'breakdown_years'],
        breakdownLoading,
    );
}

// Reloads `budgets`, never `summary` — the hero above is anchored to the real
// current month and must not follow this picker.
function loadBudgetMonth(month) {
    reload(
        { budget_month: month },
        ['budgets', 'budget_month', 'budget_years'],
        budgetLoading,
    );
}

const breakdownMonthPart = computed(() => props.breakdown_month.split('-')[1]);
const breakdownYearPart = computed(() => props.breakdown_month.split('-')[0]);

const budgetMonthPart = computed(() => props.budget_month.split('-')[1]);
const budgetYearPart = computed(() => props.budget_month.split('-')[0]);

// SearchableSelect wants {value, label} with string values; years arrive as ints.
const budgetMonthOptions = computed(() => props.budget_months);
const budgetYearOptions = computed(() =>
    props.budget_years.map((year) => ({ value: String(year), label: String(year) })),
);

const breakdownMonthOptions = computed(() => props.breakdown_months);
const breakdownYearOptions = computed(() =>
    props.breakdown_years.map((year) => ({ value: String(year), label: String(year) })),
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

// Names the month that came up empty, so "nothing here" does not read as "you
// have never logged anything" — especially when the picker is on a past month.
const breakdownEmptyText = computed(() =>
    trans('Nothing logged in :month.', { month: formatMonth(props.breakdown_month) }),
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
// From `budgets` (the card's own month), not `summary` (the hero's current
// month) — reading it off summary is what let this picker move the hero.
const budgeted = computed(() =>
    props.budgets.filter((category) => category.budget !== null),
);

const statusText = {
    over: 'text-red-600 dark:text-red-400',
    warning: 'text-amber-600 dark:text-amber-400',
    ok: 'text-[#4b9d5f] dark:text-[#6cc182]',
    none: 'text-neutral-400',
};

/**
 * How far through the month you are, against how far through the budget.
 *
 * "14% spent" says nothing on its own — 14% is excellent on the 28th and
 * alarming on the 2nd. The percentage was already on the card; what was missing
 * was the only thing that makes it mean anything.
 *
 * Derived here rather than served: every input is already on the page, so this
 * costs one date calculation instead of another field on every dashboard
 * response.
 *
 * Null without a budget: pace is measured against something, and there is
 * nothing to measure against until one is set.
 *
 * No month check is needed. The hero is built from
 * `BudgetSummary::forMonth($user, $currentMonth)` and the two month pickers
 * further down deliberately feed `budgets`/`breakdown` instead, precisely so
 * they cannot move it — so this is always the running month.
 */
const pace = computed(() => {
    if (overall.value.budget === null) {
        return null;
    }

    const [year, month] = props.current_month.split('-').map(Number);
    // Day 0 of the next month is the last day of this one.
    const daysInMonth = new Date(year, month, 0).getDate();
    const day = Math.min(Math.max(Number(props.today.date.slice(8, 10)), 1), daysInMonth);

    // What an even spend would have reached by the end of today.
    const expected = (overall.value.budget * day) / daysInMonth;
    const delta = expected - overall.value.spent;

    return {
        day,
        daysInMonth,
        markerPercent: (day / daysInMonth) * 100,
        // A day's worth of the budget, which is what the Today card compares to.
        daily: overall.value.budget / daysInMonth,
        delta: Math.abs(delta),
        under: delta >= 0,
    };
});
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
                :class="[CARD_BRAND, 'anim space-y-3 p-6 sm:p-7']"
                style="--d: 40ms"
            >
                <!-- Everything here inherits --primary-foreground from
                     CARD_BRAND. Spelling a colour out would pin it to today's
                     fill, and the admin can change that fill to anything. The
                     two lines separate on weight and opacity instead: the
                     warning at full strength, the advice stepped back to 80%,
                     which is the same "this one recedes" MUTED does on a card. -->
                <div v-if="guidance.warning" class="flex items-start gap-3">
                    <TriangleAlert class="mt-0.5 size-5 shrink-0" />
                    <p class="text-sm font-medium leading-relaxed">
                        {{ guidance.warning }}
                    </p>
                </div>
                <div v-if="guidance.advice" class="flex items-start gap-3 opacity-80">
                    <Lightbulb class="mt-0.5 size-5 shrink-0" />
                    <p class="text-sm leading-relaxed">
                        {{ guidance.advice }}
                    </p>
                </div>
            </div>

            <!-- Hero: the month, and how much room is left in it -->
            <div class="grid gap-3 lg:grid-cols-3">
                <div :class="[CARD_TINT, 'anim p-6 sm:p-8 lg:col-span-2']" style="--d: 60ms">
                    <p :class="EYEBROW_ON_BRAND">{{ __('This month') }}</p>

                    <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span :class="[FIGURE_ON_BRAND, 'text-[2.6rem] leading-none sm:text-5xl']">
                            {{ money.format(overall.spent) }}
                        </span>
                        <span
                            v-if="overall.budget !== null"
                            class="text-sm font-medium text-primary-foreground/75"
                        >
                            {{ __('of :amount', { amount: money.format(overall.budget) }) }}
                        </span>
                    </div>

                    <div v-if="overall.budget !== null" class="mt-6">
                        <BudgetProgress
                            :status="overall.status"
                            :bar-percent="overall.bar_percent"
                            :marker="pace?.markerPercent ?? null"
                            size="lg"
                            animate
                        />

                        <!--
                            What is left leads, and the percentage follows it.

                            These were both text-xs before, which put the number
                            you act on — how much room is left — at the same
                            weight as the one you only glance at, and below the
                            weight of the passive "of $800.00" above. The
                            hierarchy now matches what the card is for.
                        -->
                        <div class="mt-3 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <!--
                                Not statusText here, unlike the per-category rows
                                below. This sits on the brand fill, and the status
                                palette cannot be trusted against a colour an
                                admin picked: "ok" green on a green card is
                                barely there, and an admin who picks red would
                                lose "over budget" entirely. The bar above keeps
                                its status colour — it has its own light track,
                                so it reads on any fill — and the words say the
                                same thing in text.
                            -->
                            <span class="text-base font-bold text-primary-foreground">
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
                            <span :class="[MUTED_ON_BRAND, 'text-xs font-semibold tabular-nums']">
                                {{ overall.percent }}%
                            </span>
                        </div>

                        <!-- The tick above, said in words. Without this the
                             marker is a line on a bar that nobody can read. -->
                        <p v-if="pace" :class="[MUTED_ON_BRAND, 'mt-1.5 text-xs font-medium']">
                            {{ __('Day :day of :days', { day: pace.day, days: pace.daysInMonth }) }}
                            <span aria-hidden="true" class="px-1">·</span>
                            {{
                                pace.under
                                    ? __(':amount under an even pace', {
                                          amount: money.format(pace.delta),
                                      })
                                    : __(':amount ahead of an even pace', {
                                          amount: money.format(pace.delta),
                                      })
                            }}
                        </p>
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

                <!--
                    justify-between is gone on purpose. It spread three items
                    across a box whose height comes from the taller card beside
                    it, so on a phone — where that card is the tall one directly
                    above — this became a short stack with a hole in the middle.
                    Explicit spacing now, and the button is pushed to the foot
                    only from lg, which is the breakpoint where the two cards
                    actually share a row and want a common baseline.
                -->
                <div :class="[CARD, 'anim flex flex-col p-6 sm:p-8']" style="--d: 120ms">
                    <p :class="EYEBROW">{{ __('Today') }}</p>
                    <span :class="[FIGURE, 'mt-2 text-[2.6rem] leading-none']">
                        {{ money.format(today.total) }}
                    </span>

                    <!-- Ties this card to the meter beside it: the same budget,
                         divided into the day it is actually spent in. -->
                    <p v-if="pace" :class="[MUTED, 'mt-1.5 text-xs font-medium']">
                        {{ __('of a :amount daily pace', { amount: money.format(pace.daily) }) }}
                    </p>

                    <!--
                        A button, not an underlined link. This is the one thing
                        the dashboard exists to get you to do, and it was styled
                        exactly like the "Set a budget" fallback next to it.
                        bg-primary so it follows the admin's brand colour, as the
                        lettermark does.
                    -->
                    <Link
                        :href="route('expenses.index')"
                        :class="[
                            PILL_ACTION,
                            'mt-6 inline-flex items-center justify-center gap-1.5 bg-primary text-primary-foreground transition hover:opacity-90 lg:mt-auto',
                        ]"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        {{ __('Add an expense') }}
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
                    <!-- Wraps, because the two pickers below cannot. Their
                         triggers are whitespace-nowrap and sized by their content
                         ("November", "2026"), so on one line the row's min-content
                         exceeded the card's 248px inner width at a 320px viewport
                         and pushed the whole page into a horizontal scroll. Given
                         a second line it simply stacks: heading, then pickers. -->
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                        <h2 class="min-w-0 text-base font-bold tracking-tight">
                            {{ __('Where it went') }}
                        </h2>

                        <!-- The same two selects the Budgets card uses, so both
                             cards are filtered the same way. -->
                        <div class="flex items-center gap-1">
                            <SearchableSelect
                                :options="breakdownMonthOptions"
                                :model-value="breakdownMonthPart"
                                :label="__('Month')"
                                :search-placeholder="__('Search month')"
                                :empty-text="__('No month found.')"
                                :trigger-class="BUDGET_PICKER"
                                content-class="w-44"
                                @update:model-value="loadBreakdownMonth(`${breakdownYearPart}-${$event}`)"
                            />

                            <SearchableSelect
                                :options="breakdownYearOptions"
                                :model-value="breakdownYearPart"
                                :label="__('Year')"
                                :search-placeholder="__('Search year')"
                                :empty-text="__('No year found.')"
                                :trigger-class="[BUDGET_PICKER, 'tabular-nums']"
                                content-class="w-32"
                                @update:model-value="loadBreakdownMonth(`${$event}-${breakdownMonthPart}`)"
                            />
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
                    <!-- Wraps for the same reason as the card above; the pickers
                         here are the same pair. -->
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                        <h2 class="min-w-0 text-base font-bold tracking-tight">{{ __('Budgets') }}</h2>

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
