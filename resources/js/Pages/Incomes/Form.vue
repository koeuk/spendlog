<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import AmountField from '@/Components/AmountField.vue';
import DateField from '@/Components/DateField.vue';
import FormActions from '@/Components/FormActions.vue';
import SourcePicker from '@/Components/SourcePicker.vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { trans } from '@/lib/i18n';

/**
 * Create/edit an income on its own screen, for the reason Expenses/Form gives:
 * the date picker has nowhere to go inside a dialog on a phone, and a route
 * restores the system back button.
 */
const props = defineProps({
    // The record being edited; absent when creating.
    income: { type: Object, default: null },
    // The sources this person has used before, for the picker.
    sources: { type: Array, default: () => [] },
    // Where the list was — month and year. Whitelisted server-side.
    return_query: { type: Object, default: () => ({}) },
});

const editing = !!props.income;

function todayString() {
    // Local date, not UTC — toISOString() would shift the day for some zones.
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const form = useForm({
    source: props.income?.source ?? '',
    amount: editing ? String(props.income.amount) : '',
    // The stored amount is USD whatever it was typed in, so editing always
    // starts from USD rather than from the currency it was entered in.
    currency: editing ? 'USD' : defaultCurrency,
    received_on: props.income?.received_on ?? todayString(),
    note: props.income?.note ?? '',
    return_query: props.return_query,
});

const backHref = route('incomes.index', props.return_query);

function submit() {
    if (editing) {
        form.put(route('incomes.update', props.income.uuid));
    } else {
        form.post(route('incomes.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit income') : trans('Add income')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="editing ? __('Edit income') : __('Add income')"
        :back-label="__('Back to income')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="grid gap-4">
                <div>
                    <Label for="source">{{ __('Source') }}</Label>
                    <!-- A picker over what was used before, with a new name
                         accepted inline — the category control, for strings. -->
                    <SourcePicker :form="form" :sources="sources" />
                </div>

                <AmountField :form="form" field="amount" :label="__('Amount')" />

                <div>
                    <Label>{{ __('Date') }}</Label>
                    <DateField v-model="form.received_on" no-future />
                    <p
                        v-if="form.errors.received_on"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.received_on }}
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
