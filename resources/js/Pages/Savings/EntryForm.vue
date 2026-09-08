<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import AmountField from '@/Components/AmountField.vue';
import DateField from '@/Components/DateField.vue';
import FormActions from '@/Components/FormActions.vue';
import { MUTED } from '@/lib/appStyles';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { trans } from '@/lib/i18n';

/**
 * A deposit into, or withdrawal from, one goal.
 *
 * Which way the money goes is a toggle, preselected from the button that
 * opened this screen. The amount is always typed positive; the server applies
 * the sign, and refuses a withdrawal larger than what the goal holds.
 */
const props = defineProps({
    goal: { type: Object, required: true },
    // 'deposit' | 'withdraw'
    type: { type: String, default: 'deposit' },
});

function todayString() {
    // Local date, not UTC — toISOString() would shift the day for some zones.
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const form = useForm({
    type: props.type,
    amount: '',
    currency: defaultCurrency,
    saved_on: todayString(),
    note: '',
});

const TYPES = [
    { value: 'deposit', label: 'Deposit' },
    { value: 'withdraw', label: 'Withdraw' },
];

const withdrawing = computed(() => form.type === 'withdraw');

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const title = computed(() =>
    withdrawing.value
        ? trans('Withdraw from :goal', { goal: props.goal.name })
        : trans('Deposit to :goal', { goal: props.goal.name }),
);

const backHref = route('savings.show', props.goal.uuid);

function submit() {
    form.post(route('savings.entries.store', props.goal.uuid));
}
</script>

<template>
    <Head :title="title" />

    <FormScreenLayout
        :back-href="backHref"
        :title="title"
        :back-label="__('Back to goal')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="grid gap-4">
                <!-- The same pill control as the currency toggle, so the two
                     switches on this form read as one pattern. -->
                <div
                    class="inline-flex justify-self-start rounded-full border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-800"
                    role="group"
                >
                    <button
                        v-for="option in TYPES"
                        :key="option.value"
                        type="button"
                        :aria-pressed="form.type === option.value"
                        class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                        :class="
                            form.type === option.value
                                ? 'bg-primary text-primary-foreground'
                                : 'text-neutral-500 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                        "
                        @click="form.type = option.value"
                    >
                        {{ __(option.label) }}
                    </button>
                </div>

                <div>
                    <AmountField :form="form" field="amount" :label="__('Amount')" />
                    <!-- Says the ceiling before the server has to refuse it. -->
                    <p class="mt-1 text-xs" :class="MUTED">
                        {{ __('Saved so far') }}: {{ money.format(goal.saved) }}
                        <template v-if="withdrawing">
                            · {{ __('Up to :amount can be withdrawn.', { amount: money.format(goal.saved) }) }}
                        </template>
                    </p>
                </div>

                <div>
                    <Label>{{ __('Date') }}</Label>
                    <DateField v-model="form.saved_on" no-future />
                    <p
                        v-if="form.errors.saved_on"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.saved_on }}
                    </p>
                </div>

                <div>
                    <Label for="note">
                        {{ __('Note') }}
                        <span class="font-normal text-muted-foreground">({{ __('optional') }})</span>
                    </Label>
                    <Textarea
                        id="note"
                        v-model="form.note"
                        class="mt-1"
                        rows="2"
                        maxlength="500"
                        :aria-invalid="!!form.errors.note"
                    />
                    <p
                        v-if="form.errors.note"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.note }}
                    </p>
                </div>
            </div>

            <FormActions>
                <template #cancel>
                    <Button
                        :as="Link"
                        :href="backHref"
                        variant="outline"
                        class="w-full rounded-xl max-sm:h-12 sm:w-auto"
                    >
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full rounded-xl max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
