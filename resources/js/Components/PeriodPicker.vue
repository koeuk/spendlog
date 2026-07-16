<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';

const props = defineProps({
    // [{ value, label }]
    options: { type: Array, required: true },
    modelValue: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'Period' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const searchRef = ref(null);

const selected = computed(
    () => props.options.find((option) => option.value === props.modelValue) ?? null,
);

/**
 * Matches the label or the value, so "2025" finds every month of that year and
 * "jul" finds July — the two ways someone actually looks for a period.
 */
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.options;
    }

    return props.options.filter(
        (option) =>
            option.label.toLowerCase().includes(q) ||
            option.value.toLowerCase().includes(q),
    );
});

// Focus the box on open, and clear the last search so the full list is back.
watch(open, async (isOpen) => {
    if (!isOpen) {
        query.value = '';
        return;
    }

    await nextTick();
    searchRef.value?.focus();
});

function choose(option) {
    open.value = false;
    if (option.value !== props.modelValue) {
        emit('update:modelValue', option.value);
    }
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="inline-flex h-9 min-w-32 items-center justify-between gap-1.5 rounded-full border border-neutral-200 bg-white/70 px-3 text-xs font-semibold text-neutral-700 transition hover:bg-white disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800/70 dark:text-neutral-200 dark:hover:bg-neutral-800"
                :disabled="disabled"
                :aria-label="label"
                :aria-expanded="open"
                aria-haspopup="listbox"
            >
                <span class="truncate">{{ selected?.label ?? label }}</span>
                <ChevronDown class="size-3.5 shrink-0 text-neutral-400" />
            </button>
        </PopoverTrigger>

        <PopoverContent class="w-56 p-0" align="end">
            <div class="flex items-center gap-2 border-b border-neutral-100 px-3 dark:border-neutral-800">
                <Search class="size-3.5 shrink-0 text-neutral-400" />
                <!-- A plain input, not the shadcn one: this needs no ring or
                     border of its own inside an already-bordered popover. -->
                <input
                    ref="searchRef"
                    v-model="query"
                    type="text"
                    class="h-9 w-full border-0 bg-transparent p-0 text-xs font-medium placeholder:text-neutral-400 focus:outline-none focus:ring-0 dark:text-neutral-100"
                    :placeholder="__('Search month or year')"
                    @keydown.enter.prevent="filtered.length && choose(filtered[0])"
                    @keydown.esc="open = false"
                />
            </div>

            <ul class="max-h-64 overflow-y-auto p-1" role="listbox">
                <li v-for="option in filtered" :key="option.value">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-start text-xs font-medium transition hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        :class="option.value === modelValue ? 'text-neutral-900 dark:text-neutral-100' : 'text-neutral-600 dark:text-neutral-400'"
                        role="option"
                        :aria-selected="option.value === modelValue"
                        @click="choose(option)"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <Check
                            v-if="option.value === modelValue"
                            class="size-3.5 shrink-0"
                        />
                    </button>
                </li>

                <li
                    v-if="!filtered.length"
                    class="px-2.5 py-6 text-center text-xs text-neutral-400"
                >
                    {{ __('No period found.') }}
                </li>
            </ul>
        </PopoverContent>
    </Popover>
</template>
