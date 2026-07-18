<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Play, RotateCcw, Square, Timer } from 'lucide-vue-next';
import { CARD, CARD_ALERT, EYEBROW, MUTED } from '@/lib/appStyles';
import { formatClock } from '@/lib/exerciseStyles';
import { useRestTimer } from '@/composables/useSessionTimer';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

/**
 * A countdown you set yourself — the rest between sets.
 *
 * Client-side and per-page, so navigating away loses it, the same trade
 * useSessionTimer already documents. Nothing here is persisted: the workout form
 * is what records a session, and this is the thing you watch between them.
 */

// The rests people actually take. Anything else goes in the fields below.
const PRESETS = [60, 90, 120, 180];

const timer = useRestTimer(90);

/*
 * The custom-length dialog. Its fields are a draft rather than the timer
 * itself: typing "1" on the way to "10" would otherwise move the timer on the
 * first keystroke, and cancelling would have nothing to fall back to.
 */
const customOpen = ref(false);
const mins = ref(0);
const secs = ref(0);

// A rest under five seconds is a mistyped one, and an hour is not a rest.
function clamp(value, max) {
    return Math.min(max, Math.max(0, Math.floor(Number(value) || 0)));
}

const draftSeconds = computed(
    () => Math.min(3599, Math.max(5, clamp(mins.value, 59) * 60 + clamp(secs.value, 59))),
);

// Opens on whatever the timer is set to now, so the dialog is an edit rather
// than a blank slate.
function openCustom() {
    mins.value = Math.floor(timer.duration.value / 60);
    secs.value = timer.duration.value % 60;
    customOpen.value = true;
}

function applyCustom() {
    setDuration(draftSeconds.value);
    customOpen.value = false;
}

function setDuration(seconds) {
    timer.duration.value = seconds;
}

/*
 * useRestTimer keeps its interval alive after it hits zero, so finishing has to
 * stop it. Stopping clears `finished` with it, hence the local flag — the card
 * has to keep saying "done" after the countdown itself has been torn down.
 */
const done = ref(false);

watch(timer.finished, (finished) => {
    if (finished) {
        done.value = true;
        timer.stop();
    }
});

function begin(seconds = null) {
    done.value = false;
    timer.start(seconds);
}

function reset() {
    done.value = false;
    timer.stop();
}

// Zero once it has run out, rather than springing back to the full duration the
// moment the countdown is stopped.
const display = computed(() => (done.value ? 0 : timer.remaining.value));

const editable = computed(() => !timer.running.value && !done.value);
</script>

<template>
    <div :class="[done ? CARD_ALERT : CARD, 'anim flex flex-wrap items-center gap-x-4 gap-y-3 p-6']">
        <span
            class="grid size-12 shrink-0 place-items-center rounded-full transition-colors"
            :class="timer.running.value || done ? 'bg-primary/15' : 'bg-muted'"
        >
            <component
                :is="done ? Check : Timer"
                class="size-6 transition-colors"
                :class="timer.running.value || done ? 'text-primary' : MUTED"
                aria-hidden="true"
            />
        </span>

        <div class="min-w-0">
            <p :class="EYEBROW">{{ done ? __('Rest over') : __('Rest') }}</p>
            <p class="mt-0.5 text-3xl font-extrabold tabular-nums tracking-tight">
                {{ formatClock(display) }}
            </p>
        </div>

        <div class="ms-auto flex shrink-0 items-center gap-1.5">
            <button
                v-if="!timer.running.value"
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="__('Start')"
                @click="begin()"
            >
                <Play class="size-4" />
            </button>

            <button
                v-else
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="__('Stop')"
                @click="reset()"
            >
                <Square class="size-4" />
            </button>

            <button
                v-if="done"
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="__('Reset')"
                @click="reset()"
            >
                <RotateCcw class="size-4" />
            </button>
        </div>

        <!-- Set the length. Hidden mid-countdown, where the fields would be
             showing something other than what is on the clock. -->
        <div v-if="editable" class="flex w-full flex-wrap items-center gap-3">
            <div class="flex items-center gap-1.5">
                <label class="sr-only" for="rest_minutes">{{ __('Minutes') }}</label>
                <input
                    id="rest_minutes"
                    v-model="mins"
                    type="number"
                    min="0"
                    max="59"
                    inputmode="numeric"
                    class="h-9 w-14 rounded-xl border border-border bg-card/70 px-2 text-center text-sm tabular-nums"
                />
                <span class="text-xs font-semibold" :class="MUTED">{{ __('min') }}</span>

                <label class="sr-only" for="rest_seconds">{{ __('Seconds') }}</label>
                <input
                    id="rest_seconds"
                    v-model="secs"
                    type="number"
                    min="0"
                    max="59"
                    inputmode="numeric"
                    class="h-9 w-14 rounded-xl border border-border bg-card/70 px-2 text-center text-sm tabular-nums"
                />
                <span class="text-xs font-semibold" :class="MUTED">{{ __('sec') }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="preset in PRESETS"
                    :key="preset"
                    type="button"
                    class="h-8 rounded-full border border-border px-3 text-xs font-semibold tabular-nums transition hover:bg-muted"
                    :class="timer.duration.value === preset ? 'bg-primary text-primary-foreground' : ''"
                    @click="showDuration(preset)"
                >
                    {{ formatClock(preset) }}
                </button>
            </div>
        </div>
    </div>
</template>
