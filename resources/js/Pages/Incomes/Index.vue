<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Banknote } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { CARD, MUTED } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import ExpenseListSkeleton from '@/Components/ExpenseListSkeleton.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import DateFilter from '@/Components/DateFilter.vue';
import { useNavigating } from '@/composables/useNavigating';
import { trans } from '@/lib/i18n';
import { categoryColor } from '@/lib/categoryStyles';
import { Button } from '@/Components/ui/button';
import FloatingAddButton from '@/Components/FloatingAddButton.vue';

/**
 * The income list — the Expenses page with the category and scope controls
 * taken away. Income has no shared catalogue (the source is free text) and
 * no Everyone view, so what is left is search and the two date filters.
 */
const props = defineProps({
    days: { type: Array, required: true },
    pagination: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    // '' means "every one" for each; a year alone shows the whole year, a
    // month alone shows that month across every year.
    month: { type: String, default: '' },
    year: { type: String, default: '' },
    // Month labels are built server-side so they follow the app locale.
    months: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
});

const { navigating } = useNavigating();

/*
 * Every control narrows the same list, so each edits the current query rather
 * than replacing it. Empty values are dropped rather than sent blank:
 * ?filter[source]= is a filter for the empty string, not the absence of one.
 */
function navigate({ month, year, ...changes } = {}) {
    const filter = { ...(props.filters?.filter ?? {}), ...changes };
    const query = {};

    const nextMonth = month !== undefined ? month : props.month;
    const nextYear = year !== undefined ? year : props.year;

    if (nextMonth) {
        query.month = nextMonth;
    }

    if (nextYear) {
        query.year = nextYear;
    }

    for (const [key, value] of Object.entries(filter)) {
        if (value !== '' && value !== null && value !== undefined) {
            query.filter = { ...query.filter, [key]: value };
        }
    }

    router.get(route('incomes.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const search = ref(props.filters?.filter?.source ?? '');

// Clearing every filter at once sets this ref alongside the others, and the
// watcher would then fire a second request that merges the filters just
// dropped back in. The reset owns the navigation for that one change.
let resetting = false;

watch(search, (value) => {
    if (resetting) {
        return;
    }

    navigate({ source: value });
});

const monthFilter = ref(props.month ?? '');
const yearFilter = ref(props.year ?? '');

const monthOptions = computed(() => [
    { value: '', label: trans('All months') },
    ...props.months.map((m) => ({ value: m.value, label: m.label })),
]);

const yearOptions = computed(() => [
    { value: '', label: trans('All years') },
    ...props.years.map((y) => ({ value: String(y), label: String(y) })),
]);

function applyMonthFilter(value) {
    monthFilter.value = value;
    navigate({ month: value });
}

function applyYearFilter(value) {
    yearFilter.value = value;
    navigate({ year: value });
}

const hasActiveFilters = computed(
    () => Boolean(search.value) || Boolean(monthFilter.value) || Boolean(yearFilter.value),
);

function clearFilters() {
    resetting = true;

    search.value = '';
    monthFilter.value = '';
    yearFilter.value = '';

    router.get(route('incomes.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            resetting = false;
        },
    });
}

/**
 * Where this list currently is, handed to the create/edit screens so saving
 * comes back to the same month rather than to an unfiltered list. Only the
 * keys the server whitelists; empty values are omitted.
 */
const returnQuery = computed(() =>
    Object.fromEntries(
        Object.entries({ month: props.month, year: props.year }).filter(
            ([, value]) => value !== '' && value != null,
        ),
    ),
);

function editHref(income) {
    return route('incomes.edit', { income: income.uuid, ...returnQuery.value });
}

// The whole row is a click target; the buttons inside carry @click.stop.
function openEdit(income) {
    router.get(editHref(income));
}

const deleting = ref(null);
const deleteForm = useForm({});

// The row awaiting confirmation, so the prompt can name what is about to go.
const confirming = ref(null);

function confirmDestroy(income) {
    confirming.value = income;
}

function destroy() {
    const income = confirming.value;

    if (!income) {
        return;
    }

    deleting.value = income.uuid;

    deleteForm.delete(route('incomes.destroy', income.uuid), {
        preserveScroll: true,
        // Closed on success, not on click: a failed delete should leave the
        // prompt up rather than vanish with the row still there.
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = null;
        },
    });
}

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

// Amounts are stored in USD; this is the same figure shown in riel beside them.
const riel = (usd) => formatRiel(usd, khrPerUsd.value);

const dayFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
});

function formatDay(date) {
    const [year, month, day] = date.split('-').map(Number);
    const value = new Date(year, month - 1, day);
    const isToday = value.toDateString() === new Date().toDateString();

    return isToday ? trans('Today') : dayFormatter.format(value);
}

const isEmpty = computed(() => props.days.length === 0);

// Nothing logged yet, or nothing left after filtering — two different messages.
const filtered = computed(
    () =>
        Boolean(props.month) ||
        Boolean(props.year) ||
        Object.values(props.filters?.filter ?? {}).some((v) => v !== '' && v != null),
);

// Income has no category; every row wears the same green badge, which is
// also what tells it apart from an expense at a glance.
const badge = categoryColor('green').badge;
</script>

<template>
    <Head :title="trans('Income')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                    {{ __('Income') }}
                </h2>

                <!-- Desktop only. On a phone this is the floating button above
                     the tab bar, where a thumb already is. -->
                <Button
                    :as="Link"
                    :href="route('incomes.create', returnQuery)"
                    size="sm"
                    class="max-sm:hidden"
                >
                    {{ __('Add income') }}
                </Button>
            </div>
        </template>

        <div class="pb-8 pt-2">
            <div :class="[CARD, 'overflow-hidden p-2 sm:p-3']">
                <div class="grid grid-cols-2 gap-2 p-1 pb-2 sm:flex sm:flex-row sm:items-center sm:p-2 sm:pb-3">
                    <SearchInput
                        v-model="search"
                        :placeholder="__('Search income…')"
                        class="col-span-2 min-w-0 sm:max-w-sm sm:flex-1"
                    />

                    <DateFilter
                        :month-options="monthOptions"
                        :year-options="yearOptions"
                        :month="monthFilter"
                        :year="yearFilter"
                        :label="__('All dates')"
                        align="start"
                        content-class="w-72"
                        trigger-class="border-input dark:hover:bg-input/50 h-9 w-full min-w-0 rounded-xl border bg-card px-2.5 py-2 text-sm shadow-xs sm:w-56"
                        @update:month="applyMonthFilter"
                        @update:year="applyYearFilter"
                    />

                    <Button
                        v-if="hasActiveFilters"
                        variant="outline"
                        class="h-9 w-full sm:w-auto"
                        @click="clearFilters"
                    >
                        {{ __('Clear') }}
                    </Button>
                </div>

                <ExpenseListSkeleton v-if="navigating" />

                <div v-else-if="isEmpty" class="p-10 text-center">
                    <p class="text-sm text-gray-600 dark:text-neutral-400">
                        {{
                            filtered
                                ? __('No income matches these filters.')
                                : __('No income yet, add your first one.')
                        }}
                    </p>
                    <Button
                        v-if="!filtered"
                        :as="Link"
                        :href="route('incomes.create', returnQuery)"
                        class="mt-4"
                        size="sm"
                    >
                        {{ __('Add income') }}
                    </Button>
                </div>

                <div
                    v-for="day in navigating ? [] : days"
                    :key="day.date"
                    class="border-t border-gray-100 dark:border-neutral-800"
                >
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-neutral-800">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-neutral-100">
                            {{ formatDay(day.date) }}
                        </h3>
                        <!-- Only where it is actually a sum — see Expenses. -->
                        <span
                            v-if="day.incomes.length > 1"
                            class="text-sm font-semibold text-gray-800 dark:text-neutral-100"
                        >
                            {{ money.format(day.total) }}
                            <span class="text-xs font-normal" :class="MUTED">
                                {{ riel(day.total) }}
                            </span>
                        </span>
                    </div>

                    <ul class="divide-y divide-gray-100 dark:divide-neutral-800">
                        <li
                            v-for="income in day.incomes"
                            :key="income.uuid"
                            class="group flex cursor-pointer items-center gap-3 px-4 py-3 transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] hover:bg-gray-50 active:scale-[0.97] dark:hover:bg-neutral-800/50"
                            @click="openEdit(income)"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
                                :class="badge"
                            >
                                <Banknote class="size-4" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-neutral-100">
                                    {{ income.source }}
                                </p>
                                <p
                                    v-if="income.note"
                                    class="truncate text-xs text-gray-500 dark:text-neutral-400"
                                >
                                    {{ income.note }}
                                </p>
                            </div>

                            <span class="shrink-0 text-right tabular-nums">
                                <span class="block text-base font-bold text-gray-900 dark:text-neutral-100">
                                    {{ money.format(income.amount) }}
                                </span>
                                <span class="block text-xs" :class="MUTED">
                                    {{ riel(income.amount) }}
                                </span>
                            </span>

                            <div
                                class="flex shrink-0 gap-1 opacity-100 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                            >
                                <Button
                                    :as="Link"
                                    :href="editHref(income)"
                                    variant="secondary"
                                    size="xs"
                                    class="rounded-xl max-sm:h-8"
                                    @click.stop
                                >
                                    {{ __('Edit') }}
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="xs"
                                    class="rounded-xl max-sm:h-8"
                                    :disabled="deleting === income.uuid"
                                    @click.stop="confirmDestroy(income)"
                                >
                                    {{ __('Delete') }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>

                <Pagination v-if="!navigating" :meta="pagination" />
            </div>
        </div>

        <FloatingAddButton
            :href="route('incomes.create', returnQuery)"
            :label="__('Add income')"
        />

        <ConfirmDialog
            :open="confirming !== null"
            :title="__('Delete this income?')"
            :description="
                confirming
                    ? __('&quot;:source&quot; (:amount) will be removed. This cannot be undone.', {
                          source: confirming.source,
                          amount: money.format(confirming.amount),
                      })
                    : ''
            "
            :confirm-label="__('Delete')"
            :cancel-label="__('Cancel')"
            :processing="deleting !== null"
            :processing-label="__('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="destroy"
        />
    </AuthenticatedLayout>
</template>
