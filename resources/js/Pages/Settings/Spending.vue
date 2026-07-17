<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    // { enabled, warning: {en, km}, advice: {en, km} }
    spending: { type: Object, required: true },
    // [{ value, label }] — drives one textarea per language.
    locales: { type: Array, required: true },
});

const form = useForm({
    enabled: props.spending.enabled,
    // Spread so the form owns its own copy and editing does not mutate the prop.
    warning: { ...props.spending.warning },
    advice: { ...props.spending.advice },
});

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

            <!-- Warning message -->
            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold">{{ __('Warning message') }}</legend>
                <p class="text-xs" :class="MUTED">
                    {{ __('A caution about overspending. Leave a language blank to skip it.') }}
                </p>
                <div v-for="locale in locales" :key="`warning_${locale.value}`">
                    <Label :for="`warning_${locale.value}`" class="text-xs" :class="MUTED">
                        {{ locale.label }}
                    </Label>
                    <Textarea
                        :id="`warning_${locale.value}`"
                        v-model="form.warning[locale.value]"
                        class="mt-1"
                        rows="3"
                        :aria-invalid="!!form.errors[`warning.${locale.value}`]"
                    />
                    <p
                        v-if="form.errors[`warning.${locale.value}`]"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors[`warning.${locale.value}`] }}
                    </p>
                </div>
            </fieldset>

            <!-- Spending advice -->
            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold">{{ __('Spending advice') }}</legend>
                <p class="text-xs" :class="MUTED">
                    {{ __('A short tip on how to spend wisely.') }}
                </p>
                <div v-for="locale in locales" :key="`advice_${locale.value}`">
                    <Label :for="`advice_${locale.value}`" class="text-xs" :class="MUTED">
                        {{ locale.label }}
                    </Label>
                    <Textarea
                        :id="`advice_${locale.value}`"
                        v-model="form.advice[locale.value]"
                        class="mt-1"
                        rows="3"
                        :aria-invalid="!!form.errors[`advice.${locale.value}`]"
                    />
                    <p
                        v-if="form.errors[`advice.${locale.value}`]"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors[`advice.${locale.value}`] }}
                    </p>
                </div>
            </fieldset>

            <div>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
