<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExpenseForm from '@/Components/ExpenseForm.vue';
import ExpenseListSkeleton from '@/Components/ExpenseListSkeleton.vue';
import { useNavigating } from '@/composables/useNavigating';
import { trans } from '@/lib/i18n';
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
    can: { type: Object, default: () => ({ view_all: false }) },
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const viewingAll = computed(() => props.scope === 'all');

const { navigating } = useNavigating();

function setScope(scope) {
    router.get(
        route('expenses.index'),
        scope === 'all' ? { scope: 'all' } : {},
        { preserveScroll: true },
    );
}

// Admin-only: narrow the everyone view to a single person.
const userFilter = ref(props.filters?.filter?.user ?? '');

function applyUserFilter(uuid) {
    userFilter.value = uuid;
    router.get(
        route('expenses.index'),
        uuid ? { scope: 'all', filter: { user: uuid } } : { scope: 'all' },
        { preserveScroll: true },
    );
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
    item: '',
    price: '',
    category_uuid: '',
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
    form.item = expense.item;
    form.price = String(expense.price);
    form.category_uuid = expense.category_uuid;
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

function destroy(expense) {
    deleting.value = expense.uuid;
    deleteForm.delete(route('expenses.destroy', expense.uuid), {
        preserveScroll: true,
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

                    <select
                        v-if="can.view_all && viewingAll"
                        :value="userFilter"
                        class="rounded-md border-gray-200 dark:border-neutral-800 py-1 text-xs text-gray-700 dark:text-neutral-300 focus:border-gray-400 focus:ring-0"
                        @change="applyUserFilter($event.target.value)"
                    >
                        <option value="">{{ __('All users') }}</option>
                        <option v-for="u in users" :key="u.uuid" :value="u.uuid">
                            {{ u.name }}
                        </option>
                    </select>

                    <Button size="sm" @click="openCreate">{{ __('Add expense') }}</Button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                <ExpenseListSkeleton v-if="navigating" />

                <div
                    v-else-if="isEmpty"
                    class="rounded-lg bg-white dark:bg-neutral-900 p-10 text-center shadow-sm"
                >
                    <p class="text-sm text-gray-600 dark:text-neutral-400">
                        {{ __('No expenses yet, add your first one.') }}
                    </p>
                    <Button class="mt-4" size="sm" @click="openCreate">
                        {{ __('Add expense') }}
                    </Button>
                </div>

                <div
                    v-for="day in navigating ? [] : days"
                    :key="day.date"
                    class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900"
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
                            class="group flex items-center gap-3 px-4 py-3"
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
                                    @click="openEdit(expense)"
                                >
                                    {{ __('Edit') }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                    :disabled="deleting === expense.uuid"
                                    @click="destroy(expense)"
                                >
                                    {{ __('Delete') }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="pagination.last_page > 1"
                    class="flex items-center justify-between pt-2"
                >
                    <Link
                        v-if="pagination.prev_page_url"
                        :href="pagination.prev_page_url"
                        class="text-sm text-gray-600 dark:text-neutral-400 hover:text-gray-900 dark:hover:text-neutral-100"
                    >
                        &larr; {{ __('Newer') }}
                    </Link>
                    <span v-else />

                    <span class="text-xs text-gray-500 dark:text-neutral-400">
                        Page {{ pagination.current_page }} of
                        {{ pagination.last_page }}
                    </span>

                    <Link
                        v-if="pagination.next_page_url"
                        :href="pagination.next_page_url"
                        class="text-sm text-gray-600 dark:text-neutral-400 hover:text-gray-900 dark:hover:text-neutral-100"
                    >
                        {{ __('Older') }} &rarr;
                    </Link>
                    <span v-else />
                </div>
            </div>
        </div>

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
                        <ExpenseForm :form="form" :categories="categories" />
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
