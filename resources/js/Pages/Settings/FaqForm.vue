<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import FormActions from '@/Components/FormActions.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * Author a help entry on its own screen.
 *
 * Three fields, but the answer is a four-row textarea and both it and the
 * question carry a locale tab strip — enough to fill a dialog before any
 * validation line appears, and to overflow one after.
 *
 * Not SettingsLayout: that renders the nine-tab settings nav, which is nine ways
 * to walk out of a half-written answer. A form screen shows the way back and
 * nothing else.
 */
const props = defineProps({
    // The entry being edited; absent when adding.
    faq: { type: Object, default: null },
    statuses: { type: Array, required: true },
});

const editing = !!props.faq;

const form = useForm({
    // Per-locale maps, always with both keys — the controller sends them that
    // way so there is a box for each language even before anything is written.
    question: { en: props.faq?.question?.en ?? '', km: props.faq?.question?.km ?? '' },
    answer: { en: props.faq?.answer?.en ?? '', km: props.faq?.answer?.km ?? '' },
    status: props.faq?.status ?? 'draft',
});

const backHref = route('faqs.index');

function submit() {
    if (editing) {
        form.patch(route('faqs.update', props.faq.uuid));
    } else {
        form.post(route('faqs.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit entry') : trans('Add entry')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="editing ? __('Edit entry') : __('Add entry')"
        :back-label="__('Back to help entries')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <p class="mb-4 text-sm" :class="MUTED">
                {{ __('Write the question and answer in each language. English is required.') }}
            </p>

            <div class="space-y-5">
                <!-- One tab per language over each field, mounting only the
                     active locale — the same pattern the Category form uses. -->
                <LocaleTabs :form="form" field="question">
                    <template #label>
                        <Label class="text-sm font-semibold">{{ __('Question') }}</Label>
                    </template>
                    <template #default="{ locale, isRequired }">
                        <Input
                            :id="`q_${locale}`"
                            v-model="form.question[locale]"
                            autocomplete="off"
                            :required="isRequired"
                            :aria-invalid="!!form.errors[`question.${locale}`]"
                        />
                    </template>
                </LocaleTabs>

                <LocaleTabs :form="form" field="answer">
                    <template #label>
                        <Label class="text-sm font-semibold">{{ __('Answer') }}</Label>
                    </template>
                    <template #default="{ locale, isRequired }">
                        <!-- Taller than the dialog's four rows: the screen has
                             the height, and an answer is a paragraph. -->
                        <Textarea
                            :id="`a_${locale}`"
                            v-model="form.answer[locale]"
                            rows="8"
                            :required="isRequired"
                            :aria-invalid="!!form.errors[`answer.${locale}`]"
                        />
                    </template>
                </LocaleTabs>

                <div>
                    <Label for="faq_status" class="text-xs" :class="MUTED">
                        {{ __('Status') }}
                    </Label>
                    <NativeSelect id="faq_status" v-model="form.status" class="mt-1">
                        <NativeSelectOption
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </NativeSelectOption>
                    </NativeSelect>
                </div>
            </div>

            <FormActions>
                <template #cancel>
                    <Button :as="Link" :href="backHref" variant="outline" class="w-full max-sm:h-12 sm:w-auto">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
