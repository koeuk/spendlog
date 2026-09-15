<script setup>
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
 * Money paid back against one borrowing.
 *
 * The amount is capped at what is still owed — the server refuses more, under
 * a row lock — and the ceiling is said here so the refusal is rarely needed.
 */
const props = defineProps({
    // { uuid, lender, amount, remaining, borrowed_on }
    borrowing: { type: Object, required: true },
});

function todayString() {
    // Local date, not UTC — toISOString() would shift the day for some zones.
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const form = useForm({
    amount: '',
    currency: defaultCurrency,
    paid_on: todayString(),
    note: '',
});

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

const backHref = route('borrowings.show', props.borrowing.uuid);

function submit() {
    form.post(route('borrowings.repayments.store', props.borrowing.uuid));
}
</script>

<template>
    <Head :title="trans('Record repayment')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="__('Record repayment')"
        :back-label="__('Back to :lender', { lender: borrowing.lender })"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="grid gap-4">
                <div>
                    <AmountField :form="form" field="amount" :label="__('Amount')" />
                    <!-- Says the ceiling before the server has to refuse it. -->
                    <p class="mt-1 text-xs" :class="MUTED">
                        {{ __(':amount still owed', { amount: money.format(borrowing.remaining) }) }}
                        · {{ __('Up to :amount can be repaid.', { amount: money.format(borrowing.remaining) }) }}
                    </p>
                </div>

                <div>
                    <Label>{{ __('Paid on') }}</Label>
                    <DateField v-model="form.paid_on" no-future />
                    <p
                        v-if="form.errors.paid_on"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.paid_on }}
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
