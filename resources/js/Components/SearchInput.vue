<script setup>
import { ref, watch } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { Search, X } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    // Long enough that a typist is not firing a request per keystroke, short
    // enough that the list still feels like it is following along.
    debounce: { type: Number, default: 300 },
});

const emit = defineEmits(['update:modelValue']);

// A local copy, so the field stays responsive while the debounced request is
// still in flight. Emitting straight from the input would make every keystroke
// wait on the round trip.
const text = ref(props.modelValue);

watchDebounced(text, (value) => emit('update:modelValue', value), {
    debounce: () => props.debounce,
});

// The parent can reset the filter (a Clear all button, or a back navigation
// restoring an earlier query) and the box has to follow.
watch(
    () => props.modelValue,
    (value) => {
        if (value !== text.value) {
            text.value = value;
        }
    },
);

function clear() {
    text.value = '';
    // Emitted directly rather than waiting out the debounce: clearing is a
    // deliberate act and should feel immediate.
    emit('update:modelValue', '');
}
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <!-- Deliberately type=text: type=search makes WebKit draw its own clear
             button, which would sit next to the one below. -->
        <Input
            v-model="text"
            type="text"
            :placeholder="placeholder"
            autocomplete="off"
            class="ps-9 pe-9"
            :aria-label="placeholder"
        />
        <button
            v-if="text"
            type="button"
            class="absolute end-2 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :aria-label="__('Clear search')"
            @click="clear"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
