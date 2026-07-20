<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Pause, Play, Plus, RotateCcw, Timer, Trash2 } from 'lucide-vue-next';
import { getLocalTimeZone, today as currentDate } from '@internationalized/date';
import { MUTED, PILL_ACTION } from '@/lib/appStyles';
import { formatClock } from '@/lib/exerciseStyles';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useSessionTimer } from '@/composables/useSessionTimer';

const props = defineProps({
    // Null when adding; the presented workout when editing.
    workout: { type: Object, default: null },
    exerciseTypes: { type: Array, required: true },
    // Seconds to start the duration field on, handed over by the dashboard
    // timer. Ignored when editing, where the saved duration is the truth.
    initialDurationSeconds: { type: Number, default: null },
});

const emit = defineEmits(['saved', 'cancel']);

const page = usePage();

const editing = computed(() => props.workout !== null);

// The unit typed into the weight fields. Sent alongside, and converted to
// kilograms server-side — the same contract the expense form has with currency.
const unit = ref(page.props.default_weight_unit ?? 'kg');

// Local, not toISOString(): that returns UTC, so anywhere east of Greenwich a
// session logged in the small hours would be dated to the day before.
function localToday() {
    return currentDate(getLocalTimeZone()).toString();
}

const seededOn = localToday();

const form = useForm({
    performed_on: props.workout?.performed_on ?? seededOn,
    duration_seconds: props.workout?.duration_seconds ?? props.initialDurationSeconds,
    notes: props.workout?.notes ?? '',
    weight_unit: unit.value,
    sets: [],
});

// SearchableSelect wants {value, label}. The star marks a movement the viewer
// added themselves, exactly as the old <option> did.
const typeOptions = computed(() =>
    props.exerciseTypes.map((type) => ({
        value: type.uuid,
        label: type.is_mine ? `${type.name} ★` : type.name,
    })),
);

/**
 * Seed the rows from the workout being edited.
 *
 * Only the movement is editable now, but every measure is still carried:
 * an update rewrites each set row wholesale, so anything dropped here would be
 * nulled out in the database on the next save. Weights come back in kilograms
 * and are held in the viewer's unit, the same shape the form has always sent.
 */
function seed() {
    form.sets = (props.workout?.sets ?? []).map((set) => ({
        exercise_type_uuid: set.exercise_type_uuid,
        reps: set.reps,
        weight: set.weight_kg === null ? null : toUnit(set.weight_kg),
        distance_m: set.distance_m,
        duration_seconds: set.duration_seconds,
        rpe: set.rpe,
    }));
}

function toUnit(kg) {
    const value = unit.value === 'lb' ? kg / 0.45359237 : kg;

    return Math.round(value * 10) / 10;
}

seed();
watch(() => props.workout, seed);

function addSet() {
    // Repeats the last exercise: a session is usually several sets of the same
    // movement, so defaulting to a blank picker means re-choosing it every time.
    const previous = form.sets[form.sets.length - 1];

    form.sets.push({
        exercise_type_uuid: previous?.exercise_type_uuid ?? props.exerciseTypes[0]?.uuid ?? '',
        reps: previous?.reps ?? null,
        weight: previous?.weight ?? null,
        distance_m: null,
        duration_seconds: null,
        rpe: null,
    });
}

function removeSet(index) {
    form.sets.splice(index, 1);
}

/* --- the stopwatch ---------------------------------------------------- */

const timer = useSessionTimer();

/**
 * Starting the clock is what dates the session.
 *
 * The dialog can sit open a while before the first rep — across midnight, even
 * — so the date it was opened on is the wrong answer. Restamped on the first
 * start only: a resume after a pause must not move the date, and a date the
 * person picked by hand outranks the clock, so an edited field is left alone.
 */
let stamped = false;

function toggleTimer() {
    const untouched = form.performed_on === seededOn;

    if (!timer.running.value && !stamped && !editing.value && untouched) {
        stamped = true;
        form.performed_on = localToday();
    }

    timer.toggle();
}

/*
 * The reading is the duration — there is no separate field to copy it into, so
 * it follows the clock rather than waiting to be taken.
 *
 * Guarded on `> 0` because the timer starts at zero: writing that through on
 * mount would wipe a duration the form opened with, either handed over by the
 * dashboard timer or already saved on the workout being edited.
 */
watch(timer.elapsedSeconds, (seconds) => {
    if (seconds > 0) {
        form.duration_seconds = seconds;
    }
});

/*
 * The number on the card. Reads the form, not the clock: a duration handed over
 * by the dashboard timer arrives with the stopwatch still at zero, and showing
 * the clock would report 00:00 for a session that already has a length.
 */
const duration = computed(() => form.duration_seconds ?? 0);

// What the form opened on, to fall back to when the clock is wound back.
const openedWith = props.workout?.duration_seconds ?? props.initialDurationSeconds;

// Resetting means "that reading was wrong", so the duration goes back to what
// was there before the clock ran rather than to nothing.
function resetTimer() {
    timer.reset();
    form.duration_seconds = openedWith;
}

/* --- submit ----------------------------------------------------------- */

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!editing.value) {
                form.reset('notes', 'duration_seconds');
                form.sets = [];
                timer.reset();
            }

            emit('saved');
        },
    };

    if (editing.value) {
        form.put(route('exercise.workouts.update', props.workout.uuid), options);
    } else {
        form.post(route('exercise.workouts.store'), options);
    }
}
</script>

<template>
    <!-- Capped like the other forms: the card is page-width now, and these
         are short single-line controls that read badly stretched across it. -->
    <form class="flex flex-1 flex-col space-y-5" @submit.prevent="submit">
        <!-- Date and duration have no fields of their own: the stopwatch sets
             both. The date is stamped when the clock starts, and the duration
             tracks the reading — hence the caption, which is the only thing
             saying so now that neither field is on screen. -->
        <div class="flex items-center gap-3 rounded-2xl border border-border bg-muted/40 px-4 py-3">
            <span
                class="grid size-11 shrink-0 place-items-center rounded-full transition-colors"
                :class="timer.running.value ? 'bg-primary/15' : 'bg-muted'"
            >
                <Timer
                    class="size-5 transition-colors"
                    :class="timer.running.value ? 'text-primary' : MUTED"
                    aria-hidden="true"
                />
            </span>

            <div class="min-w-0">
                <p class="text-2xl font-extrabold tabular-nums tracking-tight">
                    {{ formatClock(duration) }}
                </p>
                <p class="text-[11px] font-semibold" :class="MUTED">
                    {{ __("This session's length") }}
                </p>
            </div>

            <div class="ms-auto flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-full border border-border transition hover:bg-card"
                    :aria-label="timer.running.value ? __('Pause') : __('Start')"
                    @click="toggleTimer()"
                >
                    <component :is="timer.running.value ? Pause : Play" class="size-4" />
                </button>

                <button
                    v-if="duration > 0"
                    type="button"
                    class="grid size-9 place-items-center rounded-full border border-border transition hover:bg-card"
                    :aria-label="__('Reset')"
                    @click="resetTimer()"
                >
                    <RotateCcw class="size-4" />
                </button>
            </div>
        </div>

        <!-- Sets -->
        <div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold">{{ __('Sets') }}</span>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-xs font-semibold transition hover:bg-muted"
                    @click="addSet"
                >
                    <Plus class="size-3.5" />
                    {{ __('Add set') }}
                </button>
            </div>

            <p v-if="!form.sets.length" class="mt-3 text-xs" :class="MUTED">
                {{ __('A session with no sets is fine — a timed run counts on its own.') }}
            </p>

            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="(set, index) in form.sets"
                    :key="index"
                    class="rounded-2xl border border-border bg-card/50 p-3"
                >
                    <div class="flex items-center gap-2">
                        <span class="w-6 shrink-0 text-xs font-bold tabular-nums" :class="MUTED">
                            {{ index + 1 }}
                        </span>

                        <SearchableSelect
                            v-model="set.exercise_type_uuid"
                            :options="typeOptions"
                            :label="__('Movement')"
                            :search-placeholder="__('Search movements…')"
                            :empty-text="__('No movement found.')"
                            align="start"
                            trigger-class="h-9 min-w-0 flex-1 rounded-xl border border-border bg-card/70 px-2 text-sm"
                            content-class="w-[--reka-popover-trigger-width]"
                        />

                        <button
                            type="button"
                            class="grid size-8 shrink-0 place-items-center rounded-full text-neutral-400 transition hover:bg-red-500/10 hover:text-red-600"
                            :aria-label="__('Remove set')"
                            @click="removeSet(index)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>

                </li>
            </ul>
        </div>

        <label class="block">
            <span class="text-xs font-semibold">{{ __('Notes') }}</span>
            <textarea
                v-model="form.notes"
                rows="2"
                class="mt-1 w-full rounded-xl border border-border bg-card/70 px-3 py-2 text-sm"
            />
        </label>

        <FormActions>
            <template #cancel>
                <button
                    type="button"
                    class="w-full rounded-full border border-border px-4 text-sm font-semibold transition hover:bg-muted max-sm:h-12 sm:w-auto"
                    :class="PILL_ACTION"
                    @click="emit('cancel')"
                >
                    {{ trans('Cancel') }}
                </button>
            </template>

            <template #submit>
                <button
                    type="submit"
                    class="w-full bg-primary text-primary-foreground transition hover:opacity-90 disabled:opacity-50 max-sm:h-12 sm:w-auto"
                    :class="PILL_ACTION"
                    :disabled="form.processing"
                >
                    {{ editing ? trans('Save changes') : trans('Log workout') }}
                </button>
            </template>
        </FormActions>
    </form>
</template>
