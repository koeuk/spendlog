<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { MUTED, SEGMENT, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    // { enabled, warning: {en, km}, advice: {en, km} }
    spending: { type: Object, required: true },
    // [{ value, label }] — one tab per language.
    locales: { type: Array, required: true },
});

const form = useForm({
    enabled: props.spending.enabled,
    // Spread so the form owns its own copy and editing does not mutate the prop.
    warning: { ...props.spending.warning },
    advice: { ...props.spending.advice },
});

// One language edited at a time. Every locale's value still lives in the form,
// so switching tabs only changes which one the two boxes are bound to — nothing
// is lost, and a single Save writes them all.
const activeLocale = ref(props.locales[0]?.value ?? 'en');

// A validation error can land on a language that is not the open tab, so mark
// the tab rather than let the message hide behind it.
function localeHasError(locale) {
    return Boolean(form.errors[`warning.${locale}`] || form.errors[`advice.${locale}`]);
}

const warningError = computed(() => form.errors[`warning.${activeLocale.value}`]);
const adviceError = computed(() => form.errors[`advice.${activeLocale.value}`]);

function submit() {
    form.post(route('spending.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Spending" />

    <SettingsLayout
        :heading="trans('Spending')"
        :description="trans('An optional warning and a spending tip, shown on the dashboard.')"
    >
        <form class="max-w-xl space-y-8" @submit.prevent="submit">
            <!-- Master switch. Off means neither message shows anywhere. -->
            <div class="flex items-start gap-3">
                <Checkbox
                    id="spending_enabled"
                    :model-value="form.enabled"
                    class="mt-0.5"
                    @update:model-value="form.enabled = $event"
                />
                <div class="min-w-0">
                    <Label for="spending_enabled" class="cursor-pointer text-sm font-medium">
                        {{ __('Show spending guidance on the dashboard') }}
                    </Label>
                    <p class="text-xs" :class="MUTED">
                        {{ __('When off, the messages below are saved but hidden from everyone.') }}
                    </p>
                </div>
            </div>

            <!-- Language tabs. The text fields below follow the selected tab. -->
            <div :class="SEGMENT" role="tablist" :aria-label="__('Language')">
                <button
                    v-for="locale in locales"
                    :key="locale.value"
                    type="button"
                    role="tab"
                    :aria-selected="activeLocale === locale.value"
                    class="relative px-4 py-1.5 text-sm font-semibold transition"
                    :class="activeLocale === locale.value ? SEGMENT_ON : SEGMENT_OFF"
                    @click="activeLocale = locale.value"
                >
                    {{ locale.label }}
                    <!-- A validation error on a language that is not open. -->
                    <span
                        v-if="localeHasError(locale.value) && activeLocale !== locale.value"
                        class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-red-500"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <!-- One set of boxes, swapped per active language. The others stay in
                 the form, so a single Save writes every language at once. -->
            <div class="space-y-6">
                <div>
                    <Label :for="`warning_${activeLocale}`" class="text-sm font-semibold">
                        {{ __('Warning message') }}
                    </Label>
                    <p class="mt-0.5 text-xs" :class="MUTED">
                        {{ __('A caution about overspending. Leave a language blank to skip it.') }}
                    </p>
                    <Textarea
                        :id="`warning_${activeLocale}`"
                        v-model="form.warning[activeLocale]"
                        class="mt-2"
                        rows="3"
                        :aria-invalid="!!warningError"
                    />
                    <p v-if="warningError" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ warningError }}
                    </p>
                </div>

                <div>
                    <Label :for="`advice_${activeLocale}`" class="text-sm font-semibold">
                        {{ __('Spending advice') }}
                    </Label>
                    <p class="mt-0.5 text-xs" :class="MUTED">
                        {{ __('A short tip on how to spend wisely.') }}
                    </p>
                    <Textarea
                        :id="`advice_${activeLocale}`"
                        v-model="form.advice[activeLocale]"
                        class="mt-2"
                        rows="3"
                        :aria-invalid="!!adviceError"
                    />
                    <p v-if="adviceError" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ adviceError }}
                    </p>
                </div>
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
