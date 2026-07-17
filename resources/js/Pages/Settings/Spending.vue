<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    // { enabled, warning: {en?, km?}, advice: {en?, km?} } — the translatable
    // fields arrive as raw JSON maps, exactly like category.name.
    spending: { type: Object, required: true },
});

// Both locales are always present on the form, so a language never goes missing
// — the same shape Categories/Index seeds its name field with.
const form = useForm({
    enabled: props.spending.enabled,
    warning: {
        en: props.spending.warning?.en ?? '',
        km: props.spending.warning?.km ?? '',
    },
    advice: {
        en: props.spending.advice?.en ?? '',
        km: props.spending.advice?.km ?? '',
    },
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

            <!-- One LocaleTabs per translatable field, matching Categories. The
                 language tabs sit in each field's own label row. Nothing is
                 required — the whole feature is optional — so no locale is
                 marked mandatory. -->
            <LocaleTabs :form="form" field="warning">
                <template #label>
                    <div>
                        <Label class="text-sm font-semibold">{{ __('Warning message') }}</Label>
                        <p class="text-xs" :class="MUTED">
                            {{ __('A caution about overspending. Leave a language blank to skip it.') }}
                        </p>
                    </div>
                </template>

                <template #default="{ locale }">
                    <Textarea
                        :id="`warning_${locale}`"
                        v-model="form.warning[locale]"
                        rows="3"
                        :aria-invalid="!!form.errors[`warning.${locale}`]"
                    />
                </template>
            </LocaleTabs>

            <LocaleTabs :form="form" field="advice">
                <template #label>
                    <div>
                        <Label class="text-sm font-semibold">{{ __('Spending advice') }}</Label>
                        <p class="text-xs" :class="MUTED">
                            {{ __('A short tip on how to spend wisely.') }}
                        </p>
                    </div>
                </template>

                <template #default="{ locale }">
                    <Textarea
                        :id="`advice_${locale}`"
                        v-model="form.advice[locale]"
                        rows="3"
                        :aria-invalid="!!form.errors[`advice.${locale}`]"
                    />
                </template>
            </LocaleTabs>

            <div>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
