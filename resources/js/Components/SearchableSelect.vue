<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { cn } from '@/lib/utils';

const props = defineProps({
    // [{ value, label }]
    options: { type: Array, required: true },
    modelValue: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    // Shown on the trigger when nothing in `options` matches modelValue.
    label: { type: String, default: '' },
    searchPlaceholder: { type: String, default: '' },
    emptyText: { type: String, default: '' },
    // Search the value as well as the label. Useful when the value is itself
    // meaningful to a human ("2026-07"), noise when it is a uuid.
    matchValue: { type: Boolean, default: false },
    /*
     * Popover renders no element of its own, so a class set on this component
     * would be dropped rather than inherited by the trigger. The trigger and
     * the popup therefore take their skins by prop — each caller sits in a
     * different toolbar and has to match its neighbours.
     */
    triggerClass: { type: [String, Array, Object], default: '' },
    contentClass: { type: [String, Array, Object], default: 'w-56' },
    align: { type: String, default: 'end' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const searchRef = ref(null);

const selected = computed(
    () => props.options.find((option) => option.value === props.modelValue) ?? null,
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.options;
    }

    return props.options.filter(
        (option) =>
            option.label.toLowerCase().includes(q) ||
            (props.matchValue && String(option.value).toLowerCase().includes(q)),
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
                :class="
                    cn(
                        'inline-flex items-center justify-between gap-1.5 transition disabled:opacity-50',
                        triggerClass,
                    )
                "
                :disabled="disabled"
                :aria-label="label"
                :aria-expanded="open"
                aria-haspopup="listbox"
            >
                <span class="truncate">{{ selected?.label ?? label }}</span>
                <ChevronDown class="size-3.5 shrink-0 text-neutral-400" />
            </button>
        </PopoverTrigger>

        <PopoverContent :class="cn('p-0', contentClass)" :align="align">
            <div class="flex items-center gap-2 border-b border-neutral-100 px-3 dark:border-neutral-800">
                <Search class="size-3.5 shrink-0 text-neutral-400" />
                <!-- A plain input, not the shadcn one: this needs no ring or
                     border of its own inside an already-bordered popover. -->
                <input
                    ref="searchRef"
                    v-model="query"
                    type="text"
                    class="h-9 w-full border-0 bg-transparent p-0 text-xs font-medium placeholder:text-neutral-400 focus:outline-none focus:ring-0 dark:text-neutral-100"
                    :placeholder="searchPlaceholder || __('Search…')"
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
                    {{ emptyText || __('Nothing found.') }}
                </li>
            </ul>
        </PopoverContent>
    </Popover>
</template>
