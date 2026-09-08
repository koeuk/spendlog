<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FloatingAddButton from '@/Components/FloatingAddButton.vue';
import { CARD, CARD_LIFT, EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import { categoryColor } from '@/lib/categoryStyles';
import { trans } from '@/lib/i18n';
import { Button } from '@/Components/ui/button';

/**
 * Every goal this person is saving towards, with the totals across them.
 *
 * Not a list of rows like Expenses: a goal is read for how far along it is,
 * so each one is a card with its own bar, and the page opens on the sum of
 * them — the figure the Dashboard's savings card also shows.
 */
const props = defineProps({
    goals: { type: Array, required: true },
    // SavingsSummary::forMonth for the current month.
    totals: { type: Object, required: true },
    can: { type: Object, default: () => ({ create: false }) },
});

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

const riel = (usd) => formatRiel(usd, khrPerUsd.value);

const dateFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

function formatDate(date) {
    const [year, month, day] = date.split('-').map(Number);

    return dateFormatter.format(new Date(year, month - 1, day));
}

const isEmpty = computed(() => props.goals.length === 0);
</script>

<template>
    <Head :title="trans('Savings')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                    {{ __('Savings') }}
                </h2>

                <Button
                    v-if="can.create"
                    :as="Link"
                    :href="route('savings.create')"
                    size="sm"
                    class="max-sm:hidden"
                >
                    {{ __('Add goal') }}
                </Button>
            </div>
        </template>

        <div class="space-y-4 pb-8 pt-2">
            <!-- The sum across every goal. Drawn like the overall row on the
                 Budgets page, so the two "how am I doing" figures read alike. -->
            <div :class="[CARD, 'p-5']">
                <p :class="EYEBROW">{{ __('Saved so far') }}</p>
                <div class="mt-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <span>
                        <span :class="FIGURE">{{ money.format(totals.total_saved) }}</span>
                        <span class="ms-2 text-sm" :class="MUTED">
                            {{ __('of :amount target', { amount: money.format(totals.total_target) }) }}
                        </span>
                    </span>
                    <span class="text-sm font-medium text-gray-600 dark:text-neutral-300">
                        {{ totals.percent }}%
                    </span>
                </div>
                <div
                    class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
                    role="progressbar"
                    :aria-valuenow="totals.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full rounded-full bg-green-500" :style="{ width: `${totals.percent}%` }" />
                </div>
                <p class="mt-2 text-xs" :class="MUTED">
                    {{ __(':count goals', { count: totals.goals_count }) }}
                    · {{ __('Saved this month') }}: {{ money.format(totals.saved_this_month) }}
                    <span class="ms-1">{{ riel(totals.saved_this_month) }}</span>
                </p>
            </div>

            <div v-if="isEmpty" :class="[CARD, 'p-10 text-center']">
                <p class="text-sm text-gray-600 dark:text-neutral-400">
                    {{ __('No savings goals yet, start your first one.') }}
                </p>
                <Button
                    v-if="can.create"
                    :as="Link"
                    :href="route('savings.create')"
                    class="mt-4"
                    size="sm"
                >
                    {{ __('Add goal') }}
                </Button>
            </div>

            <!-- One card per goal, the whole card a link to its ledger. -->
            <div v-else class="grid gap-3 sm:grid-cols-2">
                <Link
                    v-for="goal in goals"
                    :key="goal.uuid"
                    :href="route('savings.show', goal.uuid)"
                    :class="[CARD, CARD_LIFT, 'block p-5']"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :class="categoryColor(goal.color).dot"
                            />
                            <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-neutral-100">
                                {{ goal.name }}
                            </h3>
                        </div>
                        <span
                            v-if="goal.reached"
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="categoryColor('green').badge"
                        >
                            {{ __('Reached') }}
                        </span>
                        <span v-else class="shrink-0 text-xs font-medium" :class="MUTED">
                            {{ goal.percent }}%
                        </span>
                    </div>

                    <p class="mt-3 text-lg font-bold tabular-nums text-gray-900 dark:text-neutral-100">
                        {{ money.format(goal.saved) }}
                        <span class="text-sm font-normal" :class="MUTED">
                            {{ __('of :amount', { amount: money.format(goal.target_amount) }) }}
                        </span>
                    </p>

                    <div
                        class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
                        role="progressbar"
                        :aria-valuenow="goal.percent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="categoryColor(goal.color).bar"
                            :style="{ width: `${goal.percent}%` }"
                        />
                    </div>

                    <p class="mt-2 flex flex-wrap justify-between gap-x-3 text-xs" :class="MUTED">
                        <span v-if="!goal.reached">
                            {{ __(':amount to go', { amount: money.format(goal.remaining) }) }}
                        </span>
                        <span v-else>{{ riel(goal.saved) }}</span>
                        <span v-if="goal.deadline">
                            {{ __('by :date', { date: formatDate(goal.deadline) }) }}
                        </span>
                    </p>
                </Link>
            </div>
        </div>

        <FloatingAddButton
            v-if="can.create"
            :href="route('savings.create')"
            :label="__('Add goal')"
        />
    </AuthenticatedLayout>
</template>
