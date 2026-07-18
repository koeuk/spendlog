<script setup>
import { computed } from 'vue';
import { Pause, Play, RotateCcw, Timer } from 'lucide-vue-next';
import { CARD, EYEBROW, MUTED } from '@/lib/appStyles';
import { formatClock } from '@/lib/exerciseStyles';
import { useSessionTimer } from '@/composables/useSessionTimer';

/**
 * A stopwatch for the session you are in the middle of.
 *
 * Deliberately not wired to anything: the one in the workout form writes its
 * reading into the duration field, but this one is for the warm-up and the walk
 * to the rack — the part of a session that happens before there is a form open
 * to put a number into. Client-side and per-page, so navigating away loses it,
 * the same trade useSessionTimer already documents.
 */
const timer = useSessionTimer();

// Nothing to reset before the clock has moved, so the button stays out of the
// way until it would do something.
const resettable = computed(
    () => timer.running.value || timer.elapsedSeconds.value > 0,
);
</script>

<template>
    <div :class="[CARD, 'anim flex items-center gap-4 p-6']">
        <span
            class="grid size-12 shrink-0 place-items-center rounded-full transition-colors"
            :class="timer.running.value ? 'bg-primary/15' : 'bg-muted'"
        >
            <Timer
                class="size-6 transition-colors"
                :class="timer.running.value ? 'text-primary' : MUTED"
                aria-hidden="true"
            />
        </span>

        <div class="min-w-0">
            <p :class="EYEBROW">{{ __('Session') }}</p>
            <p class="mt-0.5 text-3xl font-extrabold tabular-nums tracking-tight">
                {{ formatClock(timer.elapsedSeconds.value) }}
            </p>
        </div>

        <div class="ms-auto flex shrink-0 items-center gap-1.5">
            <button
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="timer.running.value ? __('Pause') : __('Start')"
                @click="timer.toggle()"
            >
                <component :is="timer.running.value ? Pause : Play" class="size-4" />
            </button>

            <button
                v-if="resettable"
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="__('Reset')"
                @click="timer.reset()"
            >
                <RotateCcw class="size-4" />
            </button>
        </div>
    </div>
</template>
