<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDownToLine, ArrowUpFromLine, ChevronLeft } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { CARD, EYEBROW, FIGURE, MUTED } from '@/lib/appStyles';
import { formatRiel } from '@/lib/currency';
import { categoryColor } from '@/lib/categoryStyles';
import { trans } from '@/lib/i18n';
import { Button } from '@/Components/ui/button';

/**
 * One goal: how far along it is, and the ledger of what went in and out.
 *
 * The balance is never edited directly — a deposit or a withdrawal is a
 * line in the ledger, and the figure at the top is their sum.
 */
const props = defineProps({
    goal: { type: Object, required: true },
    // Newest first, capped server-side at the latest 100.
    entries: { type: Array, required: true },
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

const color = computed(() => categoryColor(props.goal.color));

// --- Deleting the goal -------------------------------------------------------

const confirmingGoal = ref(false);
const goalForm = useForm({});

function destroyGoal() {
    goalForm.delete(route('savings.destroy', props.goal.uuid), {
        onSuccess: () => {
            confirmingGoal.value = false;
        },
    });
}

// --- Deleting a ledger line --------------------------------------------------

// The entry awaiting confirmation, so the prompt can say what is about to go.
const confirmingEntry = ref(null);
const deletingEntry = ref(null);
const entryForm = useForm({});

function destroyEntry() {
    const entry = confirmingEntry.value;

    if (!entry) {
        return;
    }

    deletingEntry.value = entry.uuid;

    entryForm.delete(route('savings.entries.destroy', { goal: props.goal.uuid, entry: entry.uuid }), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingEntry.value = null;
        },
        onFinish: () => {
            deletingEntry.value = null;
        },
    });
}

function entryLabel(entry) {
    return entry.type === 'withdraw' ? trans('Withdrawal') : trans('Deposit');
}
</script>

<template>
    <Head :title="goal.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <Link
                        :href="route('savings.index')"
                        class="-ms-1 rounded-full p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                        :aria-label="__('Back to savings')"
                    >
                        <ChevronLeft class="size-5" />
                    </Link>
                    <span class="size-2.5 shrink-0 rounded-full" :class="color.dot" />
                    <h2 class="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                        {{ goal.name }}
                    </h2>
                </div>

                <div v-if="can.update || can.delete" class="flex items-center gap-2">
                    <Button
                        v-if="can.update"
                        :as="Link"
                        :href="route('savings.edit', goal.uuid)"
                        variant="secondary"
                        size="sm"
                    >
                        {{ __('Edit') }}
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="destructive"
                        size="sm"
                        @click="confirmingGoal = true"
                    >
                        {{ __('Delete') }}
                    </Button>
                </div>
            </div>
        </template>

        <div class="space-y-4 pb-8 pt-2">
            <div :class="[CARD, 'p-5']">
                <p :class="EYEBROW">{{ __('Saved so far') }}</p>
                <div class="mt-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <span>
                        <span :class="FIGURE">{{ money.format(goal.saved) }}</span>
                        <span class="ms-2 text-sm" :class="MUTED">
                            {{ __('of :amount target', { amount: money.format(goal.target_amount) }) }}
                        </span>
                    </span>
                    <span
                        v-if="goal.reached"
                        class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="categoryColor('green').badge"
                    >
                        {{ __('Reached') }}
                    </span>
                    <span v-else class="text-sm font-medium text-gray-600 dark:text-neutral-300">
                        {{ goal.percent }}%
                    </span>
                </div>
                <div
                    class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
                    role="progressbar"
                    :aria-valuenow="goal.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full rounded-full" :class="color.bar" :style="{ width: `${goal.percent}%` }" />
                </div>
                <p class="mt-2 flex flex-wrap justify-between gap-x-3 text-xs" :class="MUTED">
                    <span>
                        {{ riel(goal.saved) }}
                        <template v-if="!goal.reached">
                            · {{ __(':amount to go', { amount: money.format(goal.remaining) }) }}
                        </template>
                    </span>
                    <span v-if="goal.deadline">
                        {{ __('by :date', { date: formatDate(goal.deadline) }) }}
                    </span>
                </p>

                <!-- The two things anyone opens this page to do. Withdraw is
                     the quieter button: taking money out is the exception. -->
                <div v-if="can.update" class="mt-4 grid grid-cols-2 gap-2 sm:flex">
                    <Button
                        :as="Link"
                        :href="route('savings.entries.create', { goal: goal.uuid, type: 'deposit' })"
                        class="rounded-xl max-sm:h-11"
                    >
                        <ArrowDownToLine class="size-4" />
                        {{ __('Deposit') }}
                    </Button>
                    <Button
                        :as="Link"
                        :href="route('savings.entries.create', { goal: goal.uuid, type: 'withdraw' })"
                        variant="outline"
                        class="rounded-xl max-sm:h-11"
                        :disabled="goal.saved <= 0"
                    >
                        <ArrowUpFromLine class="size-4" />
                        {{ __('Withdraw') }}
                    </Button>
                </div>
            </div>

            <div :class="[CARD, 'overflow-hidden']">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-neutral-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100">{{ __('Ledger') }}</h3>
                </div>

                <p v-if="entries.length === 0" class="p-10 text-center text-sm text-gray-600 dark:text-neutral-400">
                    {{ __('No deposits yet.') }}
                </p>

                <ul v-else class="divide-y divide-gray-100 dark:divide-neutral-800">
                    <li
                        v-for="entry in entries"
                        :key="entry.uuid"
                        class="group flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
                            :class="entry.type === 'withdraw' ? categoryColor('red').badge : categoryColor('green').badge"
                        >
                            <ArrowUpFromLine v-if="entry.type === 'withdraw'" class="size-4" />
                            <ArrowDownToLine v-else class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-neutral-100">
                                {{ entry.note || entryLabel(entry) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-neutral-400">
                                {{ formatDate(entry.saved_on) }}
                                <template v-if="entry.note"> · {{ entryLabel(entry) }}</template>
                            </p>
                        </div>

                        <span class="shrink-0 text-right tabular-nums">
                            <span
                                class="block text-base font-bold"
                                :class="entry.type === 'withdraw' ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-neutral-100'"
                            >
                                {{ entry.type === 'withdraw' ? '−' : '+' }}{{ money.format(entry.amount) }}
                            </span>
                            <span class="block text-xs" :class="MUTED">
                                {{ riel(entry.amount) }}
                            </span>
                        </span>

                        <Button
                            v-if="can.update"
                            variant="destructive"
                            size="xs"
                            class="shrink-0 rounded-xl opacity-100 max-sm:h-8 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                            :disabled="deletingEntry === entry.uuid"
                            @click="confirmingEntry = entry"
                        >
                            {{ __('Delete') }}
                        </Button>
                    </li>
                </ul>
            </div>
        </div>

        <ConfirmDialog
            :open="confirmingGoal"
            :title="__('Delete this goal?')"
            :description="
                __('&quot;:name&quot; and everything saved against it will be removed. This cannot be undone.', {
                    name: goal.name,
                })
            "
            :confirm-label="__('Delete')"
            :cancel-label="__('Cancel')"
            :processing="goalForm.processing"
            :processing-label="__('Deleting…')"
            @update:open="confirmingGoal = $event"
            @confirm="destroyGoal"
        />

        <ConfirmDialog
            :open="confirmingEntry !== null"
            :title="__('Delete this entry?')"
            :description="
                confirmingEntry
                    ? __(':amount on :date will be removed. This cannot be undone.', {
                          amount: money.format(confirmingEntry.amount),
                          date: formatDate(confirmingEntry.saved_on),
                      })
                    : ''
            "
            :confirm-label="__('Delete')"
            :cancel-label="__('Cancel')"
            :processing="deletingEntry !== null"
            :processing-label="__('Deleting…')"
            @update:open="confirmingEntry = $event ? confirmingEntry : null"
            @confirm="destroyEntry"
        />
    </AuthenticatedLayout>
</template>
