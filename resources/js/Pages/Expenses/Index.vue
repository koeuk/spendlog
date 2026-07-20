<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { CARD, MUTED } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import ExpenseListSkeleton from '@/Components/ExpenseListSkeleton.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useNavigating } from '@/composables/useNavigating';
import { localized, trans } from '@/lib/i18n';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { Button } from '@/Components/ui/button';
import FloatingAddButton from '@/Components/FloatingAddButton.vue';

const props = defineProps({
    days: { type: Array, required: true },
    pagination: { type: Object, required: true },
    categories: { type: Array, required: true },
    scope: { type: String, default: 'mine' },
    can: { type: Object, default: () => ({ view_all: false, create_category: false }) },
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // The two date filters. '' means "every one" for each, and they are
    // independent: a year alone shows the whole year, a month alone shows that
    // month across every year.
    month: { type: String, default: '' },
    year: { type: String, default: '' },
    // Month labels are built server-side so they follow the app locale.
    months: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
});

const viewingAll = computed(() => props.scope === 'all');

const { navigating } = useNavigating();

/*
 * Every control on this page narrows the same list, so each one edits the
 * current query rather than replacing it — otherwise switching scope would
 * silently drop the search term the user just typed.
 *
 * Empty values are dropped rather than sent blank: ?filter[item]= is a filter
 * for the empty string, not the absence of one.
 */
function navigate({ scope, month, year, ...changes } = {}) {
    const filter = { ...(props.filters?.filter ?? {}), ...changes };
    const query = {};

    const nextScope = scope ?? props.scope;

    if (nextScope === 'all') {
        query.scope = 'all';
    }

    // Carried as top-level params, not filters (spatie rejects an unknown
    // filter). Each is preserved unless this call changes it, so picking a
    // category does not silently clear the month you chose.
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

    router.get(route('expenses.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setScope(scope) {
    // The user filter only exists on the everyone view; carrying it back to
    // "mine" would send a filter the server refuses to allow there.
    navigate(scope === 'all' ? { scope } : { scope, user: '' });
}

const search = ref(props.filters?.filter?.item ?? '');

// Clearing every filter at once sets this ref alongside the others, and the
// watcher below would then fire a second request that merges the filters we
// just dropped back in. The reset owns the navigation, so the watcher stands
// down for that one change.
let resetting = false;

watch(search, (value) => {
    if (resetting) {
        return;
    }

    navigate({ item: value });
});

const categoryFilter = ref(props.filters?.filter?.category ?? '');

// '' is the "no filter" option rather than a sentinel, so it round-trips
// through navigate()'s empty-value drop untouched.
const categoryOptions = computed(() => [
    { value: '', label: trans('All categories') },
    ...props.categories.map((c) => ({
        value: c.uuid,
        label: localized(c.name),
    })),
]);

function applyCategoryFilter(uuid) {
    categoryFilter.value = uuid;
    navigate({ category: uuid });
}

// Two independent date controls. '' is the "no filter" option in each, so it
// round-trips through navigate()'s empty-value drop like the category one.
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

// Admin-only: narrow the everyone view to a single person.
const userFilter = ref(props.filters?.filter?.user ?? '');

const userOptions = computed(() => [
    { value: '', label: trans('All users') },
    ...props.users.map((u) => ({ value: u.uuid, label: u.name })),
]);

function applyUserFilter(uuid) {
    userFilter.value = uuid;
    navigate({ user: uuid });
}

// Only shown once there is something to clear — an always-visible Clear on an
// untouched list is a button that does nothing.
const hasActiveFilters = computed(
    () =>
        Boolean(search.value) ||
        Boolean(categoryFilter.value) ||
        Boolean(monthFilter.value) ||
        Boolean(yearFilter.value) ||
        Boolean(userFilter.value),
);

// Back to the unfiltered list. Scope is not a filter — it is which list you are
// looking at — so "mine" or "everyone" survives the reset.
function clearFilters() {
    resetting = true;

    search.value = '';
    categoryFilter.value = '';
    monthFilter.value = '';
    yearFilter.value = '';
    userFilter.value = '';

    router.get(
        route('expenses.index'),
        viewingAll.value ? { scope: 'all' } : {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            // The watcher runs after the ref settles, so it is only safe to
            // re-arm once this navigation is on its way.
            onFinish: () => {
                resetting = false;
            },
        },
    );
}

/**
 * Where this list currently is, handed to the create/edit screens so saving
 * comes back to the same month and scope rather than to an unfiltered list.
 *
 * Only the four keys the server whitelists — anything else it drops, so sending
 * more would be noise in the URL. Empty values are omitted rather than sent
 * blank, which keeps the plain case a clean /expenses/create.
 */
const returnQuery = computed(() =>
    Object.fromEntries(
        Object.entries({
            month: props.month,
            year: props.year,
            scope: props.scope === 'all' ? 'all' : '',
            user: props.filters?.filter?.user ?? '',
        }).filter(([, value]) => value !== '' && value != null),
    ),
);

/**
 * The edit screen for a row, with the list's position carried along.
 *
 * Ziggy puts named parameters in the path and anything left over in the query,
 * so spreading returnQuery here is what turns it into ?month=…&scope=….
 */
function editHref(expense) {
    return route('expenses.edit', { expense: expense.uuid, ...returnQuery.value });
}

// The whole row is a click target, not just the Edit button. The buttons inside
// it carry @click.stop so they do not fire this as well.
function openEdit(expense) {
    router.get(editHref(expense));
}

const deleting = ref(null);
const deleteForm = useForm({});

// The expense awaiting confirmation. Holding the row itself, not just a flag,
// lets the prompt name what is about to go.
const confirming = ref(null);

function confirmDestroy(expense) {
    confirming.value = expense;
}

function destroy() {
    const expense = confirming.value;

    if (!expense) {
        return;
    }

    deleting.value = expense.uuid;

    deleteForm.delete(route('expenses.destroy', expense.uuid), {
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

// Prices are stored in USD; this is the same figure shown in riel beside them.
const riel = (usd) => formatRiel(usd, khrPerUsd.value);

const dayFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
});

function formatDay(date) {
    const [year, month, day] = date.split('-').map(Number);
    const value = new Date(year, month - 1, day);
    const todayDate = new Date();
    const isToday = value.toDateString() === todayDate.toDateString();

    return isToday ? trans('Today') : dayFormatter.format(value);
}

const isEmpty = computed(() => props.days.length === 0);

// An empty list means two different things: nothing logged yet, or nothing left
// after filtering. Offering "add your first one" to someone who just searched
// would be answering a question they did not ask.
const filtered = computed(() =>
    Object.values(props.filters?.filter ?? {}).some((v) => v !== '' && v != null),
);
</script>

<template>
    <Head title="Expenses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                    {{ __('Expenses') }}
                </h2>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Admin only: switch between own expenses and everyone's -->
                    <div
                        v-if="can.view_all"
                        class="inline-flex rounded-md border border-gray-200 dark:border-neutral-800 bg-white dark:bg-neutral-800 p-0.5"
                    >
                        <button
                            type="button"
                            class="rounded px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                !viewingAll
                                    ? 'bg-gray-900 text-white'
                                    : 'text-gray-600 dark:text-neutral-400 hover:bg-gray-50 dark:hover:bg-neutral-800'
                            "
                            @click="setScope('mine')"
                        >
                            {{ __('Mine') }}
                        </button>
                        <button
                            type="button"
                            class="rounded px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                viewingAll
                                    ? 'bg-gray-900 text-white'
                                    : 'text-gray-600 dark:text-neutral-400 hover:bg-gray-50 dark:hover:bg-neutral-800'
                            "
                            @click="setScope('all')"
                        >
                            {{ __('Everyone') }}
                        </button>
                    </div>

                    <!--
                        Not a native <select>: its popup is drawn by the OS, so it
                        ignores the app's theme entirely — a white system menu on a
                        dark page. This one is ours, so it inherits the glass.
                    -->
                    <SearchableSelect
                        v-if="can.view_all && viewingAll"
                        :options="userOptions"
                        :model-value="userFilter"
                        :label="__('Filter by user')"
                        :search-placeholder="__('Search people…')"
                        :empty-text="__('No one found.')"
                        content-class="w-44"
                        trigger-class="h-8 rounded-full border border-neutral-200 bg-white px-3 text-xs font-medium text-neutral-700 sm:w-44 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                        @update:model-value="applyUserFilter"
                    />

                    <!-- Desktop only. On a phone this is the floating button
                         above the tab bar, where a thumb already is — up here it
                         was a reach to the far top corner on every add. -->
                    <Button
                        :as="Link"
                        :href="route('expenses.create', returnQuery)"
                        size="sm"
                        class="max-sm:hidden"
                    >
                        {{ __('Add expense') }}
                    </Button>
                </div>
            </div>
        </template>

        <!-- pt-2, not pt-8. The layout's header already carries pb-6, so the
             old padding stacked into a 56px band of nothing between the title
             and the first control. -->
        <div class="pb-8 pt-2">
            <!-- Width and gutters come from the layout's one container, so the
                 column never resizes when navigating between pages. -->
            <div class="space-y-4">
                <!--
                    A two-column grid on a phone, the same flex row as before
                    from sm: up.

                    Stacked, these four controls spent most of the first screen
                    on filters nobody had touched yet. Search and category keep
                    the full width — one is typed into, the other holds the
                    longest labels — while month and year pair off on one line,
                    which is also what they are: two halves of a single date,
                    not two unrelated dropdowns.

                    Each select is placed by its own trigger-class, because
                    SearchableSelect's root is a Popover that renders no element
                    and would drop a class set on the component.
                -->
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-row sm:items-center">
                    <SearchInput
                        v-model="search"
                        :placeholder="__('Search expenses…')"
                        class="col-span-2 min-w-0 sm:max-w-sm sm:flex-1"
                        input-class="bg-card"
                    />

                    <SearchableSelect
                        :options="categoryOptions"
                        :model-value="categoryFilter"
                        :label="__('Filter by category')"
                        :search-placeholder="__('Search categories…')"
                        :empty-text="__('No category found.')"
                        align="start"
                        content-class="w-52"
                        trigger-class="border-input dark:hover:bg-input/50 col-span-2 h-9 w-full rounded-md border bg-card px-2.5 py-2 text-sm shadow-xs sm:w-52"
                        @update:model-value="applyCategoryFilter"
                    />

                    <!-- Month and year are separate controls: either narrows on
                         its own, and together they pin a single month. -->
                    <SearchableSelect
                        :options="monthOptions"
                        :model-value="monthFilter"
                        :label="__('Filter by month')"
                        :search-placeholder="__('Search…')"
                        :empty-text="__('Nothing found.')"
                        align="start"
                        content-class="w-44"
                        trigger-class="border-input dark:hover:bg-input/50 h-9 w-full min-w-0 rounded-md border bg-card px-2.5 py-2 text-sm shadow-xs sm:w-40"
                        @update:model-value="applyMonthFilter"
                    />

                    <SearchableSelect
                        :options="yearOptions"
                        :model-value="yearFilter"
                        :label="__('Filter by year')"
                        :search-placeholder="__('Search…')"
                        :empty-text="__('Nothing found.')"
                        match-value
                        align="start"
                        content-class="w-36"
                        trigger-class="border-input dark:hover:bg-input/50 h-9 w-full min-w-0 rounded-md border bg-card px-2.5 py-2 text-sm shadow-xs sm:w-32"
                        @update:model-value="applyYearFilter"
                    />

                    <!-- Spans both columns on a phone so it never sits half-width
                         beside a dropdown, and takes its natural width from sm: up. -->
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

                <div
                    v-else-if="isEmpty"
                    :class="[CARD, 'p-10 text-center']"
                >
                    <p class="text-sm text-gray-600 dark:text-neutral-400">
                        {{
                            filtered
                                ? __('No expenses match these filters.')
                                : __('No expenses yet, add your first one.')
                        }}
                    </p>
                    <Button
                        v-if="!filtered"
                        :as="Link"
                        :href="route('expenses.create', returnQuery)"
                        class="mt-4"
                        size="sm"
                    >
                        {{ __('Add expense') }}
                    </Button>
                </div>

                <div
                    v-for="day in navigating ? [] : days"
                    :key="day.date"
                    :class="[CARD, 'overflow-hidden']"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-100 dark:border-neutral-800 px-4 py-3"
                    >
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-neutral-100">
                            {{ formatDay(day.date) }}
                        </h3>
                        <!--
                            Only where it is actually a sum.

                            A day holding one expense had its total printed twice
                            — once here and once on the only row under it, the
                            same figure in both currencies — which reads as two
                            numbers to check against each other rather than one
                            fact. With several rows the header is doing real work,
                            so it stays.
                        -->
                        <span
                            v-if="day.expenses.length > 1"
                            class="text-sm font-semibold text-gray-800 dark:text-neutral-100"
                        >
                            {{ money.format(day.total) }}
                            <!-- Stored totals are USD; riel trails them at
                                 today's rate rather than reading as a second
                                 total of its own. -->
                            <span class="text-xs font-normal" :class="MUTED">
                                {{ riel(day.total) }}
                            </span>
                        </span>
                    </div>

                    <ul class="divide-y divide-gray-100 dark:divide-neutral-800">
                        <li
                            v-for="expense in day.expenses"
                            :key="expense.uuid"
                            class="group flex cursor-pointer items-center gap-3 px-4 py-3 transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] hover:bg-gray-50 active:scale-[0.97] dark:hover:bg-neutral-800/50"
                            @click="openEdit(expense)"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
                                :class="categoryColor(expense.category_color).badge"
                            >
                                <component
                                    :is="categoryIcon(expense.category_icon)"
                                    v-if="categoryIcon(expense.category_icon)"
                                    class="size-4"
                                />
                                <span
                                    v-else
                                    class="size-2 rounded-full"
                                    :class="categoryColor(expense.category_color).dot"
                                />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-neutral-100">
                                    {{ expense.item }}
                                </p>
                                <p class="truncate text-xs text-gray-500 dark:text-neutral-400">
                                    {{ expense.category }}
                                    <template v-if="expense.owner">
                                        · {{ expense.owner }}
                                    </template>
                                </p>
                            </div>

                            <!-- Stacked rather than inline: this column is narrow
                                 and sits between the item and the row actions, so
                                 a second figure beside it would crowd both. -->
                            <span class="shrink-0 text-right tabular-nums">
                                <!-- The amount is what this row is read for, and
                                     it was set at the same size and weight as the
                                     item name beside it. Now it carries the
                                     emphasis the day header used to. -->
                                <span class="block text-base font-bold text-gray-900 dark:text-neutral-100">
                                    {{ money.format(expense.price) }}
                                </span>
                                <span class="block text-xs" :class="MUTED">
                                    {{ riel(expense.price) }}
                                </span>
                            </span>

                            <div
                                class="flex shrink-0 gap-1 opacity-100 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                            >
                                <Button
                                    :as="Link"
                                    :href="editHref(expense)"
                                    variant="ghost"
                                    size="sm"
                                    @click.stop
                                >
                                    {{ __('Edit') }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                    :disabled="deleting === expense.uuid"
                                    @click.stop="confirmDestroy(expense)"
                                >
                                    {{ __('Delete') }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Its own card, so the pager sits on a surface like the day
                     groups above it rather than floating on the page. -->
                <div v-if="!navigating" :class="[CARD, 'overflow-hidden']">
                    <Pagination :meta="pagination" />
                </div>
            </div>
        </div>

        <FloatingAddButton
            :href="route('expenses.create', returnQuery)"
            :label="__('Add expense')"
        />

        <ConfirmDialog
            :open="confirming !== null"
            :title="__('Delete this expense?')"
            :description="
                confirming
                    ? __('&quot;:item&quot; (:price) will be removed. This cannot be undone.', {
                          item: confirming.item,
                          price: money.format(confirming.price),
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
