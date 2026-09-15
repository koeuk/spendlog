<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { CARD, EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import { trans } from '@/lib/i18n';
import { Button } from '@/Components/ui/button';

/**
 * One borrowing: what is still owed, the terms, and the ledger of repayments
 * that produced the figure. Every action on a debt starts here.
 */
const props = defineProps({
    borrowing: { type: Object, required: true },
    // Newest first.
    repayments: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ update: false, delete: false }) },
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

// What is awaiting confirmation: the borrowing itself, or one ledger line.
const confirming = ref(null);
const deleting = ref(false);

const confirmDeleteBorrowing = () => {
    confirming.value = { kind: 'borrowing' };
};

const confirmDeleteRepayment = (repayment) => {
    confirming.value = { kind: 'repayment', repayment };
};

const confirmTitle = computed(() =>
    confirming.value?.kind === 'repayment'
        ? trans('Delete this repayment?')
        : trans('Delete this borrowing?'),
);

const confirmDescription = computed(() => {
    if (!confirming.value) {
        return '';
    }

    if (confirming.value.kind === 'repayment') {
        return trans(':amount on :date will be removed. This cannot be undone.', {
            amount: money.format(confirming.value.repayment.amount),
            date: formatDate(confirming.value.repayment.paid_on),
        });
    }

    return trans(':lender (:amount) and every repayment against it will be removed. This cannot be undone.', {
        lender: props.borrowing.lender,
        amount: money.format(props.borrowing.amount),
    });
});

function runDelete() {
    if (!confirming.value) {
        return;
    }

    const url =
        confirming.value.kind === 'repayment'
            ? route('borrowings.repayments.destroy', {
                  borrowing: props.borrowing.uuid,
                  repayment: confirming.value.repayment.uuid,
              })
            : route('borrowings.destroy', props.borrowing.uuid);

    deleting.value = true;

    router.delete(url, {
        preserveScroll: true,
        // Closed on success, not on click: a failed delete should leave the
        // prompt up rather than vanish with the row still there.
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

const statusClass = computed(() => {
    if (props.borrowing.settled) {
        return 'text-green-600 dark:text-green-400';
    }

    return props.borrowing.overdue ? 'text-red-600 dark:text-red-400' : MUTED;
});
</script>

<template>
    <Head :title="borrowing.lender" />

    <FormScreenLayout
        :back-href="route('borrowings.index')"
        :title="borrowing.lender"
        :back-label="__('Back to borrowing')"
    >
        <div class="space-y-4">
            <div :class="[CARD, 'p-5']">
                <p :class="EYEBROW">
                    {{ borrowing.settled ? __('Paid back') : __('Still owed') }}
                    · {{ borrowing.lender_type_label }}
                </p>
                <p class="mt-1">
                    <span :class="FIGURE" class="text-3xl sm:text-4xl">
                        {{ money.format(borrowing.settled ? borrowing.amount : borrowing.remaining) }}
                    </span>
                </p>
                <p class="mt-1 text-sm" :class="MUTED">
                    {{ riel(borrowing.settled ? borrowing.amount : borrowing.remaining) }}
                </p>

                <template v-if="!borrowing.settled">
                    <p class="mt-3 text-sm" :class="MUTED">
                        {{ __('Repaid :repaid of :amount', {
                            repaid: money.format(borrowing.repaid),
                            amount: money.format(borrowing.amount),
                        }) }}
                    </p>
                    <div
                        class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
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
                </template>

                <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3 sm:block">
                        <dt :class="MUTED">{{ __('Borrowed on') }}</dt>
                        <dd class="font-medium">{{ formatDate(borrowing.borrowed_on) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 sm:block">
                        <dt :class="MUTED">{{ __('Due on') }}</dt>
                        <dd class="font-medium" :class="statusClass">
                            <template v-if="!borrowing.due_on">{{ __('No due date') }}</template>
                            <template v-else-if="borrowing.overdue">
                                {{ __('Overdue since :date', { date: formatDate(borrowing.due_on) }) }}
                            </template>
                            <template v-else>{{ formatDate(borrowing.due_on) }}</template>
                        </dd>
                    </div>
                    <div v-if="borrowing.note" class="sm:col-span-2">
                        <dt :class="MUTED">{{ __('Note') }}</dt>
                        <dd class="whitespace-pre-line font-medium">{{ borrowing.note }}</dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-2">
                    <!-- Repaying is what an open debt is for, so it leads. -->
                    <Button
                        v-if="can.update && !borrowing.settled"
                        :as="Link"
                        :href="route('borrowings.repayments.create', borrowing.uuid)"
                        class="rounded-xl max-sm:h-11 max-sm:flex-1"
                    >
                        {{ __('Record repayment') }}
                    </Button>
                    <Button
                        v-if="can.update"
                        :as="Link"
                        :href="route('borrowings.edit', borrowing.uuid)"
                        variant="outline"
                        class="rounded-xl max-sm:h-11"
                    >
                        {{ __('Edit') }}
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="destructive"
                        class="rounded-xl max-sm:h-11"
                        @click="confirmDeleteBorrowing"
                    >
                        {{ __('Delete') }}
                    </Button>
                </div>
            </div>

            <div :class="[CARD, 'overflow-hidden']">
                <div class="flex items-center justify-between gap-3 px-5 pt-5">
                    <p :class="EYEBROW">{{ __('Repayments') }}</p>
                    <span v-if="repayments.length" class="text-xs" :class="MUTED">
                        {{ money.format(borrowing.repaid) }}
                    </span>
                </div>

                <p v-if="!repayments.length" class="p-5 pt-3 text-sm" :class="MUTED">
                    {{ __('No repayments yet.') }}
                </p>

                <ul v-else class="mt-3 divide-y divide-gray-100 border-t border-gray-100 dark:divide-neutral-800 dark:border-neutral-800">
                    <li
                        v-for="repayment in repayments"
                        :key="repayment.uuid"
                        class="flex items-center justify-between gap-3 px-5 py-3"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold tabular-nums text-green-600 dark:text-green-400">
                                −{{ money.format(repayment.amount) }}
                            </p>
                            <p class="mt-0.5 truncate text-xs" :class="MUTED">
                                {{ formatDate(repayment.paid_on) }}
                                <template v-if="repayment.note"> · {{ repayment.note }}</template>
                            </p>
                        </div>

                        <Button
                            v-if="can.update"
                            variant="destructive"
                            size="xs"
                            class="shrink-0 rounded-xl max-sm:h-8"
                            @click="confirmDeleteRepayment(repayment)"
                        >
                            {{ __('Delete') }}
                        </Button>
                    </li>
                </ul>
            </div>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirmTitle"
            :description="confirmDescription"
            :confirm-label="__('Delete')"
            :cancel-label="__('Cancel')"
            :processing="deleting"
            :processing-label="__('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="runDelete"
        />
    </FormScreenLayout>
</template>
