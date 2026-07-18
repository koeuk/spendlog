<script setup>
import { computed } from 'vue';
import { EYEBROW, MUTED } from '@/lib/appStyles';

/**
 * Twelve weeks of training days, one column per week.
 *
 * Binary — trained or not — rather than shaded by volume. This card answers
 * "am I showing up", and shading it by load would let a heavy Monday hide three
 * missed days behind one dark square.
 */
const props = defineProps({
    // { from: 'Y-m-d', to: 'Y-m-d', days: ['Y-m-d', …] }
    heatmap: { type: Object, required: true },
});

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/*
 * Dates are built as plain strings rather than through Date arithmetic.
 *
 * `new Date('2026-07-18')` parses as UTC midnight but formats in local time, so
 * anywhere west of Greenwich every square lands on the previous day. Walking the
 * calendar with UTC accessors and formatting by hand keeps the key identical to
 * what the server sent.
 */
function toKey(date) {
    const y = date.getUTCFullYear();
    const m = String(date.getUTCMonth() + 1).padStart(2, '0');
    const d = String(date.getUTCDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

const trained = computed(() => new Set(props.heatmap?.days ?? []));

const weeks = computed(() => {
    const from = new Date(`${props.heatmap.from}T00:00:00Z`);
    const to = new Date(`${props.heatmap.to}T00:00:00Z`);
    const columns = [];

    let cursor = from;

    while (cursor <= to) {
        const week = [];

        for (let i = 0; i < 7; i++) {
            const key = toKey(cursor);

            week.push({
                key,
                trained: trained.value.has(key),
                // Days after today are drawn as absent, not as "missed" — the
                // week is not over yet.
                future: cursor > new Date(),
            });

            cursor = new Date(cursor.getTime() + 86400000);
        }

        columns.push(week);
    }

    return columns;
});

const total = computed(() => trained.value.size);
</script>

<template>
    <div>
        <header class="flex items-baseline justify-between gap-3">
            <p :class="EYEBROW">{{ __('Consistency') }}</p>
            <p class="text-xs" :class="MUTED">
                {{ __(':count days in 12 weeks', { count: total }) }}
            </p>
        </header>

        <div class="mt-4 flex gap-3">
            <!-- Only alternate labels, so seven rows of text do not crowd out
                 the squares on a narrow card. -->
            <div class="flex flex-col justify-between py-px text-[10px]" :class="MUTED">
                <span v-for="(day, i) in WEEKDAYS" :key="day" class="h-3 leading-3">
                    {{ i % 2 === 0 ? __(day) : '' }}
                </span>
            </div>

            <div class="flex flex-1 gap-1 overflow-x-auto">
                <div v-for="(week, w) in weeks" :key="w" class="flex flex-col gap-1">
                    <span
                        v-for="day in week"
                        :key="day.key"
                        class="size-3 rounded-[3px] transition-colors"
                        :class="
                            day.future
                                ? 'bg-transparent'
                                : day.trained
                                  ? 'bg-primary'
                                  : 'bg-muted'
                        "
                        :title="day.key"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
