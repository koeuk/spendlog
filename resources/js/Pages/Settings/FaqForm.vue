<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import FormActions from '@/Components/FormActions.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { FORM_ACTION, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * Author a help entry on its own screen.
 *
 * Three fields, but the answer is a tall textarea — enough to fill a dialog
 * before any validation line appears, and to overflow one after.
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

// One box per field. The columns are still translatable JSON, but what is
// written here is stored under the fallback locale — see TranslatableInput.
const form = useForm({
    question: props.faq?.question ?? '',
    answer: props.faq?.answer ?? '',
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
                {{ __('Write the question and the answer it should show.') }}
            </p>

            <div class="space-y-5">
                <div>
                    <Label for="question" class="text-sm font-semibold">{{ __('Question') }}</Label>
                    <Input
                        id="question"
                        v-model="form.question"
                        class="mt-1"
                        autocomplete="off"
                        required
                        :aria-invalid="!!form.errors.question"
                    />
                    <p v-if="form.errors.question" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.question }}
                    </p>
                </div>

                <div>
                    <Label for="answer" class="text-sm font-semibold">{{ __('Answer') }}</Label>
                    <!-- Taller than the dialog's four rows: the screen has the
                         height, and an answer is a paragraph. -->
                    <Textarea
                        id="answer"
                        v-model="form.answer"
                        class="mt-1"
                        rows="8"
                        required
                        :aria-invalid="!!form.errors.answer"
                    />
                    <p v-if="form.errors.answer" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.answer }}
                    </p>
                </div>

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
                    <Button :as="Link" :href="backHref" variant="outline" :class="FORM_ACTION">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" :class="FORM_ACTION">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
