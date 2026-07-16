<script setup>
import SearchableSelect from '@/Components/SearchableSelect.vue';

defineProps({
    // [{ value, label }]
    options: { type: Array, required: true },
    modelValue: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'Period' },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <!--
        match-value so "2025" finds every month of that year off the raw
        "2025-07" value, the way "jul" finds July off the label — the two ways
        someone actually looks for a period.
    -->
    <SearchableSelect
        :options="options"
        :model-value="modelValue"
        :disabled="disabled"
        :label="label"
        :search-placeholder="__('Search month or year')"
        :empty-text="__('No period found.')"
        match-value
        trigger-class="h-9 min-w-32 rounded-full border border-neutral-200 bg-white/70 px-3 text-xs font-semibold text-neutral-700 hover:bg-white dark:border-neutral-700 dark:bg-neutral-800/70 dark:text-neutral-200 dark:hover:bg-neutral-800"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
