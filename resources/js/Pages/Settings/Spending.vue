<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    // { enabled, warning: {en?, km?}, advice: {en?, km?} } — the translatable
    // fields arrive as raw JSON maps, exactly like category.name.
    spending: { type: Object, required: true },
    // [{ value: 'USD', label: '$ USD' }, …] — built server-side so the page
    // does not have to know the symbol for each currency.
    currencies: { type: Array, default: () => [] },
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
    khr_per_usd: props.spending.khr_per_usd,
    default_currency: props.spending.default_currency,
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

            <!-- Not spending *guidance*, but it belongs to the same "how money
                 works here" settings page rather than a page of its own. -->
            <div>
                <Label for="khr_per_usd" class="text-sm font-semibold">
                    {{ __('Exchange rate') }}
                </Label>
                <p class="text-xs" :class="MUTED">
                    {{ __('Riel per one US dollar. A price entered in KHR is converted at this rate and stored in USD.') }}
                </p>
                <div class="mt-2 flex items-center gap-2">
                    <Input
                        id="khr_per_usd"
                        v-model="form.khr_per_usd"
                        type="number"
                        step="1"
                        min="1"
                        inputmode="decimal"
                        class="max-w-40"
                        :aria-invalid="!!form.errors.khr_per_usd"
                    />
                    <span class="text-sm" :class="MUTED">{{ __('KHR = $1.00') }}</span>
                </div>
                <p v-if="form.errors.khr_per_usd" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.khr_per_usd }}
                </p>
            </div>

            <div>
                <Label for="default_currency" class="text-sm font-semibold">
                    {{ __('Default currency') }}
                </Label>
                <p class="text-xs" :class="MUTED">
                    {{ __('Which currency the amount fields start on. Amounts are always stored in US dollars — this only saves retoggling when most spending is in one currency.') }}
                </p>
                <div class="mt-2">
                    <Select
                        :model-value="form.default_currency"
                        @update:model-value="form.default_currency = $event"
                    >
                        <SelectTrigger id="default_currency" class="max-w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="currency in currencies"
                                :key="currency.value"
                                :value="currency.value"
                            >
                                {{ currency.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <p v-if="form.errors.default_currency" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.default_currency }}
                </p>
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
