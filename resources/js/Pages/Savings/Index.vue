<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import CurrencyToggle from '@/Components/CurrencyToggle.vue';
import FloatingAddButton from '@/Components/FloatingAddButton.vue';
import ResponsiveDialog from '@/Components/ResponsiveDialog.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { CARD, EYEBROW, FIGURE, MUTED, TAP_TARGET } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import { trans } from '@/lib/i18n';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

/**
 * Savings, a month at a time — the same shape as the Budgets page.
 *
 * The headline is the all-time balance, because savings carry over: what is
 * put aside in September is still there in October. Under it, this month's
 * deposits and withdrawals against what was planned for the month, then the
 * ledger that produced them.
 */
const props = defineProps({
    // SavingsSummary::forMonth for the month being viewed.
    summary: { type: Object, required: true },
    // The stored plan row, or null when the month has none.
    plan: { type: Object, default: null },
    entries: { type: Array, default: () => [] },
    month: { type: String, required: true },
    prev_month: { type: String, required: true },
    next_month: { type: String, required: true },
    // Month labels are built server-side so they follow the app locale.
    months: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ create: false, createEntry: false }) },
});

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

const riel = (usd) => formatRiel(usd, khrPerUsd.value);

const dateFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

function formatDate(date) {
    const [year, month, day] = date.split('-').map(Number);

    return dateFormatter.format(new Date(year, month - 1, day));
}

function formatMonth(month) {
    const [year, m] = month.split('-').map(Number);

    return new Date(year, m - 1, 1).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    });
}

// 'YYYY-MM' is one value on the wire but two controls on screen.
const monthPart = computed(() => props.month.split('-')[1]);
const yearPart = computed(() => props.month.split('-')[0]);

const yearOptions = computed(() =>
    props.years.map((year) => ({ value: String(year), label: String(year) })),
);

function visit(month) {
    router.get(route('savings.index', { month }), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

const goToMonth = (value) => visit(`${yearPart.value}-${value}`);
const goToYear = (value) => visit(`${value}-${monthPart.value}`);

// 'met' | 'close' | 'ok' — how the month's plan is going.
const barClass = computed(() =>
    ({
        met: 'bg-green-500',
        close: 'bg-emerald-400',
    })[props.summary.status] ?? 'bg-primary',
);

const percentClass = computed(() =>
    ({
        met: 'text-green-600 dark:text-green-400',
        close: 'text-emerald-600 dark:text-emerald-400',
    })[props.summary.status] ?? MUTED,
);

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const showPlanDialog = ref(false);

const form = useForm({
    month: props.month,
    amount: '',
    // What the amount is typed in. Stored as USD either way — see Currency.
    currency: defaultCurrency,
});

/**
 * A stored USD plan, written in the currency the field is about to start on.
 * Mirrors the Budgets dialog — see the note there for why this converts rather
 * than forcing the label to USD.
 */
function amountIn(usd, currency) {
    if (usd === null || usd === undefined) {
        return '';
    }

    return currency === 'KHR'
        ? String(Math.round(Number(usd) * khrPerUsd.value))
        : String(usd);
}

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

function openPlan() {
    form.month = props.month;
    form.currency = defaultCurrency;
    form.amount = amountIn(props.plan?.amount ?? null, defaultCurrency);
    form.clearErrors();
    showPlanDialog.value = true;
}

function submitPlan() {
    form.post(route('savings.plan.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showPlanDialog.value = false;
        },
    });
}

// What is awaiting confirmation: the month's plan, or one ledger line.
const confirming = ref(null);
const deleting = ref(false);

function confirmClearPlan() {
    if (!props.plan) {
        return;
    }

    confirming.value = { kind: 'plan' };
}

const confirmDeleteEntry = (entry) => {
    confirming.value = { kind: 'entry', entry };
};

function runDelete() {
    if (!confirming.value) {
        return;
    }

    const url =
        confirming.value.kind === 'plan'
            ? route('savings.plan.destroy', props.plan.uuid)
            : route('savings.entries.destroy', confirming.value.entry.uuid);

    deleting.value = true;

    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

const isEmpty = computed(() => props.entries.length === 0);
</script>

<template>
    <Head :title="trans('Savings')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ formatMonth(month) }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ __('Savings') }}
                    </h1>
                </div>

                <div class="flex min-w-0 max-w-full items-center gap-2">
                    <Link
                        :href="route('savings.index', { month: prev_month })"
                        preserve-scroll
                        :class="[
                            TAP_TARGET,
                            'grid size-9 shrink-0 place-items-center rounded-full border border-neutral-200/80 bg-white/60 text-neutral-500 backdrop-blur-xl transition hover:bg-neutral-100 hover:text-neutral-900 dark:border-white/10 dark:bg-neutral-900/50 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
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
                        trigger-class="h-9 min-w-0 flex-1 rounded-full border border-neutral-200/80 bg-white/60 px-3.5 text-sm font-semibold backdrop-blur-xl max-sm:h-11 sm:w-[7.5rem] sm:flex-none dark:border-white/10 dark:bg-neutral-900/50"
                        content-class="w-44"
                        align="start"
                        @update:model-value="goToMonth"
                    />

                    <SearchableSelect
                        :model-value="yearPart"
                        :options="yearOptions"
                        :label="__('Year')"
                        :searchable="false"
                        trigger-class="h-9 min-w-0 shrink rounded-full border border-neutral-200/80 bg-white/60 px-3.5 text-sm font-semibold tabular-nums backdrop-blur-xl max-sm:h-11 sm:w-[5.5rem] sm:shrink-0 dark:border-white/10 dark:bg-neutral-900/50"
                        content-class="w-32"
                        align="start"
                        @update:model-value="goToYear"
                    />

                    <Link
                        :href="route('savings.index', { month: next_month })"
                        preserve-scroll
                        :class="[
                            TAP_TARGET,
                            'grid size-9 shrink-0 place-items-center rounded-full border border-neutral-200/80 bg-white/60 text-neutral-500 backdrop-blur-xl transition hover:bg-neutral-100 hover:text-neutral-900 dark:border-white/10 dark:bg-neutral-900/50 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
                        ]"
                        :aria-label="__('Next month')"
                    >
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-4 pb-8 pt-2">
            <!-- The balance leads: it is the figure that answers "how much do
                 I have put aside", which no single month can. -->
            <div :class="[CARD, 'p-5']">
                <p :class="EYEBROW">{{ __('Total saved') }}</p>
                <div class="mt-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <span>
                        <span :class="FIGURE">{{ money.format(summary.total_saved) }}</span>
                        <span class="ms-2 text-sm" :class="MUTED">{{ riel(summary.total_saved) }}</span>
                    </span>
                    <span class="text-sm font-medium" :class="percentClass">
                        {{ summary.percent }}%
                    </span>
                </div>

                <div class="mt-4 mb-1.5 flex items-baseline justify-between text-sm">
                    <span class="text-gray-900 dark:text-neutral-100">
                        {{ money.format(summary.saved_this_month) }}
                        <span class="text-gray-500 dark:text-neutral-400">
                            {{ __('of :amount this month', { amount: money.format(summary.planned) }) }}
                        </span>
                    </span>
                    <Button
                        v-if="can.create"
                        variant="outline"
                        size="xs"
                        class="rounded-xl max-sm:h-8"
                        @click="openPlan"
                    >
                        {{ plan ? __('Edit plan') : __('Set plan') }}
                    </Button>
                </div>

                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
                    role="progressbar"
                    :aria-valuenow="summary.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="h-full rounded-full"
                        :class="barClass"
                        :style="{ width: `${summary.percent}%` }"
                    />
                </div>

                <p class="mt-2 flex flex-wrap justify-between gap-x-3 text-xs" :class="MUTED">
                    <span v-if="summary.planned > 0">
                        {{ __(':amount to go', { amount: money.format(summary.remaining) }) }}
                    </span>
                    <span v-else>{{ __('No savings plan set for this month.') }}</span>
                    <button
                        v-if="plan && can.create"
                        type="button"
                        class="underline-offset-2 hover:underline"
                        @click="confirmClearPlan"
                    >
                        {{ __('Clear plan') }}
                    </button>
                </p>
            </div>

            <div v-if="isEmpty" :class="[CARD, 'p-10 text-center']">
                <p class="text-sm text-gray-600 dark:text-neutral-400">
                    {{ __('Nothing put aside this month yet.') }}
                </p>
                <Button
                    v-if="can.createEntry"
                    :as="Link"
                    :href="route('savings.entries.create')"
                    class="mt-4"
                    size="sm"
                >
                    {{ __('Add entry') }}
                </Button>
            </div>

            <!-- One row per line of the ledger: deposits in, withdrawals out. -->
            <ul v-else :class="[CARD, 'divide-y divide-gray-100 dark:divide-neutral-800']">
                <li
                    v-for="entry in entries"
                    :key="entry.uuid"
                    class="flex items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p
                            class="text-sm font-semibold tabular-nums"
                            :class="
                                entry.type === 'withdraw'
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-green-600 dark:text-green-400'
                            "
                        >
                            {{ entry.type === 'withdraw' ? '−' : '+' }}{{ money.format(entry.amount) }}
                        </p>
                        <p class="mt-0.5 truncate text-xs" :class="MUTED">
                            {{ formatDate(entry.saved_on) }}
                            <template v-if="entry.note"> · {{ entry.note }}</template>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            :as="Link"
                            :href="route('savings.entries.edit', entry.uuid)"
                            variant="outline"
                            size="xs"
                            class="rounded-xl max-sm:h-8"
                        >
                            {{ __('Edit') }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="xs"
                            class="rounded-xl max-sm:h-8"
                            @click="confirmDeleteEntry(entry)"
                        >
                            {{ __('Delete') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </div>

        <FloatingAddButton
            v-if="can.createEntry"
            :href="route('savings.entries.create')"
            :label="__('Add entry')"
        />

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirming?.kind === 'plan' ? __('Clear this plan?') : __('Delete this entry?')"
            :description="
                confirming?.kind === 'plan'
                    ? __('The plan for this month will be removed. The money you put aside is not affected.')
                    : __('This line is removed from the ledger and the balance changes with it.')
            "
            :confirm-label="confirming?.kind === 'plan' ? __('Clear') : __('Delete')"
            :cancel-label="__('Cancel')"
            :processing="deleting"
            :processing-label="__('Removing…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="runDelete"
        />

        <ResponsiveDialog v-model:open="showPlanDialog" content-class="sm:max-w-sm">
            <form @submit.prevent="submitPlan">
                <DialogHeader>
                    <DialogTitle>{{ __('Savings plan') }}</DialogTitle>
                    <DialogDescription>{{ formatMonth(month) }}</DialogDescription>
                </DialogHeader>

                <div class="py-4">
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
                        min="0"
                        inputmode="decimal"
                        :placeholder="form.currency === 'KHR' ? '0' : '0.00'"
                    />

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
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl max-sm:hidden"
                        @click="showPlanDialog = false"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button type="submit" class="rounded-xl" :disabled="form.processing">
                        {{ __('Save') }}
                    </Button>
                </DialogFooter>
            </form>
        </ResponsiveDialog>
    </AuthenticatedLayout>
</template>
