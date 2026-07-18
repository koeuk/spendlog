<script setup>
/**
 * Pick the currency an amount is *entered* in.
 *
 * Not the currency it is stored in: every amount in the database is USD (see
 * App\Enums\Currency), and the server converts on the way in. This only says how
 * to read the number the user is typing.
 */
defineProps({
    modelValue: { type: String, default: 'USD' },
});

defineEmits(['update:modelValue']);

const CURRENCIES = [
    { value: 'USD', label: '$ USD' },
    { value: 'KHR', label: '៛ KHR' },
];
</script>

<template>
    <!-- Same pill control as the language tabs, so the toggles in a dialog read
         as one pattern. -->
    <div
        class="inline-flex rounded-full border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-800"
        role="group"
        :aria-label="__('Currency')"
    >
        <button
            v-for="option in CURRENCIES"
            :key="option.value"
            type="button"
            :aria-pressed="modelValue === option.value"
            class="rounded-full px-2.5 py-1 text-xs font-semibold transition"
            :class="
                modelValue === option.value
                    ? 'bg-primary text-primary-foreground'
                    : 'text-neutral-500 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-700/60'
            "
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
