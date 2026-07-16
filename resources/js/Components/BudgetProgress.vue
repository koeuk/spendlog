<script setup>
import { computed } from 'vue';

const props = defineProps({
    // 'ok' | 'warning' | 'over' | 'none' — computed server-side by BudgetSummary.
    status: { type: String, default: 'none' },
    barPercent: { type: Number, default: 0 },
});

const STATUS_CLASSES = {
    ok: 'bg-green-500',
    warning: 'bg-amber-500',
    over: 'bg-red-500',
    none: 'bg-gray-300',
};

const barClass = computed(() => STATUS_CLASSES[props.status] ?? STATUS_CLASSES.none);
</script>

<template>
    <div
        class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800"
        role="progressbar"
        :aria-valuenow="barPercent"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <div
            class="h-full rounded-full transition-all"
            :class="barClass"
            :style="{ width: `${barPercent}%` }"
        />
    </div>
</template>
