<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import CategoryStylePicker from '@/Components/CategoryStylePicker.vue';
import FormActions from '@/Components/FormActions.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * Create/edit a category on its own screen.
 *
 * Three fields on paper, but two of them are the colour and icon grids — some
 * fifty swatches and tiles between them. In a bottom sheet that ran past the top
 * of the viewport with the Create button stranded below the fold, which is the
 * shape that says "this wanted a screen" no matter what the field count said.
 */
const props = defineProps({
    // The record being edited; absent when creating.
    category: { type: Object, default: null },
});

const editing = !!props.category;

const form = useForm({
    // One name. The column behind it is still translatable JSON, but the value
    // typed here is stored under the fallback locale — see TranslatableInput.
    name: props.category?.name ?? '',
    color: props.category?.color ?? 'slate',
    icon: props.category?.icon ?? null,
});

const backHref = route('categories.index');

function submit() {
    if (editing) {
        form.put(route('categories.update', props.category.uuid));
    } else {
        form.post(route('categories.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit category') : trans('New category')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="editing ? __('Edit category') : __('New category')"
        :back-label="__('Back to categories')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <p class="mb-4 text-sm" :class="MUTED">
                {{ __('Categories are shared by everyone logging expenses.') }}
            </p>

            <!--
                One field per row, each the full width of the card.

                A two-column split was tried and left a tall empty gap under Name:
                the swatch and icon grids are much taller than a single input, so
                the columns could never balance. Stacked, every row gets the whole
                width — the icon grid spreads into more columns and needs fewer
                rows, which is what the space was worth spending on.
            -->
            <div class="grid gap-6">
                <div>
                    <Label for="name">{{ __('Name') }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        class="mt-1"
                        autocomplete="off"
                        placeholder="e.g. Groceries"
                        required
                        :aria-invalid="!!form.errors.name"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.name }}
                    </p>
                </div>

                <CategoryStylePicker :form="form" />
            </div>

            <FormActions>
                <template #cancel>
                    <Button :as="Link" :href="backHref" variant="outline" class="w-full rounded-xl max-sm:h-12 sm:w-auto">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full rounded-xl max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : editing ? __('Save') : __('Create') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
