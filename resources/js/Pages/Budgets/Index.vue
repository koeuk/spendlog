<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import CategoryBadge from '@/Components/CategoryBadge.vue';
import { useNavigating } from '@/composables/useNavigating';
import { Skeleton } from '@/Components/ui/skeleton';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const props = defineProps({
    summary: { type: Object, required: true },
    month: { type: String, required: true },
    prev_month: { type: String, required: true },
    next_month: { type: String, required: true },
});

const { navigating } = useNavigating();

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

const showDialog = ref(false);
// null target = the overall budget.
const target = ref(null);

const form = useForm({
    category_uuid: null,
    month: props.month,
    amount: '',
});

function openEdit(row, categoryUuid = null) {
    target.value = row;
    form.category_uuid = categoryUuid;
    form.month = props.month;
    form.amount = row.budget !== null ? String(row.budget) : '';
    form.clearErrors();
    showDialog.value = true;
}

function submit() {
    form.post(route('budgets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
        },
    });
}

function clearBudget(row) {
    if (!row.budget_uuid) {
        return;
    }

    router.delete(route('budgets.destroy', row.budget_uuid), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Budgets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Budgets
                </h2>
                <div class="flex items-center gap-1">
                    <Link
                        :href="route('budgets.index', { month: prev_month })"
                        class="rounded-md px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"
                    >
                        &larr;
                    </Link>
                    <span class="min-w-36 text-center text-sm font-medium text-gray-700">
                        {{ formatMonth(month) }}
                    </span>
                    <Link
                        :href="route('budgets.index', { month: next_month })"
                        class="rounded-md px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"
                    >
                        &rarr;
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- Overall -->
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">
                                Overall budget
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Across every category this month.
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="outline" size="sm" @click="openEdit(summary.overall, null)">
                                {{ summary.overall.budget !== null ? 'Edit' : 'Set budget' }}
                            </Button>
                            <Button
                                v-if="summary.overall.budget !== null"
                                variant="ghost"
                                size="sm"
                                class="text-red-600 hover:text-red-700"
                                @click="clearBudget(summary.overall)"
                            >
                                Clear
                            </Button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1.5 flex items-baseline justify-between text-sm">
                            <span class="font-medium text-gray-900">
                                {{ money.format(summary.overall.spent) }}
                                <span
                                    v-if="summary.overall.budget !== null"
                                    class="font-normal text-gray-500"
                                >
                                    of {{ money.format(summary.overall.budget) }}
                                </span>
                            </span>
                            <span
                                v-if="summary.overall.percent !== null"
                                class="text-xs font-medium"
                                :class="
                                    summary.overall.status === 'over'
                                        ? 'text-red-600'
                                        : summary.overall.status === 'warning'
                                          ? 'text-amber-600'
                                          : 'text-gray-500'
                                "
                            >
                                {{ summary.overall.percent }}%
                            </span>
                            <span v-else class="text-xs text-gray-400">No budget set</span>
                        </div>
                        <BudgetProgress
                            :status="summary.overall.status"
                            :bar-percent="summary.overall.bar_percent"
                        />
                        <p
                            v-if="summary.overall.remaining !== null"
                            class="mt-1.5 text-xs"
                            :class="summary.overall.remaining < 0 ? 'text-red-600' : 'text-gray-500'"
                        >
                            {{
                                summary.overall.remaining < 0
                                    ? `${money.format(Math.abs(summary.overall.remaining))} over budget`
                                    : `${money.format(summary.overall.remaining)} left`
                            }}
                        </p>
                    </div>
                </div>

                <!-- Per category -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">By category</h3>
                    </div>

                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="category in summary.categories"
                            :key="category.uuid"
                            class="px-5 py-4"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <CategoryBadge
                                    :name="category.name"
                                    :color="category.color"
                                    :icon="category.icon"
                                />
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEdit(category, category.uuid)"
                                    >
                                        {{ category.budget !== null ? 'Edit' : 'Set' }}
                                    </Button>
                                    <Button
                                        v-if="category.budget !== null"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700"
                                        @click="clearBudget(category)"
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="mb-1.5 flex items-baseline justify-between text-sm">
                                    <span class="text-gray-900">
                                        {{ money.format(category.spent) }}
                                        <span
                                            v-if="category.budget !== null"
                                            class="text-gray-500"
                                        >
                                            of {{ money.format(category.budget) }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="category.percent !== null"
                                        class="text-xs font-medium"
                                        :class="
                                            category.status === 'over'
                                                ? 'text-red-600'
                                                : category.status === 'warning'
                                                  ? 'text-amber-600'
                                                  : 'text-gray-500'
                                        "
                                    >
                                        {{ category.percent }}%
                                    </span>
                                    <span v-else class="text-xs text-gray-400">
                                        No budget set
                                    </span>
                                </div>
                                <BudgetProgress
                                    :status="category.status"
                                    :bar-percent="category.bar_percent"
                                />
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-sm">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ form.category_uuid ? 'Category budget' : 'Overall budget' }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ formatMonth(month) }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="py-4">
                        <Label for="amount">Amount</Label>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            class="mt-1"
                            type="number"
                            step="0.01"
                            min="0"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                        <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">
                            {{ form.errors.amount }}
                        </p>
                        <p v-if="form.errors.month" class="mt-1 text-sm text-red-600">
                            {{ form.errors.month }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showDialog = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
