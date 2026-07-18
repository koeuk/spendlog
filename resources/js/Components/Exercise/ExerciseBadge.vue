<script setup>
import { computed } from 'vue';
import { exerciseColor, exerciseIcon } from '@/lib/exerciseStyles';

/**
 * The exercise module's counterpart to CategoryBadge — same geometry, so the two
 * modules read as one product, but resolving icons from the movement registry
 * rather than the spending one.
 */
const props = defineProps({
    name: { type: String, required: true },
    color: { type: String, default: 'slate' },
    icon: { type: String, default: null },
});

const classes = computed(() => exerciseColor(props.color));
const Icon = computed(() => exerciseIcon(props.icon));
</script>

<template>
    <span class="flex items-center gap-2.5">
        <span
            class="flex size-7 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
            :class="classes.badge"
        >
            <component :is="Icon" v-if="Icon" class="size-4" aria-hidden="true" />
            <span v-else class="size-2 rounded-full" :class="classes.dot" />
        </span>
        {{ name }}
    </span>
</template>
