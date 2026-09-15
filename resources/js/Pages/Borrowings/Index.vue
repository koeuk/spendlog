<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Briefcase, CircleDashed, House, Landmark, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD, EYEBROW, FIGURE, MUTED, SEGMENT, SEGMENT_OFF, SEGMENT_ON } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import ExpenseListSkeleton from '@/Components/ExpenseListSkeleton.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useNavigating } from '@/composables/useNavigating';
import { trans } from '@/lib/i18n';
import { categoryColor } from '@/lib/categoryStyles';
import { Button } from '@/Components/ui/button';
import FloatingAddButton from '@/Components/FloatingAddButton.vue';

/**
 * Everything borrowed, still-owed first.
 *
 * Not grouped by day like Expenses and Income: a debt is not a thing that
 * happened on a date so much as a thing that is still there, so each row
 * leads with who is owed and how much is left, and the date it started is
 * a detail under it.
 */
const props = defineProps({
    borrowings: { type: Array, required: true },
    pagination: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    // 'open' | 'settled' | 'all'
    status: { type: String, default: 'all' },
    // BorrowingSummary::forUser — all time, not a month.
    summary: { type: Object, required: true },
    // [{ value, label }] with labels resolved server-side for the locale.
    lender_types: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ create: false }) },
});

const { navigating } = useNavigating();

/*
 * Every control narrows the same list, so each edits the current query rather
 * than replacing it. Empty values are dropped rather than sent blank:
 * ?filter[lender]= is a filter for the empty string, not the absence of one.
 */
function navigate({ status, ...changes } = {}) {
    const filter = { ...(props.filters?.filter ?? {}), ...changes };
    const query = {};

    const nextStatus = status !== undefined ? status : props.status;

    if (nextStatus && nextStatus !== 'all') {
        query.status = nextStatus;
    }

    for (const [key, value] of Object.entries(filter)) {
        if (value !== '' && value !== null && value !== undefined) {
            query.filter = { ...query.filter, [key]: value };
        }
    }

    router.get(route('borrowings.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const search = ref(props.filters?.filter?.lender ?? '');

// Clearing every filter at once sets this ref alongside the others, and the
// watcher would then fire a second request that merges the filters just
// dropped back in. The reset owns the navigation for that one change.
let resetting = false;

watch(search, (value) => {
    if (resetting) {
        return;
    }

    navigate({ lender: value });
});

const typeFilter = ref(props.filters?.filter?.type ?? '');

// '' is the "no filter" option rather than a sentinel, so it round-trips
// through navigate()'s empty-value drop untouched.
const typeOptions = computed(() => [
    { value: '', label: trans('All lender types') },
    ...props.lender_types,
]);

function applyTypeFilter(value) {
    typeFilter.value = value;
    navigate({ type: value });
}

const STATUSES = [
    { value: 'open', label: 'Still owed' },
    { value: 'settled', label: 'Settled' },
    { value: 'all', label: 'All' },
];

const hasActiveFilters = computed(
    () => Boolean(search.value) || Boolean(typeFilter.value) || props.status !== 'all',
);

function clearFilters() {
    resetting = true;

    search.value = '';
    typeFilter.value = '';

    router.get(route('borrowings.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            resetting = false;
        },
    });
}

// The whole row opens the borrowing's own page, where the ledger is.
function open(borrowing) {
    router.get(route('borrowings.show', borrowing.uuid));
}

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

// Amounts are stored in USD; this is the same figure shown in riel beside them.
const riel = (usd) => formatRiel(usd, khrPerUsd.value);

const dateFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

function formatDate(date) {
    const [year, month, day] = date.split('-').map(Number);

    return dateFormatter.format(new Date(year, month - 1, day));
}

/**
 * One icon and one colour per kind of lender, so a row says "family" or
 * "bank" before the label under it is read. The palette is the category one,
 * which keeps every badge in the app on the same set of tints.
 */
const TYPE_STYLES = {
    friend: { icon: Users, color: 'teal' },
    family: { icon: House, color: 'amber' },
    bank: { icon: Landmark, color: 'blue' },
    employer: { icon: Briefcase, color: 'indigo' },
    other: { icon: CircleDashed, color: 'slate' },
};

const styleFor = (type) => TYPE_STYLES[type] ?? TYPE_STYLES.other;

const isEmpty = computed(() => props.borrowings.length === 0);

// Nothing logged yet, or nothing left after filtering — two different messages.
const filtered = computed(() => hasActiveFilters.value);
</script>

<template>
    <Head :title="trans('Borrowing')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                    {{ __('Borrowing') }}
                </h2>

                <!-- Desktop only. On a phone this is the floating button above
                     the tab bar, where a thumb already is. -->
                <Button
                    v-if="can.create"
                    :as="Link"
                    :href="route('borrowings.create')"
                    size="sm"
                    class="max-sm:hidden"
                >
                    {{ __('Add borrowing') }}
                </Button>
            </div>
        </template>

        <div class="space-y-4 pb-8 pt-2">
            <!--
                Three figures, all time: a debt does not belong to a month.
                What is still owed leads, because it is the one that changes
                what you do next; the other two are the story behind it.
            -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div :class="[CARD, 'p-5']">
                    <p :class="EYEBROW">{{ __('Still owed') }}</p>
                    <p class="mt-1">
                        <span :class="FIGURE">{{ money.format(summary.outstanding) }}</span>
                    </p>
                    <p class="mt-1 text-sm" :class="MUTED">
                        {{ riel(summary.outstanding) }}
                    </p>
                    <p class="mt-3 text-xs" :class="MUTED">
                        {{ __(':count open', { count: summary.open_count }) }}
                        <template v-if="summary.overdue_count > 0">
                            ·
                            <span class="font-semibold text-red-600 dark:text-red-400">
                                {{ __(':count overdue', { count: summary.overdue_count }) }}
                            </span>
                        </template>
                    </p>
                </div>

                <div :class="[CARD, 'p-5']">
                    <p :class="EYEBROW">{{ __('Borrowed in total') }}</p>
                    <p class="mt-1">
                        <span :class="FIGURE">{{ money.format(summary.borrowed) }}</span>
                    </p>
                    <p class="mt-1 text-sm" :class="MUTED">
                        {{ riel(summary.borrowed) }}
                    </p>
                    <p class="mt-3 text-xs" :class="MUTED">{{ __('All time') }}</p>
                </div>

                <div :class="[CARD, 'p-5']">
                    <p :class="EYEBROW">{{ __('Repaid so far') }}</p>
                    <p class="mt-1">
                        <span :class="FIGURE">{{ money.format(summary.repaid) }}</span>
                    </p>
                    <p class="mt-1 text-sm" :class="MUTED">
                        {{ riel(summary.repaid) }}
                    </p>
                    <p class="mt-3 text-xs" :class="MUTED">
                        {{ __(':count settled', { count: summary.settled_count }) }}
                    </p>
                </div>
            </div>

            <!-- Who is owed what, by kind — only while something is. -->
            <div v-if="summary.by_lender_type.length" :class="[CARD, 'p-5']">
                <p :class="EYEBROW">{{ __('Still owed by lender type') }}</p>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    <li
                        v-for="row in summary.by_lender_type"
                        :key="row.lender_type"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
                                :class="categoryColor(styleFor(row.lender_type).color).badge"
                            >
                                <component :is="styleFor(row.lender_type).icon" class="size-3.5" />
                            </span>
                            <span class="truncate font-medium">{{ row.label }}</span>
                            <span class="text-xs" :class="MUTED">
                                {{ __(':count open', { count: row.count }) }}
                            </span>
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums">
                            {{ money.format(row.outstanding) }}
                        </span>
                    </li>
                </ul>
            </div>

            <div :class="[CARD, 'overflow-hidden p-2 sm:p-3']">
                <div class="grid grid-cols-2 gap-2 p-1 pb-2 sm:flex sm:flex-row sm:items-center sm:p-2 sm:pb-3">
                    <SearchInput
                        v-model="search"
                        :placeholder="__('Search lenders…')"
                        class="col-span-2 min-w-0 sm:max-w-sm sm:flex-1"
                    />

                    <SearchableSelect
                        :options="typeOptions"
                        :model-value="typeFilter"
                        :label="__('Filter by lender type')"
                        :searchable="false"
                        align="start"
                        content-class="w-52"
                        trigger-class="border-input dark:hover:bg-input/50 h-9 w-full min-w-0 rounded-xl border bg-card px-2.5 py-2 text-sm shadow-xs sm:w-48"
                        @update:model-value="applyTypeFilter"
                    />

                    <!-- Which debts to show. Not a filter in the spatie sense —
                         it decides which list you are looking at. -->
                    <div :class="[SEGMENT, 'h-9 w-full sm:w-auto']" role="group">
                        <button
                            v-for="option in STATUSES"
                            :key="option.value"
                            type="button"
                            class="flex-1 px-3 text-xs font-semibold transition sm:flex-none"
                            :class="status === option.value ? SEGMENT_ON : SEGMENT_OFF"
                            :aria-pressed="status === option.value"
                            @click="navigate({ status: option.value })"
                        >
                            {{ __(option.label) }}
                        </button>
                    </div>

                    <Button
                        v-if="hasActiveFilters"
                        variant="outline"
                        class="col-span-2 h-9 w-full sm:w-auto"
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
                                ? __('No borrowing matches these filters.')
                                : __('No borrowing yet, add your first one.')
                        }}
                    </p>
                    <Button
                        v-if="!filtered && can.create"
                        :as="Link"
                        :href="route('borrowings.create')"
                        class="mt-4"
                        size="sm"
                    >
                        {{ __('Add borrowing') }}
                    </Button>
                </div>

                <ul
                    v-else
                    class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-neutral-800 dark:border-neutral-800"
                >
                    <li
                        v-for="borrowing in borrowings"
                        :key="borrowing.uuid"
                        class="group cursor-pointer px-4 py-3 transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] hover:bg-gray-50 active:scale-[0.97] dark:hover:bg-neutral-800/50"
                        :class="borrowing.settled ? 'opacity-70' : ''"
                        @click="open(borrowing)"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
                                :class="categoryColor(styleFor(borrowing.lender_type).color).badge"
                            >
                                <component :is="styleFor(borrowing.lender_type).icon" class="size-4" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-neutral-100">
                                    {{ borrowing.lender }}
                                </p>
                                <p class="truncate text-xs" :class="MUTED">
                                    {{ borrowing.lender_type_label }}
                                    · {{ formatDate(borrowing.borrowed_on) }}
                                    <template v-if="borrowing.settled">
                                        ·
                                        <span class="font-semibold text-green-600 dark:text-green-400">
                                            {{ __('Settled') }}
                                        </span>
                                    </template>
                                    <template v-else-if="borrowing.overdue">
                                        ·
                                        <span class="font-semibold text-red-600 dark:text-red-400">
                                            {{ __('Overdue since :date', { date: formatDate(borrowing.due_on) }) }}
                                        </span>
                                    </template>
                                    <template v-else-if="borrowing.due_on">
                                        · {{ __('Due :date', { date: formatDate(borrowing.due_on) }) }}
                                    </template>
                                </p>
                            </div>

                            <!-- What is left is the figure a row is read for;
                                 the original amount sits under it, smaller. -->
                            <span class="shrink-0 text-right tabular-nums">
                                <span class="block text-base font-bold text-gray-900 dark:text-neutral-100">
                                    {{ money.format(borrowing.settled ? borrowing.amount : borrowing.remaining) }}
                                </span>
                                <span class="block text-xs" :class="MUTED">
                                    <template v-if="borrowing.settled">
                                        {{ __('Paid back') }}
                                    </template>
                                    <template v-else>
                                        {{ __('of :amount', { amount: money.format(borrowing.amount) }) }}
                                    </template>
                                </span>
                            </span>
                        </div>

                        <!-- The ledger's progress, only once something has
                             been paid: a bar at zero is noise on every row. -->
                        <div
                            v-if="borrowing.repaid > 0 && !borrowing.settled"
                            class="ml-11 mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
                            role="progressbar"
                            :aria-valuenow="borrowing.percent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${borrowing.percent}%` }"
                            />
                        </div>
                    </li>
                </ul>

                <Pagination v-if="!navigating" :meta="pagination" />
            </div>
        </div>

        <FloatingAddButton
            v-if="can.create"
            :href="route('borrowings.create')"
            :label="__('Add borrowing')"
        />
    </AuthenticatedLayout>
</template>
