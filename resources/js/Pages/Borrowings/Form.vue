<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import AmountField from '@/Components/AmountField.vue';
import DateField from '@/Components/DateField.vue';
import FormActions from '@/Components/FormActions.vue';
import { MUTED } from '@/lib/appStyles';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { trans } from '@/lib/i18n';

/**
 * Create/edit a borrowing on its own screen, like Income: two of the fields
 * open a calendar, which a dialog cannot hold on a phone.
 */
const props = defineProps({
    // The record being edited; absent when creating.
    borrowing: { type: Object, default: null },
    // Lenders this person has named before, most used first.
    lenders: { type: Array, default: () => [] },
    // [{ value, label }] — the fixed kinds, labelled for the locale.
    lender_types: { type: Array, required: true },
});

const editing = !!props.borrowing;

function todayString() {
    // Local date, not UTC — toISOString() would shift the day for some zones.
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const form = useForm({
    lender: props.borrowing?.lender ?? '',
    lender_type: props.borrowing?.lender_type ?? props.lender_types[0]?.value ?? 'other',
    amount: editing ? String(props.borrowing.amount) : '',
    // The stored amount is USD whatever it was typed in, so editing always
    // starts from USD rather than from the currency it was entered in.
    currency: editing ? 'USD' : defaultCurrency,
    borrowed_on: props.borrowing?.borrowed_on ?? todayString(),
    due_on: props.borrowing?.due_on ?? '',
    note: props.borrowing?.note ?? '',
});

const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

// Back to the row's page when editing; to the list when creating, since
// there is no row yet.
const backHref = editing
    ? route('borrowings.show', props.borrowing.uuid)
    : route('borrowings.index');

function submit() {
    if (editing) {
        form.put(route('borrowings.update', props.borrowing.uuid));
    } else {
        form.post(route('borrowings.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit borrowing') : trans('Add borrowing')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="editing ? __('Edit borrowing') : __('Add borrowing')"
        :back-label="__('Back to borrowing')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="grid gap-4">
                <div>
                    <Label for="lender">{{ __('Lender') }}</Label>
                    <!-- A plain box with the names used before offered under
                         it — a lender is only ever the string on each row. -->
                    <Input
                        id="lender"
                        v-model="form.lender"
                        class="mt-1"
                        list="borrowing-lenders"
                        maxlength="255"
                        autocomplete="off"
                        required
                        :placeholder="__('e.g. Mom, Sokha, ABA Bank')"
                        :aria-invalid="!!form.errors.lender"
                    />
                    <datalist id="borrowing-lenders">
                        <option v-for="name in lenders" :key="name" :value="name" />
                    </datalist>
                    <p
                        v-if="form.errors.lender"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.lender }}
                    </p>
                </div>

                <div>
                    <Label>{{ __('Lender type') }}</Label>
                    <!-- Five pills, wrapping: the same control as the deposit /
                         withdraw toggle, so the app has one way to pick one of
                         a few. -->
                    <div class="mt-1 flex flex-wrap gap-1.5" role="group">
                        <button
                            v-for="type in lender_types"
                            :key="type.value"
                            type="button"
                            :aria-pressed="form.lender_type === type.value"
                            class="rounded-xl border px-3.5 py-1.5 text-sm font-semibold transition"
                            :class="
                                form.lender_type === type.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-neutral-200 bg-white text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700/60'
                            "
                            @click="form.lender_type = type.value"
                        >
                            {{ type.label }}
                        </button>
                    </div>
                    <p
                        v-if="form.errors.lender_type"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.lender_type }}
                    </p>
                </div>

                <div>
                    <AmountField :form="form" field="amount" :label="__('Amount')" />
                    <!-- Says the floor before the server has to refuse it. -->
                    <p v-if="editing && borrowing.repaid > 0" class="mt-1 text-xs" :class="MUTED">
                        {{ __(':amount has already been repaid, so the amount cannot go below it.', { amount: money.format(borrowing.repaid) }) }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label>{{ __('Borrowed on') }}</Label>
                        <DateField v-model="form.borrowed_on" no-future />
                        <p
                            v-if="form.errors.borrowed_on"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ form.errors.borrowed_on }}
                        </p>
                    </div>

                    <div>
                        <Label>
                            {{ __('Due on') }}
                            <span class="font-normal text-muted-foreground">({{ __('optional') }})</span>
                        </Label>
                        <DateField v-model="form.due_on" :placeholder="__('No due date')" />
                        <button
                            v-if="form.due_on"
                            type="button"
                            class="mt-1 text-xs underline-offset-2 hover:underline"
                            :class="MUTED"
                            @click="form.due_on = ''"
                        >
                            {{ __('Clear due date') }}
                        </button>
                        <p
                            v-if="form.errors.due_on"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ form.errors.due_on }}
                        </p>
                    </div>
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
