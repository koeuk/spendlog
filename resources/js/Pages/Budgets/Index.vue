<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BudgetProgress from '@/Components/BudgetProgress.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { CARD, CARD_ALERT, EYEBROW, MUTED, TAP_TARGET } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import CategoryBadge from '@/Components/CategoryBadge.vue';
import CategoryPicker from '@/Components/CategoryPicker.vue';
import CurrencyToggle from '@/Components/CurrencyToggle.vue';
import { useNavigating } from '@/composables/useNavigating';
import { trans } from '@/lib/i18n';
import { Skeleton } from '@/Components/ui/skeleton';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { ChevronLeft, ChevronRight, TriangleAlert } from 'lucide-vue-next';
import ResponsiveDialog from '@/Components/ResponsiveDialog.vue';
import {
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

// years arrives as plain numbers; the picker wants {value, label}, and the value
// has to be a string so it compares equal to yearPart.
const yearOptions = computed(() =>
    props.years.map((year) => ({ value: String(year), label: String(year) })),
);

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

// Which currency an amount field starts on, set in Settings → Spending.
const defaultCurrency = usePage().props.default_currency ?? 'USD';

const showDialog = ref(false);
// null target = the overall budget.
const target = ref(null);

const form = useForm({
    category_uuid: null,
    month: props.month,
    amount: '',
    // What the amount is typed in. Stored as USD either way — see Currency.
    currency: defaultCurrency,
    // CategoryPicker writes this key; budgets.store ignores it (creation is
    // disabled there), but it has to exist for the picker to bind to.
    new_category: '',
});

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

// Stored amounts are USD; this is the same figure shown in riel beside them.
const riel = (usd) => formatRiel(usd, khrPerUsd.value);

/**
 * What a riel budget will actually be stored as.
 *
 * Mirrors App\Enums\Currency::toUsd — same divisor, same rounding — so the hint
 * matches the row that gets written. Nothing to say for a USD amount: it is
 * stored exactly as typed.
 */
const convertedPreview = computed(() => {
    if (form.currency !== 'KHR') {
        return '';
    }

    const amount = Number(form.amount);

    if (!Number.isFinite(amount) || amount <= 0) {
        return trans('Entered in riel, stored in US dollars.');
    }

    return trans('Stored as :amount', {
        amount: money.format(Math.round((amount / khrPerUsd.value) * 100) / 100),
    });
});

/**
 * A stored USD budget, written in the currency the field is about to start on.
 *
 * Budgets are always stored in USD, so prefilling the raw figure while the
 * toggle says KHR would label $0.02 as ៛0.02 — and saving it would divide it by
 * the rate a second time. The figure has to be converted to match the label, or
 * the label has to be forced to USD; this converts, so that an admin who set
 * riel as the default edits in riel like they entered in riel.
 *
 * Rounded to whole riel, the smallest unit actually in circulation, which also
 * makes the round-trip stable: riel → USD stores at 4 decimals (Currency::SCALE),
 * which is finer than one riel, so reopening the row returns the same number
 * rather than drifting a little further on every save.
 */
function amountIn(usd, currency) {
    if (usd === null) {
        return '';
    }

    return currency === 'KHR'
        ? String(Math.round(Number(usd) * khrPerUsd.value))
        : String(usd);
}

function openEdit(row, categoryUuid = null) {
    target.value = row;
    choosingCategory.value = false;
    form.category_uuid = categoryUuid;
    form.month = props.month;
    // Same configured currency as openAdd — editing a budget and setting one
    // should not disagree about which currency this app works in.
    form.currency = defaultCurrency;
    form.amount = amountIn(row.budget, defaultCurrency);
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
    // Unlike openEdit, there is no stored USD figure to preserve here, so a new
    // budget starts on the configured currency.
    form.currency = defaultCurrency;
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
                <!--
                    min-w-0 so the pill is allowed to shrink at all: as a flex
                    child it defaults to its min-content width, and the two
                    triggers inside were fixed-width, which put the whole pill at
                    294px against the 296px a 320px viewport leaves. It fitted by
                    2px in English and would not have in a locale with a longer
                    month name. Below sm the triggers share the space instead of
                    each demanding its own; from sm they are fixed again.
                -->
                <div
                    class="flex min-w-0 max-w-full items-center gap-1 rounded-full border border-neutral-200/80 bg-white/60 p-1 backdrop-blur-xl backdrop-saturate-150 dark:border-white/10 dark:bg-neutral-900/50"
                >
                    <Link
                        :href="route('budgets.index', { month: prev_month })"
                        preserve-scroll
                        :class="[
                            TAP_TARGET,
                            'grid size-8 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
                        ]"
                        :aria-label="__('Previous month')"
                    >
                        <ChevronLeft class="size-4" />
                    </Link>

                    <SearchableSelect
                        :model-value="monthPart"
                        :options="months"
                        :label="__('Month')"
                        :searchable="false"
                        trigger-class="h-8 min-w-0 flex-1 rounded-full border-0 bg-transparent px-3 text-sm font-semibold max-sm:h-11 sm:w-[7.5rem] sm:flex-none"
                        content-class="w-44"
                        align="start"
                        @update:model-value="goToMonth"
                    />

                    <SearchableSelect
                        :model-value="yearPart"
                        :options="yearOptions"
                        :label="__('Year')"
                        :searchable="false"
                        trigger-class="h-8 min-w-0 shrink rounded-full border-0 bg-transparent px-3 text-sm font-semibold tabular-nums max-sm:h-11 sm:w-[5.5rem] sm:shrink-0"
                        content-class="w-32"
                        align="start"
                        @update:model-value="goToYear"
                    />

                    <Link
                        :href="route('budgets.index', { month: next_month })"
                        preserve-scroll
                        :class="[
                            TAP_TARGET,
                            'grid size-8 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
                        ]"
                        :aria-label="__('Next month')"
                    >
                        <ChevronRight class="size-4" />
                    </Link>
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
                                variant="destructive"
                                size="sm"
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
                                <!-- The stored figures are USD; riel is the same
                                     amount at today's rate, so it trails them
                                     rather than standing as a second total. -->
                                <span class="ms-1 text-xs font-normal" :class="MUTED">
                                    <!-- The separating space is written out.
                                         Left as indentation before the
                                         interpolation it sat in a text node
                                         beginning with a newline, which Vue's
                                         condense mode drops — so this rendered
                                         "៛467,403of ៛3,280,000". -->
                                    {{ riel(summary.overall.spent) }}<template v-if="summary.overall.budget !== null">{{ ' ' }}{{ __('of :amount', { amount: riel(summary.overall.budget) }) }}</template>
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
                        <Button size="sm" class="rounded-xl" @click="openAdd">
                            {{ __('Set budget') }}
                        </Button>
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
                                        variant="secondary"
                                        size="sm"
                                        @click="openEdit(category, category.uuid)"
                                    >
                                        {{ category.budget !== null ? __('Edit') : __('Set') }}
                                    </Button>
                                    <Button
                                        v-if="category.budget !== null"
                                        variant="destructive"
                                        size="sm"
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
                                        <span class="ms-1 text-xs" :class="MUTED">
                                            {{ riel(category.spent) }}<template v-if="category.budget !== null">{{ ' ' }}{{ __('of :amount', { amount: riel(category.budget) }) }}</template>
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

        <ResponsiveDialog v-model:open="showDialog" content-class="sm:max-w-sm">
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

                    <div class="flex items-center justify-between gap-2">
                        <Label for="amount">{{ __('Amount') }}</Label>
                        <CurrencyToggle v-model="form.currency" />
                    </div>

                    <Input
                        id="amount"
                        v-model="form.amount"
                        class="mt-1"
                        type="number"
                        :step="form.currency === 'KHR' ? '100' : '0.01'"
                        :min="form.currency === 'KHR' ? '100' : '0'"
                        inputmode="decimal"
                        :placeholder="form.currency === 'KHR' ? '0' : '0.00'"
                    />

                    <!-- Only stored amounts are USD, so say what a riel figure
                         will become before it is saved rather than after. -->
                    <p v-if="convertedPreview" class="mt-1 text-xs" :class="MUTED">
                        {{ convertedPreview }}
                    </p>

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
        </ResponsiveDialog>
    </AuthenticatedLayout>
</template>
