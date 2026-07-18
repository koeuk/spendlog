<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Pause, Play, Plus, RotateCcw, Trash2 } from 'lucide-vue-next';
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

const typeByUuid = computed(
    () => new Map(props.exerciseTypes.map((type) => [type.uuid, type])),
);

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
 * Weights come back in kilograms and are shown in the viewer's unit, so the
 * number in the field matches what they would have typed rather than the stored
 * canonical value.
 */
function seed() {
    form.sets = (props.workout?.sets ?? []).map((set) => ({
        exercise_type_uuid: set.exercise_type_uuid,
        reps: set.reps,
        weight: set.weight_kg === null ? null : toUnit(set.weight_kg),
        distance_m: set.distance_m,
        duration_seconds: set.duration_seconds,
        // No longer editable, but still carried: an update rewrites every set
        // row, so dropping it here would null out the values already recorded.
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

function isCardio(index) {
    return Boolean(typeByUuid.value.get(form.sets[index]?.exercise_type_uuid)?.is_cardio);
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

// Records the clock against the workout rather than submitting on its own —
// the person may still want to add sets before saving.
function applyTimer() {
    form.duration_seconds = timer.elapsedSeconds.value;
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
    <form class="space-y-5" @submit.prevent="submit">
        <!-- Date and duration have no fields of their own: the stopwatch below
             sets both. The date is stamped when the clock starts, the duration
             when you take the reading. -->

        <!-- The stopwatch. Client-side: it fills the duration field, and the
             workout is what gets saved. -->
        <div class="flex items-center gap-3 rounded-2xl border border-border bg-muted/40 px-4 py-3">
            <span class="text-2xl font-extrabold tabular-nums tracking-tight">
                {{ formatClock(timer.elapsedSeconds.value) }}
            </span>

            <div class="ms-auto flex items-center gap-1.5">
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-full border border-border transition hover:bg-card"
                    :aria-label="timer.running.value ? __('Pause') : __('Start')"
                    @click="toggleTimer()"
                >
                    <component :is="timer.running.value ? Pause : Play" class="size-4" />
                </button>

                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-full border border-border transition hover:bg-card"
                    :aria-label="__('Reset')"
                    @click="timer.reset()"
                >
                    <RotateCcw class="size-4" />
                </button>

                <button
                    type="button"
                    class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold transition hover:bg-card"
                    @click="applyTimer"
                >
                    {{ __('Use as duration') }}
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

                    <!-- Which fields show follows the movement's own is_cardio
                         flag, so a run asks for distance and time while a press
                         asks for reps and load. -->
                    <div class="mt-2 grid grid-cols-2 gap-2 ps-8">
                        <template v-if="isCardio(index)">
                            <label class="block">
                                <span class="text-[11px]" :class="MUTED">{{ __('Distance (m)') }}</span>
                                <input
                                    v-model.number="set.distance_m"
                                    type="number"
                                    min="0"
                                    class="mt-0.5 h-9 w-full rounded-xl border border-border bg-card/70 px-2 text-sm"
                                />
                            </label>
                            <label class="block">
                                <span class="text-[11px]" :class="MUTED">{{ __('Time (s)') }}</span>
                                <input
                                    v-model.number="set.duration_seconds"
                                    type="number"
                                    min="0"
                                    class="mt-0.5 h-9 w-full rounded-xl border border-border bg-card/70 px-2 text-sm"
                                />
                            </label>
                        </template>

                        <template v-else>
                            <label class="block">
                                <span class="text-[11px]" :class="MUTED">{{ __('Reps') }}</span>
                                <input
                                    v-model.number="set.reps"
                                    type="number"
                                    min="1"
                                    class="mt-0.5 h-9 w-full rounded-xl border border-border bg-card/70 px-2 text-sm"
                                />
                            </label>
                            <label class="block">
                                <span class="text-[11px]" :class="MUTED">
                                    {{ __('Weight') }} ({{ unit }})
                                </span>
                                <input
                                    v-model.number="set.weight"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    class="mt-0.5 h-9 w-full rounded-xl border border-border bg-card/70 px-2 text-sm"
                                />
                            </label>
                        </template>
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

        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="rounded-full border border-border px-4 text-sm font-semibold transition hover:bg-muted"
                :class="PILL_ACTION"
                @click="emit('cancel')"
            >
                {{ __('Cancel') }}
            </button>

            <button
                type="submit"
                class="bg-primary text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                :class="PILL_ACTION"
                :disabled="form.processing"
            >
                {{ editing ? __('Save changes') : __('Log workout') }}
            </button>
        </div>
    </form>
</template>
