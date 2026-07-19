<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExpenseForm from '@/Components/ExpenseForm.vue';
import FormScreenHeader from '@/Components/FormScreenHeader.vue';
import { Button } from '@/Components/ui/button';
import { CARD } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * Create/edit an expense on its own screen.
 *
 * This was a dialog on the index. Four fields would have fitted, but two of them
 * open pickers of their own — the category list and the calendar — and a popover
 * inside a modal has nowhere to go on a phone: it either covers the control it
 * belongs to or runs off the viewport. On its own route each picker is free to
 * be a bottom sheet.
 *
 * The route also buys back the system back button. A dialog holds no history
 * entry, so Android's back gesture left the page entirely and took the
 * half-filled form with it.
 */
const props = defineProps({
    // The record being edited; absent when creating.
    expense: { type: Object, default: null },
    categories: { type: Array, required: true },
    can: { type: Object, required: true },
    // Where the index was when we left it — month, year, user, q. Whitelisted
    // server-side, so it is safe to hand straight back on save.
    return_query: { type: Object, default: () => ({}) },
});

const editing = !!props.expense;

function todayString() {
    // Local date, not UTC — toISOString() would shift the day for some zones.
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const form = useForm({
    // One key per locale — item is a translatable JSON column, like category.name.
    // item_translations is the raw JSON; item alone is only the active locale, so
    // editing from it would quietly drop the other language on save.
    item: {
        en: props.expense?.item_translations?.en ?? '',
        km: props.expense?.item_translations?.km ?? '',
    },
    price: editing ? String(props.expense.price) : '',
    // The stored price is USD whatever it was typed in, so editing always starts
    // from USD rather than from the currency it happened to be entered in.
    currency: editing ? 'USD' : defaultCurrency,
    category_uuid: props.expense?.category_uuid ?? '',
    // Set instead of category_uuid when naming a category inline.
    new_category: '',
    spent_on: props.expense?.spent_on ?? todayString(),
    // Carried through the save so the redirect lands on the month the list was
    // showing. Revalidated server-side, never trusted as a URL.
    return_query: props.return_query,
});

const backHref = route('expenses.index', props.return_query);

function submit() {
    if (editing) {
        form.put(route('expenses.update', props.expense.uuid));
    } else {
        form.post(route('expenses.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit expense') : trans('Add expense')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl">
            <FormScreenHeader
                :back-href="backHref"
                :title="editing ? __('Edit expense') : __('Add expense')"
                :subtitle="editing ? expense.item : ''"
                :back-label="__('Back to expenses')"
            />

            <form :class="[CARD, 'p-4 sm:p-6']" @submit.prevent="submit">
                <ExpenseForm
                    :form="form"
                    :categories="categories"
                    :can-create-category="can.create_category"
                />

                <!-- Stacked and full-width on a phone, where a row of two would
                     put Cancel within a thumb's width of Save. -->
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button
                        :as="Link"
                        :href="backHref"
                        variant="outline"
                        class="sm:w-auto"
                    >
                        {{ __('Cancel') }}
                    </Button>

                    <Button type="submit" :disabled="form.processing" class="sm:w-auto">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
