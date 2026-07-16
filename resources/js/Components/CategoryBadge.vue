<script setup>
import { computed } from 'vue';
import { colorClasses, iconComponent } from '@/lib/categoryStyles';

const props = defineProps({
    name: { type: String, required: true },
    color: { type: String, default: 'slate' },
    icon: { type: String, default: null },
});

const classes = computed(() => colorClasses(props.color));
const Icon = computed(() => iconComponent(props.icon));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="classes.badge"
    >
        <component :is="Icon" v-if="Icon" class="size-3.5" aria-hidden="true" />
        {{ name }}
    </span>
</template>
