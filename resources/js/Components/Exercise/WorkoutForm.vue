<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Pause, Play, Plus, RotateCcw, Trash2 } from 'lucide-vue-next';
import {
    CalendarDate,
    DateFormatter,
    getLocalTimeZone,
    today as currentDate,
} from '@internationalized/date';
import { MUTED, PILL_ACTION, SEGMENT, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';
import { formatClock } from '@/lib/exerciseStyles';
import { trans } from '@/lib/i18n';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Button } from '@/Components/ui/button';
import { Calendar } from '@/Components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { useSessionTimer } from '@/composables/useSessionTimer';

const props = defineProps({
    // Null when adding; the presented workout when editing.
    workout: { type: Object, default: null },
    exerciseTypes: { type: Array, required: true },
});

const emit = defineEmits(['saved', 'cancel']);

const page = usePage();

const editing = computed(() => props.workout !== null);

// The unit typed into the weight fields. Sent alongside, and converted to
// kilograms server-side — the same contract the expense form has with currency.
const unit = ref(page.props.default_weight_unit ?? 'kg');

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    performed_on: props.workout?.performed_on ?? today,
    duration_seconds: props.workout?.duration_seconds ?? null,
    notes: props.workout?.notes ?? '',
    weight_unit: unit.value,
    sets: [],
});

// Same bridge as the expense form: the form holds a plain 'YYYY-MM-DD' string,
// the Calendar speaks CalendarDate.
const performedOn = computed({
    get() {
        if (!form.performed_on) {
            return undefined;
        }

        const [year, month, day] = form.performed_on.split('-').map(Number);

        return new CalendarDate(year, month, day);
    },
    set(value) {
        form.performed_on = value ? value.toString() : '';
    },
});

const dateFormatter = new DateFormatter(
    page.props.locale === 'km' ? 'km-KH' : 'en-GB',
    { dateStyle: 'medium' },
);

const performedOnLabel = computed(() =>
    performedOn.value
        ? dateFormatter.format(performedOn.value.toDate(getLocalTimeZone()))
        : trans('Pick a date'),
);

// A workout in the future is rejected server-side; block it in the picker too.
const maxDate = currentDate(getLocalTimeZone());

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
        rpe: set.rpe,
    }));
}

function toUnit(kg) {
    const value = unit.value === 'lb' ? kg / 0.45359237 : kg;

    return Math.round(value * 10) / 10;
}

seed();
watch(() => props.workout, seed);

// Switching unit rewrites what is in the fields, so the number always means what
// the label beside it says.
watch(unit, (next, previous) => {
    form.weight_unit = next;

    const factor = previous === 'lb' ? 0.45359237 : 1;
    const divisor = next === 'lb' ? 0.45359237 : 1;

    form.sets = form.sets.map((set) => ({
        ...set,
        weight:
            set.weight === null || set.weight === ''
                ? set.weight
                : Math.round(((set.weight * factor) / divisor) * 10) / 10,
    }));
});

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

// Writes the clock into the duration field rather than submitting on its own —
// the person may still want to add sets before saving.
function applyTimer() {
    form.duration_seconds = timer.elapsedSeconds.value;
}

const minutes = computed({
    get: () => (form.duration_seconds === null ? '' : Math.round(form.duration_seconds / 60)),
    set: (value) => {
        form.duration_seconds = value === '' || value === null ? null : Math.round(value * 60);
    },
});

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
        <!-- Date, duration, unit -->
        <div class="grid gap-4 sm:grid-cols-3">
            <!-- Not a <label>: the trigger is a button, which a label cannot
                 forward a click to. The span labels it instead. -->
            <div class="block">
                <span class="text-xs font-semibold">{{ __('Date') }}</span>
                <Popover>
                    <PopoverTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-1 h-10 w-full justify-start rounded-xl px-3 text-sm font-normal"
                        >
                            {{ performedOnLabel }}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0">
                        <Calendar
                            v-model="performedOn"
                            :max-value="maxDate"
                            initial-focus
                        />
                    </PopoverContent>
                </Popover>
                <p v-if="form.errors.performed_on" class="mt-1 text-xs text-red-600">
                    {{ form.errors.performed_on }}
                </p>
            </div>

            <label class="block">
                <span class="text-xs font-semibold">{{ __('Duration (minutes)') }}</span>
                <input
                    v-model.number="minutes"
                    type="number"
                    min="0"
                    class="mt-1 h-10 w-full rounded-xl border border-border bg-card/70 px-3 text-sm"
                />
            </label>

            <div>
                <span class="text-xs font-semibold">{{ __('Weight unit') }}</span>
                <div :class="[SEGMENT, 'mt-1 flex']">
                    <button
                        v-for="option in ['kg', 'lb']"
                        :key="option"
                        type="button"
                        class="flex-1 px-3 py-1.5 text-xs font-semibold transition"
                        :class="unit === option ? SEGMENT_ON : SEGMENT_OFF"
                        @click="unit = option"
                    >
                        {{ option }}
                    </button>
                </div>
            </div>
        </div>

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
                    @click="timer.toggle()"
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
                    <div class="mt-2 grid grid-cols-2 gap-2 ps-8 sm:grid-cols-3">
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

                        <label class="block">
                            <span class="text-[11px]" :class="MUTED">{{ __('RPE') }}</span>
                            <input
                                v-model.number="set.rpe"
                                type="number"
                                min="1"
                                max="10"
                                class="mt-0.5 h-9 w-full rounded-xl border border-border bg-card/70 px-2 text-sm"
                            />
                        </label>
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
