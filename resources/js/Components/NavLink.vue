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
 * arrives instead of flipping colour before it gets there.
 *
 * The active label is text-primary-foreground, matching the ACTIVE token every
 * other pill uses, because the sliding pill behind it is bg-primary. That
 * foreground is computed from the brand colour (see AppSetting::cssVariables),
 * so it stays readable on whatever an admin picks. Hardcoding it — this was
 * `text-white dark:text-neutral-900` — put near-black on the brand fill in dark
 * mode: 2.5:1, well under AA, while the Reports period toggle beside it sat at
 * 6.8:1 doing the same job.
 */
const classes = computed(() =>
    props.active
        ? 'relative z-10 inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold text-primary-foreground transition-colors duration-300'
        : 'relative z-10 inline-flex items-center rounded-full px-4 py-2 text-sm font-medium text-neutral-500 transition-colors duration-300 hover:text-neutral-900 dark:text-white dark:hover:text-white',
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
