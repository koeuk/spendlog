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

// The mobile echo of NavLink: same filled-pill active state, full width.
const classes = computed(() =>
    props.active
        ? 'block w-full rounded-[20px] bg-neutral-900 px-4 py-2.5 text-start text-sm font-semibold text-white transition dark:bg-neutral-100 dark:text-neutral-900'
        : 'block w-full rounded-[20px] px-4 py-2.5 text-start text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
