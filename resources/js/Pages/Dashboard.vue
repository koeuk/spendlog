<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import CategoryBadge from '@/Components/CategoryBadge.vue';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';

const props = defineProps({
    today: { type: Object, required: true },
    summary: { type: Object, required: true },
    breakdown: { type: Array, required: true },
    recent: { type: Array, required: true },
});

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

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

// Only categories the user actually budgeted — an unset budget has nothing to track.
const budgeted = computed(() =>
    props.summary.categories.filter((category) => category.budget !== null),
);

const overall = computed(() => props.summary.overall);

const statusText = {
    over: 'text-red-600',
    warning: 'text-amber-600',
    ok: 'text-gray-500',
    none: 'text-gray-400',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
                <span class="text-sm text-gray-500">{{ formatMonth(summary.month) }}</span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- Totals -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Today
                        </p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">
                            {{ money.format(today.total) }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            This month
                        </p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">
                            {{ money.format(overall.spent) }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            {{ overall.remaining !== null && overall.remaining < 0 ? 'Over budget' : 'Left to spend' }}
                        </p>
                        <p
                            v-if="overall.remaining !== null"
                            class="mt-1 text-2xl font-semibold tabular-nums"
                            :class="overall.remaining < 0 ? 'text-red-600' : 'text-gray-900'"
                        >
                            {{ money.format(Math.abs(overall.remaining)) }}
                        </p>
                        <p v-else class="mt-1 text-sm text-gray-400">
                            <Link :href="route('budgets.index')" class="underline underline-offset-2 hover:text-gray-600">
                                Set a budget
                            </Link>
                        </p>
                    </div>
                </div>

                <!-- Overall budget -->
                <div v-if="overall.budget !== null" class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="mb-1.5 flex items-baseline justify-between text-sm">
                        <span class="font-medium text-gray-900">
                            Overall budget
                            <span class="font-normal text-gray-500">
                                — {{ money.format(overall.spent) }} of {{ money.format(overall.budget) }}
                            </span>
                        </span>
                        <span class="text-xs font-medium" :class="statusText[overall.status]">
                            {{ overall.percent }}%
                        </span>
                    </div>
                    <BudgetProgress :status="overall.status" :bar-percent="overall.bar_percent" />
                </div>

                <!-- Spending by category -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">Where it went</h3>
                    </div>

                    <p v-if="!breakdown.length" class="px-5 py-10 text-center text-sm text-gray-500">
                        Nothing logged this month yet.
                        <Link :href="route('expenses.index')" class="underline underline-offset-2">
                            Add an expense
                        </Link>
                    </p>

                    <ul v-else class="space-y-4 px-5 py-4">
                        <li v-for="row in breakdown" :key="row.uuid">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <component
                                        :is="categoryIcon(row.icon)"
                                        v-if="categoryIcon(row.icon)"
                                        class="size-3.5 shrink-0 text-gray-400"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate text-gray-900">{{ row.name }}</span>
                                </span>
                                <span class="shrink-0 tabular-nums text-gray-900">
                                    {{ money.format(row.spent) }}
                                    <span class="ml-1 text-xs text-gray-400">{{ row.share }}%</span>
                                </span>
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-gray-100"
                                role="img"
                                :aria-label="`${row.name}: ${row.share}% of this month's spending`"
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

                <!-- Budget progress -->
                <div v-if="budgeted.length" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">Budgets</h3>
                        <Link
                            :href="route('budgets.index')"
                            class="text-xs text-gray-500 underline-offset-2 hover:underline"
                        >
                            Manage
                        </Link>
                    </div>

                    <ul class="divide-y divide-gray-100">
                        <li v-for="category in budgeted" :key="category.uuid" class="px-5 py-4">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate text-gray-900">{{ category.name }}</span>
                                <span class="shrink-0 tabular-nums text-gray-500">
                                    {{ money.format(category.spent) }} of
                                    {{ money.format(category.budget) }}
                                    <span class="ml-1 text-xs font-medium" :class="statusText[category.status]">
                                        {{ category.percent }}%
                                    </span>
                                </span>
                            </div>
                            <BudgetProgress
                                :status="category.status"
                                :bar-percent="category.bar_percent"
                            />
                        </li>
                    </ul>
                </div>

                <!-- Recent -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">Recent</h3>
                        <Link
                            :href="route('expenses.index')"
                            class="text-xs text-gray-500 underline-offset-2 hover:underline"
                        >
                            View all
                        </Link>
                    </div>

                    <p v-if="!recent.length" class="px-5 py-10 text-center text-sm text-gray-500">
                        No expenses yet — add your first one.
                    </p>

                    <ul v-else class="divide-y divide-gray-100">
                        <li
                            v-for="expense in recent"
                            :key="expense.uuid"
                            class="flex items-center justify-between gap-3 px-5 py-3"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <CategoryBadge
                                    :name="expense.category"
                                    :color="expense.color"
                                    :icon="expense.icon"
                                />
                                <span class="truncate text-sm text-gray-900">{{ expense.item }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="text-xs text-gray-400">{{ formatDay(expense.spent_on) }}</span>
                                <span class="text-sm font-medium tabular-nums text-gray-900">
                                    {{ money.format(expense.price) }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
