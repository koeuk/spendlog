<script setup>
import { Skeleton } from '@/Components/ui/skeleton';
import { CARD } from '@/lib/appStyles';

defineProps({
    days: { type: Number, default: 2 },
    rows: { type: Number, default: 3 },
});
</script>

<template>
    <div class="space-y-4" aria-busy="true" aria-live="polite">
        <span class="sr-only">Loading expenses…</span>

        <div
            v-for="day in days"
            :key="day"
            :class="[CARD, 'overflow-hidden']"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-neutral-800">
                <Skeleton class="h-4 w-24" />
                <Skeleton class="h-4 w-16" />
            </div>

            <ul class="divide-y divide-gray-100 dark:divide-neutral-800">
                <li v-for="row in rows" :key="row" class="flex items-center gap-3 px-4 py-3">
                    <Skeleton class="size-8 shrink-0 rounded-full" />
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <Skeleton class="h-3.5 w-32" />
                        <Skeleton class="h-3 w-20" />
                    </div>
                    <Skeleton class="h-4 w-14 shrink-0" />
                </li>
            </ul>
        </div>
    </div>
</template>
