<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { FORM_ACTION, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    // { enabled, warning, advice } — both messages arrive resolved for the
    // active locale, exactly like category.name.
    spending: { type: Object, required: true },
    // [{ value: 'USD', label: '$ USD' }, …] — built server-side so the page
    // does not have to know the symbol for each currency.
    currencies: { type: Array, default: () => [] },
});

// One box per message. The columns are still translatable JSON, but what is
// written here is stored under the fallback locale — see TranslatableInput.
const form = useForm({
    enabled: props.spending.enabled,
    warning: props.spending.warning ?? '',
    advice: props.spending.advice ?? '',
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
        <form class="space-y-8" @submit.prevent="submit">
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

            <!-- Neither message is required — the whole feature is optional, and
                 a blank one is cleared rather than saved empty. -->
            <div>
                <Label for="warning" class="text-sm font-semibold">{{ __('Warning message') }}</Label>
                <p class="text-xs" :class="MUTED">
                    {{ __('A caution about overspending. Leave it blank to skip it.') }}
                </p>
                <Textarea
                    id="warning"
                    v-model="form.warning"
                    class="mt-1"
                    rows="3"
                    :aria-invalid="!!form.errors.warning"
                />
                <p v-if="form.errors.warning" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.warning }}
                </p>
            </div>

            <div>
                <Label for="advice" class="text-sm font-semibold">{{ __('Spending advice') }}</Label>
                <p class="text-xs" :class="MUTED">
                    {{ __('A short tip on how to spend wisely.') }}
                </p>
                <Textarea
                    id="advice"
                    v-model="form.advice"
                    class="mt-1"
                    rows="3"
                    :aria-invalid="!!form.errors.advice"
                />
                <p v-if="form.errors.advice" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.advice }}
                </p>
            </div>

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
                    <SearchableSelect
                        :model-value="form.default_currency"
                        :options="currencies"
                        :label="__('Currency')"
                        :searchable="false"
                        align="start"
                        trigger-class="h-10 w-40 rounded-xl border border-input bg-background px-3 text-sm max-sm:h-11"
                        content-class="w-40"
                        @update:model-value="form.default_currency = $event"
                    />
                </div>
                <p v-if="form.errors.default_currency" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.default_currency }}
                </p>
            </div>

            <div>
                <Button type="submit" :disabled="form.processing" :class="FORM_ACTION">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
