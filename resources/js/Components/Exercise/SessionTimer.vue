<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Pause, Play, RotateCcw, Square, Timer } from 'lucide-vue-next';
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
 * A timer you set yourself — the rest between sets.
 *
 * Runs in either direction over the same length: down from it, or up from zero
 * towards it. Both readings come off one useRestTimer, since "1:30 left" and
 * "0:30 done of 2:00" are the same clock described from opposite ends — running
 * a second engine for the count-up would be two things to keep in step.
 *
 * Client-side and per-page, so navigating away loses it, the same trade
 * useSessionTimer already documents. Nothing here is persisted: the workout form
 * is what records a session, and this is the thing you watch between them.
 */

// The rests people actually take. Anything else goes in the dialog.
const PRESETS = [60, 90, 120, 180];

const timer = useRestTimer(90);

// 'down' counts to zero; 'up' counts from zero to the length that was set.
const mode = ref('down');

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

/*
 * What the big number reads.
 *
 * Pinned to the end of its run once finished, rather than springing back to the
 * full duration the moment the timer is stopped — which for 'down' is zero, and
 * for 'up' is the length that was set.
 */
const display = computed(() => {
    if (done.value) {
        return mode.value === 'down' ? 0 : timer.duration.value;
    }

    return mode.value === 'down'
        ? timer.remaining.value
        : timer.duration.value - timer.remaining.value;
});

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
                <!-- Counting up needs the target beside it, or the number has
                     nothing to be a fraction of. -->
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
