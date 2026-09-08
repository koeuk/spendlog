<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CurrencyToggle from '@/Components/CurrencyToggle.vue';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

/**
 * A money field: the label, the USD/KHR toggle, the number and the "stored
 * as" hint — the same block ExpenseForm draws for the price, shared by the
 * income, goal and ledger forms.
 *
 * Binds straight to the Inertia form: `field` is the amount key and
 * `currency` on the form says what the typed number is denominated in. The
 * server converts on the way in; every stored amount is USD.
 */
const props = defineProps({
    form: { type: Object, required: true },
    field: { type: String, default: 'amount' },
    label: { type: String, required: true },
});

const id = computed(() => `amount-${props.field}`);

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

/**
 * What a riel amount will actually be stored as.
 *
 * Mirrors App\Enums\Currency::toUsd — same divisor, same rounding — so the hint
 * matches the row that gets written. Nothing to say for a USD amount: it is
 * stored exactly as typed.
 */
const convertedPreview = computed(() => {
    if (props.form.currency !== 'KHR') {
        return '';
    }

    const amount = Number(props.form[props.field]);

    if (!Number.isFinite(amount) || amount <= 0) {
        return trans('Entered in riel, stored in US dollars.');
    }

    return trans('Stored as :amount', {
        amount: usd.format(Math.round((amount / khrPerUsd.value) * 100) / 100),
    });
});
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-2">
            <Label :for="id">{{ label }}</Label>

            <CurrencyToggle v-model="form.currency" />
        </div>

        <Input
            :id="id"
            v-model="form[field]"
            class="mt-1"
            type="number"
            :step="form.currency === 'KHR' ? '100' : '0.01'"
            min="0"
            inputmode="decimal"
            :placeholder="form.currency === 'KHR' ? '0' : '0.00'"
            :aria-invalid="!!form.errors[field]"
        />

        <p v-if="convertedPreview" class="mt-1 text-xs" :class="MUTED">
            {{ convertedPreview }}
        </p>

        <p
            v-if="form.errors[field]"
            class="mt-1 text-sm text-red-600 dark:text-red-400"
        >
            {{ form.errors[field] }}
        </p>
    </div>
</template>
