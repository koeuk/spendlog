<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import CategoryStylePicker from '@/Components/CategoryStylePicker.vue';
import FormActions from '@/Components/FormActions.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
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
    // Every locale at once — name is a translatable JSON column, so a single
    // submit sends the whole object.
    name: {
        en: props.category?.name?.en ?? '',
        km: props.category?.name?.km ?? '',
    },
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
                <LocaleTabs
                    :form="form"
                    field="name"
                    :placeholders="{ en: 'e.g. Groceries', km: 'ឧ. គ្រឿងទេស' }"
                >
                    <template #label>
                        <Label for="name_en">{{ __('Name') }}</Label>
                    </template>

                    <template #default="{ locale, placeholder, isRequired }">
                        <Input
                            :id="`name_${locale}`"
                            v-model="form.name[locale]"
                            autocomplete="off"
                            :placeholder="placeholder"
                            :required="isRequired"
                            :aria-invalid="!!form.errors[`name.${locale}`]"
                        />
                    </template>
                </LocaleTabs>

                <CategoryStylePicker :form="form" />
            </div>

            <FormActions>
                <template #cancel>
                    <Button :as="Link" :href="backHref" variant="outline" class="w-full max-sm:h-12 sm:w-auto">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : editing ? __('Save') : __('Create') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
