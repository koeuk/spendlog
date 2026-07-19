<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, Dumbbell, Pause, Play, RotateCcw, Square, Timer } from 'lucide-vue-next';
import { CARD, CARD_ALERT, EYEBROW, MUTED, SEGMENT, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';
import { formatClock } from '@/lib/exerciseStyles';
import { useRestTimer, useSessionTimer } from '@/composables/useSessionTimer';
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
 * The session timer, in three modes.
 *
 *   down — from the length you set, to zero
 *   up   — from zero, to the length you set
 *   open — from zero, until you stop it
 *
 * Down and up share one useRestTimer: "1:30 left" and "0:30 done of 2:00" are
 * the same clock described from opposite ends, so a second engine there would be
 * two things to keep in step. Open genuinely is a different clock — it has no
 * length to be measured against — and useSessionTimer already models it.
 *
 * Client-side and per-page, so navigating away loses it, the same trade
 * useSessionTimer already documents. Nothing here is persisted: the workout form
 * is what records a session, and this is the thing you watch between them.
 */

// The rests people actually take. Anything else goes in the dialog.
const PRESETS = [60, 90, 120, 180];

const timer = useRestTimer(90);
const stopwatch = useSessionTimer();

// 'down' and 'up' run against a set length; 'open' just runs.
const mode = ref('down');

const open = computed(() => mode.value === 'open');

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

/*
 * Leaving a mode parks the clock it was driving. Down and up hand over to each
 * other untouched — same engine, same run — but open and the countdown are
 * different engines, and letting the abandoned one keep ticking would leave an
 * interval running behind a reading nobody is looking at.
 */
watch(mode, (next, previous) => {
    if (next === 'open' && previous !== 'open') {
        timer.stop();
        done.value = false;
    }

    if (previous === 'open' && next !== 'open') {
        stopwatch.pause();
    }
});

const running = computed(() =>
    open.value ? stopwatch.running.value : timer.running.value,
);

/*
 * The primary button. Open pauses and resumes, since a stopwatch you cannot
 * pause loses the reading the moment you need both hands; the countdown stops
 * outright, because resuming a rest that has already been broken is not a thing
 * anyone wants.
 */
function primary() {
    if (open.value) {
        stopwatch.toggle();
        return;
    }

    if (timer.running.value) {
        reset();
        return;
    }

    done.value = false;
    timer.start();
}

function reset() {
    done.value = false;

    if (open.value) {
        stopwatch.reset();
        return;
    }

    timer.stop();
}

// Nothing to reset before the clock has moved.
const resettable = computed(() => {
    if (open.value) {
        return stopwatch.running.value || stopwatch.elapsedSeconds.value > 0;
    }

    return done.value;
});

/*
 * What the big number reads.
 *
 * Pinned to the end of its run once finished, rather than springing back to the
 * full duration the moment the timer is stopped — which for 'down' is zero, and
 * for 'up' is the length that was set.
 */
const display = computed(() => {
    if (open.value) {
        return stopwatch.elapsedSeconds.value;
    }

    if (done.value) {
        return mode.value === 'down' ? 0 : timer.duration.value;
    }

    return mode.value === 'down'
        ? timer.remaining.value
        : timer.duration.value - timer.remaining.value;
});

// Open has no length to set, so the lengths have nothing to offer it.
const editable = computed(() => !open.value && !timer.running.value && !done.value);

/*
 * Time actually on the clock, whichever way it was read — what gets handed to
 * the workout form. Distinct from `display`, which for a countdown is the time
 * left rather than the time spent.
 */
const elapsed = computed(() => {
    if (open.value) {
        return stopwatch.elapsedSeconds.value;
    }

    return done.value
        ? timer.duration.value
        : timer.duration.value - timer.remaining.value;
});

/**
 * Hands the reading to the workout form rather than saving anything here.
 *
 * A timer knows how long, never what of — the movements and sets still have to
 * be chosen, and that form already knows how to validate and store them. So
 * this carries the seconds over and lets the real form do the rest.
 */
function logWorkout() {
    router.get(route('exercise.workouts.create'), { duration: elapsed.value });
}
</script>

<template>
    <div :class="[done ? CARD_ALERT : CARD, 'anim flex flex-wrap items-center gap-x-4 gap-y-3 p-6']">
        <span
            class="grid size-12 shrink-0 place-items-center rounded-full transition-colors"
            :class="running || done ? 'bg-primary/15' : 'bg-muted'"
        >
            <component
                :is="done ? Check : Timer"
                class="size-6 transition-colors"
                :class="running || done ? 'text-primary' : MUTED"
                aria-hidden="true"
            />
        </span>

        <div class="min-w-0">
            <p :class="EYEBROW">
                {{ open ? __('Elapsed') : done ? __('Rest over') : __('Rest') }}
            </p>
            <p class="mt-0.5 text-3xl font-extrabold tabular-nums tracking-tight">
                {{ formatClock(display) }}
                <!-- Counting up to a length needs that length beside it, or the
                     number has nothing to be a fraction of. Open has none. -->
                <span
                    v-if="mode === 'up'"
                    class="text-base font-bold tabular-nums"
                    :class="MUTED"
                >
                    {{ __('of :length', { length: formatClock(timer.duration.value) }) }}
                </span>
            </p>
        </div>

        <div class="ms-auto flex shrink-0 items-center gap-1.5">
            <button
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="running ? (open ? __('Pause') : __('Stop')) : __('Start')"
                @click="primary()"
            >
                <component :is="running ? (open ? Pause : Square) : Play" class="size-4" />
            </button>

            <button
                v-if="resettable"
                type="button"
                class="grid size-10 place-items-center rounded-full border border-border transition hover:bg-muted"
                :aria-label="__('Reset')"
                @click="reset()"
            >
                <RotateCcw class="size-4" />
            </button>
        </div>

        <!-- One row: the lengths on the left, the direction on the right. The
             row itself always renders — the lengths are what hide mid-run,
             where they would be offering a length other than the one on the
             clock, while the direction stays switchable throughout. -->
        <div class="flex w-full flex-wrap items-center gap-1.5">
            <template v-if="editable">
                <button
                    v-for="preset in PRESETS"
                    :key="preset"
                    type="button"
                    class="h-8 rounded-full border border-border px-3 text-xs font-semibold tabular-nums transition hover:bg-muted"
                    :class="timer.duration.value === preset ? 'bg-primary text-primary-foreground' : ''"
                    @click="setDuration(preset)"
                >
                    {{ formatClock(preset) }}
                </button>

                <!-- Anything off the presets. A dialog rather than two more
                     fields on the card: this is the rare case, and the card is
                     read at a glance mid-session. -->
                <button
                    type="button"
                    class="h-8 rounded-full border border-border px-3 text-xs font-semibold transition hover:bg-muted"
                    :class="PRESETS.includes(timer.duration.value) ? '' : 'bg-primary text-primary-foreground'"
                    @click="openCustom()"
                >
                    {{ __('Custom') }}
                </button>
            </template>

            <!-- Appears once there is something to log. Sends you to the form
                 with the seconds already in it, where the movements get added. -->
            <button
                v-if="elapsed > 0"
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-full border border-border px-3 text-xs font-semibold transition hover:bg-muted"
                @click="logWorkout()"
            >
                <Dumbbell class="size-3.5" />
                {{ __('Log workout') }}
            </button>

            <!-- Switchable mid-run: it changes how the same clock is read, not
                 what it is doing. -->
            <div :class="[SEGMENT, 'ms-auto']" role="group" :aria-label="__('Direction')">
                <button
                    type="button"
                    class="px-3 py-1 text-xs font-semibold transition"
                    :class="mode === 'down' ? SEGMENT_ON : SEGMENT_OFF"
                    :aria-pressed="mode === 'down'"
                    @click="mode = 'down'"
                >
                    {{ __('Down') }}
                </button>
                <button
                    type="button"
                    class="px-3 py-1 text-xs font-semibold transition"
                    :class="mode === 'up' ? SEGMENT_ON : SEGMENT_OFF"
                    :aria-pressed="mode === 'up'"
                    @click="mode = 'up'"
                >
                    {{ __('Up') }}
                </button>
                <!-- No length, no end: runs until you stop it. -->
                <button
                    type="button"
                    class="px-3 py-1 text-xs font-semibold transition"
                    :class="mode === 'open' ? SEGMENT_ON : SEGMENT_OFF"
                    :aria-pressed="mode === 'open'"
                    @click="mode = 'open'"
                >
                    {{ __('Open') }}
                </button>
            </div>
        </div>

        <Dialog v-model:open="customOpen">
            <DialogContent class="sm:max-w-xs">
                <DialogHeader>
                    <DialogTitle>{{ __('Rest length') }}</DialogTitle>
                    <DialogDescription>
                        {{ __('How long between sets.') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="flex items-end justify-center gap-2 py-2" @submit.prevent="applyCustom">
                    <div>
                        <label class="text-xs font-semibold" for="rest_minutes">
                            {{ __('Minutes') }}
                        </label>
                        <input
                            id="rest_minutes"
                            v-model="mins"
                            type="number"
                            min="0"
                            max="59"
                            inputmode="numeric"
                            class="mt-1 h-11 w-20 rounded-xl border border-border bg-card/70 px-2 text-center text-lg font-bold tabular-nums"
                        />
                    </div>

                    <span class="pb-3 text-lg font-bold" :class="MUTED">:</span>

                    <div>
                        <label class="text-xs font-semibold" for="rest_seconds">
                            {{ __('Seconds') }}
                        </label>
                        <input
                            id="rest_seconds"
                            v-model="secs"
                            type="number"
                            min="0"
                            max="59"
                            inputmode="numeric"
                            class="mt-1 h-11 w-20 rounded-xl border border-border bg-card/70 px-2 text-center text-lg font-bold tabular-nums"
                        />
                    </div>
                </form>

                <!-- Shows what will actually be set, after the 5s–59:59 clamp,
                     rather than letting a typo surprise you on start. -->
                <p class="text-center text-xs font-semibold" :class="MUTED">
                    {{ __('Rest of :length', { length: formatClock(draftSeconds) }) }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="customOpen = false">
                        {{ __('Cancel') }}
                    </Button>
                    <Button type="button" @click="applyCustom()">{{ __('Set') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
