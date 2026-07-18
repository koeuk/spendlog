<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { CARD, CARD_ALERT, EYEBROW } from '@/lib/appStyles';
import CategoryBadge from '@/Components/CategoryBadge.vue';
import CategoryPicker from '@/Components/CategoryPicker.vue';
import { useNavigating } from '@/composables/useNavigating';
import { trans } from '@/lib/i18n';
import { Skeleton } from '@/Components/ui/skeleton';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { ChevronLeft, ChevronRight, TriangleAlert } from 'lucide-vue-next';
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
    // Month labels are built server-side so they follow the app locale.
    months: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
});

// 'YYYY-MM' is one value on the wire but two controls on screen.
const monthPart = computed(() => props.month.split('-')[1]);
const yearPart = computed(() => props.month.split('-')[0]);

// Only categories the user actually spent in this month — the long tail of
// untouched categories is noise on this page. A category with a budget set but
// no spend still shows, so that budget stays visible and clearable rather than
// vanishing off the page.
const visibleCategories = computed(() =>
    props.summary.categories.filter(
        (category) => category.spent > 0 || category.budget !== null,
    ),
);

function visit(month) {
    router.get(route('budgets.index', { month }), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function goToMonth(value) {
    visit(`${yearPart.value}-${value}`);
}

function goToYear(value) {
    visit(`${value}-${monthPart.value}`);
}

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
    // CategoryPicker writes this key; budgets.store ignores it (creation is
    // disabled there), but it has to exist for the picker to bind to.
    new_category: '',
});

function openEdit(row, categoryUuid = null) {
    target.value = row;
    choosingCategory.value = false;
    form.category_uuid = categoryUuid;
    form.month = props.month;
    form.amount = row.budget !== null ? String(row.budget) : '';
    form.clearErrors();
    showDialog.value = true;
}

/*
 * Setting a budget from the header rather than a row.
 *
 * The list only shows categories with spend or an existing budget, so a
 * category the user has not touched yet has no row to click — this is the only
 * way to budget for one ahead of spending anything.
 */
const choosingCategory = ref(false);

// Only categories without a budget yet. One that already has one has its own
// row with an Edit button, so listing it here would be a second way to do the
// same thing — and the row is the one that shows what the budget currently is.
const budgetableCategories = computed(() =>
    props.summary.categories.filter((category) => category.budget === null),
);

function openAdd() {
    target.value = null;
    choosingCategory.value = true;
    form.category_uuid = null;
    form.month = props.month;
    form.amount = '';
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

// The budget awaiting confirmation, plus the label the prompt names. The
// overall row has no category, so it needs a name of its own.
const confirming = ref(null);
const clearing = ref(false);

function confirmClear(row, label) {
    if (!row.budget_uuid) {
        return;
    }

    confirming.value = { row, label };
}

function clearBudget() {
    if (!confirming.value) {
        return;
    }

    clearing.value = true;

    router.delete(route('budgets.destroy', confirming.value.row.budget_uuid), {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            clearing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Budgets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ formatMonth(month) }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ __('Budgets') }}
                    </h1>
                </div>

                <!--
                    The arrows stay for stepping to an adjacent month, which is
                    the common case; the selects exist for the far ones, where
                    stepping would be a dozen round trips.
                -->
                <div
                    class="flex items-center gap-1 rounded-full border border-neutral-200/80 bg-white/60 p-1 backdrop-blur-xl backdrop-saturate-150 dark:border-white/10 dark:bg-neutral-900/50"
                >
                    <Link
                        :href="route('budgets.index', { month: prev_month })"
                        preserve-scroll
                        class="grid size-8 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                        :aria-label="__('Previous month')"
                    >
                        <ChevronLeft class="size-4" />
                    </Link>

                    <Select :model-value="monthPart" @update:model-value="goToMonth">
                        <SelectTrigger
                            class="h-8 w-[7.5rem] rounded-full border-0 bg-transparent px-3 text-sm font-semibold shadow-none focus-visible:ring-0 dark:bg-transparent"
                            :aria-label="__('Month')"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="m in months" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="yearPart" @update:model-value="goToYear">
                        <SelectTrigger
                            class="h-8 w-[5.5rem] rounded-full border-0 bg-transparent px-3 text-sm font-semibold tabular-nums shadow-none focus-visible:ring-0 dark:bg-transparent"
                            :aria-label="__('Year')"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="y in years" :key="y" :value="String(y)">
                                {{ y }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Link
                        :href="route('budgets.index', { month: next_month })"
                        preserve-scroll
                        class="grid size-8 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                        :aria-label="__('Next month')"
                    >
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <!-- Width and gutters come from the layout's one container, so the
                 column never resizes when navigating between pages. -->
            <div class="space-y-4">
                <!-- Only when the overall budget is blown. A category going over
                     on its own keeps the red figure on its own row: a banner for
                     each would train the eye to skip them all. -->
                <div
                    v-if="summary.overall.status === 'over'"
                    role="alert"
                    :class="[CARD_ALERT, 'flex items-start gap-3 p-4']"
                >
                    <TriangleAlert
                        class="mt-0.5 size-5 shrink-0 text-red-600 dark:text-red-400"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-red-900 dark:text-red-200">
                            {{ __('Over budget this month') }}
                        </p>
                        <p class="mt-0.5 text-xs leading-relaxed text-red-700 dark:text-red-300">
                            {{
                                __('You have spent :spent of your :budget overall budget — :over over.', {
                                    spent: money.format(summary.overall.spent),
                                    budget: money.format(summary.overall.budget),
                                    over: money.format(Math.abs(summary.overall.remaining)),
                                })
                            }}
                        </p>
                    </div>
                </div>

                <!-- Overall -->
                <div :class="[CARD, 'p-5']">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100">
                                {{ __('Overall budget') }}
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-neutral-400">
                                {{ __('Across every category this month.') }}
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="outline" size="sm" @click="openEdit(summary.overall, null)">
                                {{ summary.overall.budget !== null ? __('Edit') : __('Set a budget') }}
                            </Button>
                            <Button
                                v-if="summary.overall.budget !== null"
                                variant="ghost"
                                size="sm"
                                class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                @click="confirmClear(summary.overall, trans('Overall budget'))"
                            >
                                {{ __('Clear') }}
                            </Button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1.5 flex items-baseline justify-between text-sm">
                            <span class="font-medium text-gray-900 dark:text-neutral-100">
                                {{ money.format(summary.overall.spent) }}
                                <span
                                    v-if="summary.overall.budget !== null"
                                    class="font-normal text-gray-500 dark:text-neutral-400"
                                >
                                    {{ __('of :amount', { amount: money.format(summary.overall.budget) }) }}
                                </span>
                            </span>
                            <span
                                v-if="summary.overall.percent !== null"
                                class="text-xs font-medium"
                                :class="
                                    summary.overall.status === 'over'
                                        ? 'text-red-600 dark:text-red-400'
                                        : summary.overall.status === 'warning'
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-gray-500 dark:text-neutral-400'
                                "
                            >
                                {{ summary.overall.percent }}%
                            </span>
                            <span v-else class="text-xs text-gray-400 dark:text-neutral-500">{{ __('No budget set') }}</span>
                        </div>
                        <BudgetProgress
                            :status="summary.overall.status"
                            :bar-percent="summary.overall.bar_percent"
                        />
                        <p
                            v-if="summary.overall.remaining !== null"
                            class="mt-1.5 text-xs"
                            :class="summary.overall.remaining < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-neutral-400'"
                        >
                            {{
                                summary.overall.remaining < 0
                                    ? __(':amount over budget', {
                                          amount: money.format(Math.abs(summary.overall.remaining)),
                                      })
                                    : __(':amount left', {
                                          amount: money.format(summary.overall.remaining),
                                      })
                            }}
                        </p>
                    </div>
                </div>

                <!-- Per category -->
                <div :class="[CARD, 'overflow-hidden']">
                    <div
                        class="flex items-center justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 px-5 py-3"
                    >
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100">{{ __('By category') }}</h3>
                        <Button size="sm" @click="openAdd">{{ __('Set budget') }}</Button>
                    </div>

                    <ul v-if="navigating" class="divide-y divide-gray-100 dark:divide-neutral-800" aria-busy="true">
                        <li v-for="n in 5" :key="n" class="px-5 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2.5">
                                    <Skeleton class="size-7 rounded-full" />
                                    <Skeleton class="h-4 w-20" />
                                </div>
                                <Skeleton class="h-4 w-10" />
                            </div>
                            <Skeleton class="mt-3 h-2 w-full rounded-full" />
                        </li>
                    </ul>

                    <p
                        v-else-if="visibleCategories.length === 0"
                        class="px-5 py-8 text-center text-sm text-gray-400 dark:text-neutral-500"
                    >
                        {{ __('Nothing spent this month yet.') }}
                    </p>

                    <ul v-else class="divide-y divide-gray-100 dark:divide-neutral-800">
                        <li
                            v-for="category in visibleCategories"
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
                                        {{ category.budget !== null ? __('Edit') : __('Set') }}
                                    </Button>
                                    <Button
                                        v-if="category.budget !== null"
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                        @click="confirmClear(category, category.name)"
                                    >
                                        {{ __('Clear') }}
                                    </Button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="mb-1.5 flex items-baseline justify-between text-sm">
                                    <span class="text-gray-900 dark:text-neutral-100">
                                        {{ money.format(category.spent) }}
                                        <span
                                            v-if="category.budget !== null"
                                            class="text-gray-500 dark:text-neutral-400"
                                        >
                                            {{ __('of :amount', { amount: money.format(category.budget) }) }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="category.percent !== null"
                                        class="text-xs font-medium"
                                        :class="
                                            category.status === 'over'
                                                ? 'text-red-600 dark:text-red-400'
                                                : category.status === 'warning'
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'text-gray-500 dark:text-neutral-400'
                                        "
                                    >
                                        {{ category.percent }}%
                                    </span>
                                    <span v-else class="text-xs text-gray-400 dark:text-neutral-500">
                                        {{ __('No budget set') }}
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

        <ConfirmDialog
            :open="confirming !== null"
            :title="__('Clear this budget?')"
            :description="
                confirming
                    ? __('The budget for &quot;:name&quot; will be removed for this month. Spending is not affected.', {
                          name: confirming.label,
                      })
                    : ''
            "
            :confirm-label="__('Clear')"
            :cancel-label="__('Cancel')"
            :processing="clearing"
            :processing-label="__('Clearing…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="clearBudget"
        />

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-sm">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ choosingCategory || form.category_uuid ? __('Category budget') : __('Overall budget') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ formatMonth(month) }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="py-4">
                        <!-- Only when opened from the header: a row already
                             names its own category. -->
                        <div v-if="choosingCategory" class="mb-4">
                            <Label for="category">{{ __('Category') }}</Label>
                            <!-- can-create is off: budgets.store only accepts an
                                 existing category_uuid, so offering to name a new
                                 one would promise something the server rejects. -->
                            <CategoryPicker
                                :form="form"
                                :categories="budgetableCategories"
                                :can-create="false"
                            />
                        </div>

                        <Label for="amount">{{ __('Amount') }}</Label>
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
                        <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.amount }}
                        </p>
                        <p v-if="form.errors.month" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.month }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showDialog = false">
                            {{ __('Cancel') }}
                        </Button>
                        <!-- Without a category the request would set the overall
                             budget, which is not what this dialog offered. -->
                        <Button
                            type="submit"
                            :disabled="form.processing || (choosingCategory && !form.category_uuid)"
                        >
                            {{ __('Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
