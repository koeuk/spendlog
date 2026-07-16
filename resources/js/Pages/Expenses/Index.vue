<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExpenseForm from '@/Components/ExpenseForm.vue';
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
});

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

    return isToday ? 'Today' : dayFormatter.format(value);
}

const isEmpty = computed(() => props.days.length === 0);
</script>

<template>
    <Head title="Expenses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Expenses
                </h2>
                <Button size="sm" @click="openCreate">Add expense</Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="isEmpty"
                    class="rounded-lg bg-white p-10 text-center shadow-sm"
                >
                    <p class="text-sm text-gray-600">
                        No expenses yet, add your first one.
                    </p>
                    <Button class="mt-4" size="sm" @click="openCreate">
                        Add expense
                    </Button>
                </div>

                <div
                    v-for="day in days"
                    :key="day.date"
                    class="overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-100 px-4 py-3"
                    >
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ formatDay(day.date) }}
                        </h3>
                        <span class="text-sm font-semibold text-gray-800">
                            {{ money.format(day.total) }}
                        </span>
                    </div>

                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="expense in day.expenses"
                            :key="expense.uuid"
                            class="group flex items-center gap-3 px-4 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ expense.item }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ expense.category }}
                                </p>
                            </div>

                            <span class="text-sm tabular-nums text-gray-900">
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
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                    :disabled="deleting === expense.uuid"
                                    @click="destroy(expense)"
                                >
                                    Delete
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
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        &larr; Newer
                    </Link>
                    <span v-else />

                    <span class="text-xs text-gray-500">
                        Page {{ pagination.current_page }} of
                        {{ pagination.last_page }}
                    </span>

                    <Link
                        v-if="pagination.next_page_url"
                        :href="pagination.next_page_url"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Older &rarr;
                    </Link>
                    <span v-else />
                </div>
            </div>
        </div>

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? 'Edit expense' : 'Add expense' }}
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
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save' : 'Add' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
