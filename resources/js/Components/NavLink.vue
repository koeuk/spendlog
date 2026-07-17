<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

/**
 * The active pill itself is not drawn here — one shared element in the layout
 * slides between links, which it cannot do if each link paints its own. This
 * only colours the label, and `relative z-10` keeps it above the sliding pill.
 *
 * Colour crossfades rather than snapping, so the label meets the pill as it
 * arrives instead of flipping to white before it gets there.
 */
const classes = computed(() =>
    props.active
        ? 'relative z-10 inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold text-white transition-colors duration-300 dark:text-neutral-900'
        : 'relative z-10 inline-flex items-center rounded-full px-4 py-2 text-sm font-medium text-neutral-500 transition-colors duration-300 hover:text-neutral-900 dark:text-white dark:hover:text-white',
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
