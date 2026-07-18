<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import ExpenseForm from '@/Components/ExpenseForm.vue';
import { CARD } from '@/lib/appStyles';
import ExpenseListSkeleton from '@/Components/ExpenseListSkeleton.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useNavigating } from '@/composables/useNavigating';
import { localized, trans } from '@/lib/i18n';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const props = defineProps({
    days: { type: Array, required: true },
    pagination: { type: Object, required: true },
    categories: { type: Array, required: true },
    scope: { type: String, default: 'mine' },
    can: { type: Object, default: () => ({ view_all: false, create_category: false }) },
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // 'all' | 'week' | 'month' | 'year' — the active date filter.
    period: { type: String, default: 'all' },
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
function navigate({ scope, period, ...changes } = {}) {
    const filter = { ...(props.filters?.filter ?? {}), ...changes };
    const query = {};

    const nextScope = scope ?? props.scope;

    if (nextScope === 'all') {
        query.scope = 'all';
    }

    // Carried as a top-level param, not a filter (spatie would reject an unknown
    // filter). Preserved across every other control so changing the category
    // does not silently clear the chosen date range.
    const nextPeriod = period !== undefined ? period : props.period;

    if (nextPeriod && nextPeriod !== 'all') {
        query.period = nextPeriod;
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

watch(search, (value) => navigate({ item: value }));

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

// Date range. 'all' is the no-filter option so it round-trips through
// navigate()'s drop untouched, same as the empty category value.
const dateFilter = ref(props.period ?? 'all');

const dateOptions = computed(() => [
    { value: 'all', label: trans('All dates') },
    { value: 'week', label: trans('This week') },
    { value: 'month', label: trans('This month') },
    { value: 'year', label: trans('This year') },
]);

function applyDateFilter(value) {
    dateFilter.value = value;
    navigate({ period: value });
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

function todayString() {
    // Local date, not UTC — toISOString() would shift the day for some zones.
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const showDialog = ref(false);
const editing = ref(null);

const form = useForm({
    // One key per locale — item is a translatable JSON column, like category.name.
    item: { en: '', km: '' },
    price: '',
    category_uuid: '',
    // Set instead of category_uuid when naming a category inline.
    new_category: '',
    spent_on: todayString(),
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.spent_on = todayString();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(expense) {
    editing.value = expense;
    // item_translations is the raw JSON; item alone is only the active locale,
    // so editing from it would quietly drop the other language on save.
    form.item = {
        en: expense.item_translations?.en ?? '',
        km: expense.item_translations?.km ?? '',
    };
    form.price = String(expense.price);
    form.category_uuid = expense.category_uuid;
    form.new_category = '';
    form.spent_on = expense.spent_on;
    form.clearErrors();
    showDialog.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
            form.spent_on = todayString();
        },
    };

    if (editing.value) {
        form.put(route('expenses.update', editing.value.uuid), options);
    } else {
        form.post(route('expenses.store'), options);
    }
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
                        trigger-class="h-8 w-44 rounded-full border border-neutral-200 bg-white px-3 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                        @update:model-value="applyUserFilter"
                    />

                    <Button size="sm" @click="openCreate">{{ __('Add expense') }}</Button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <!-- Width and gutters come from the layout's one container, so the
                 column never resizes when navigating between pages. -->
            <div class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <SearchInput
                        v-model="search"
                        :placeholder="__('Search expenses…')"
                        class="sm:max-w-sm sm:flex-1"
                    />

                    <SearchableSelect
                        :options="categoryOptions"
                        :model-value="categoryFilter"
                        :label="__('Filter by category')"
                        :search-placeholder="__('Search categories…')"
                        :empty-text="__('No category found.')"
                        align="start"
                        content-class="w-52"
                        trigger-class="border-input dark:bg-input/30 dark:hover:bg-input/50 h-9 rounded-md border bg-transparent px-2.5 py-2 text-sm shadow-xs sm:w-52"
                        @update:model-value="applyCategoryFilter"
                    />

                    <SearchableSelect
                        :options="dateOptions"
                        :model-value="dateFilter"
                        :label="__('Filter by date')"
                        :search-placeholder="__('Search…')"
                        :empty-text="__('Nothing found.')"
                        align="start"
                        content-class="w-44"
                        trigger-class="border-input dark:bg-input/30 dark:hover:bg-input/50 h-9 rounded-md border bg-transparent px-2.5 py-2 text-sm shadow-xs sm:w-44"
                        @update:model-value="applyDateFilter"
                    />
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
                    <Button v-if="!filtered" class="mt-4" size="sm" @click="openCreate">
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
                        <span class="text-sm font-semibold text-gray-800 dark:text-neutral-100">
                            {{ money.format(day.total) }}
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

                            <span class="text-sm tabular-nums text-gray-900 dark:text-neutral-100">
                                {{ money.format(expense.price) }}
                            </span>

                            <div
                                class="flex shrink-0 gap-1 opacity-100 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                            >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click.stop="openEdit(expense)"
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

        <Dialog v-model:open="showDialog">
            <!-- Deliberately narrower than the max-w-5xl content column: this is
                 a short form, and a dialog as wide as the page reads as a page. -->
            <DialogContent class="sm:max-w-3xl">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? __('Edit expense') : __('Add expense') }}
                        </DialogTitle>
                    </DialogHeader>

                    <div class="py-4">
                        <ExpenseForm
                            :form="form"
                            :categories="categories"
                            :can-create-category="can.create_category"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showDialog = false"
                        >
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? __('Save') : __('Add') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
