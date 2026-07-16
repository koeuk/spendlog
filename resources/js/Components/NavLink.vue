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

// A filled pill for the current page — the same active treatment the EN/KM and
// Mine/Everyone controls already use, rather than a second idiom.
const classes = computed(() =>
    props.active
        ? 'inline-flex items-center rounded-full bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition dark:bg-neutral-100 dark:text-neutral-900'
        : 'inline-flex items-center rounded-full px-4 py-2 text-sm font-medium text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100',
);
</script>

<template>
    <Link :href="href" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
