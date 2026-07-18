<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from '@/lib/i18n';
import { Dumbbell, Pencil, Plus, Timer, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import ExerciseBadge from '@/Components/Exercise/ExerciseBadge.vue';
import WorkoutForm from '@/Components/Exercise/WorkoutForm.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { CARD, EYEBROW, MUTED, PILL_ACTION } from '@/lib/appStyles';
import { formatDistance, formatDuration, formatWeight } from '@/lib/exerciseStyles';

const props = defineProps({
    workouts: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    exercise_types: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const unit = computed(() => page.props.default_weight_unit ?? 'kg');

// null = closed, 'new' = adding, an object = editing that workout.
const editing = ref(null);

/*
 * Arriving from the dashboard timer with ?duration=173 opens the form on the
 * spot with that many seconds already in it. Read from the URL rather than a
 * prop so the timer can hand over without the controller having to know the
 * dashboard exists.
 */
const handedOverSeconds = ref(null);

const seconds = Number(new URLSearchParams(window.location.search).get('duration'));

if (Number.isFinite(seconds) && seconds > 0) {
    handedOverSeconds.value = Math.floor(seconds);
    editing.value = 'new';
}

function close() {
    editing.value = null;
    // One handover per arrival: reopening the form afterwards should be blank,
    // not stamped with a timer reading from earlier in the session.
    handedOverSeconds.value = null;
}

// The dialog wants a boolean, but `editing` has to stay the source of truth —
// it is what tells WorkoutForm whether this is a create or an update.
const dialogOpen = computed({
    get: () => editing.value !== null,
    set: (value) => {
        if (!value) {
            close();
        }
    },
});

const deleteForm = useForm({});

// The workout awaiting confirmation. Holding the row itself, not just a flag,
// lets the prompt name what is about to go.
const confirming = ref(null);
const deleting = ref(null);

function confirmDestroy(workout) {
    confirming.value = workout;
}

function destroy() {
    const workout = confirming.value;

    if (!workout) {
        return;
    }

    deleting.value = workout.uuid;

    deleteForm.delete(route('exercise.workouts.destroy', workout.uuid), {
        preserveScroll: true,
        // Closed on success, not on click: a failed delete should leave the
        // prompt up rather than vanish with the row still there.
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = null;
        },
    });
}

/** Sets grouped by movement, so "3 × Bench Press" reads as one line. */
function grouped(workout) {
    const groups = new Map();

    for (const set of workout.sets) {
        const key = set.exercise_type_uuid ?? set.exercise;

        if (!groups.has(key)) {
            groups.set(key, { exercise: set.exercise, color: set.color, icon: set.icon, is_cardio: set.is_cardio, sets: [] });
        }

        groups.get(key).sets.push(set);
    }

    // Sets logged from the movement dropdown alone have no numbers to print, so
    // they contribute no pill — a row of empty dashes says less than no row.
    return [...groups.values()].map((group) => ({
        ...group,
        labels: group.sets
            .map((set) => ({ uuid: set.uuid, label: setLabel(set) }))
            .filter((entry) => entry.label !== null),
    }));
}

function setLabel(set) {
    if (set.is_cardio) {
        return [formatDistance(set.distance_m), formatDuration(set.duration_seconds)]
            .filter((part) => part !== '—')
            .join(' · ') || null;
    }

    if (set.reps === null && set.weight_kg === null) {
        return null;
    }

    const reps = set.reps ?? '—';

    return set.weight_kg === null
        ? `${reps}`
        : `${reps} × ${formatWeight(set.weight_kg, unit.value)}`;
}

const dateFormatter = new Intl.DateTimeFormat(page.props.locale === 'km' ? 'km-KH' : 'en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

function formatDate(value) {
    return dateFormatter.format(new Date(`${value}T00:00:00`));
}
</script>

<template>
    <Head :title="trans('Workouts')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ trans('Exercise') }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ trans('Workouts') }}
                    </h1>
                </div>

                <button
                    v-if="can.create"
                    type="button"
                    class="bg-primary text-primary-foreground inline-flex items-center gap-2 transition hover:opacity-90"
                    :class="PILL_ACTION"
                    @click="editing = 'new'"
                >
                    <Plus class="size-4" />
                    {{ trans('Log a workout') }}
                </button>
            </div>
        </template>

        <!-- Matches the Movements form: the list stays where it is instead of
             being pushed down the page while you type. -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-2xl max-h-[85svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing === 'new' ? trans('Log a workout') : trans('Edit workout') }}
                    </DialogTitle>
                </DialogHeader>

                <WorkoutForm
                    :workout="editing === 'new' ? null : editing"
                    :exercise-types="exercise_types"
                    :initial-duration-seconds="handedOverSeconds"
                    @saved="close"
                    @cancel="close"
                />
            </DialogContent>
        </Dialog>

        <div class="space-y-3">
            <p
                v-if="!workouts.length"
                :class="[CARD, 'anim px-6 py-16 text-center text-sm']"
                style="--d: 60ms"
            >
                <span :class="MUTED">{{ trans('No workouts logged yet.') }}</span>
            </p>

            <article
                v-for="(workout, index) in workouts"
                :key="workout.uuid"
                :class="[CARD, 'anim p-6']"
                :style="{ '--d': `${60 + index * 40}ms` }"
            >
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold">{{ formatDate(workout.performed_on) }}</p>

                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs" :class="MUTED">
                            <span v-if="workout.volume_kg > 0" class="inline-flex items-center gap-1">
                                <Dumbbell class="size-3.5" />
                                {{ formatWeight(workout.volume_kg, unit) }}
                            </span>
                            <span>{{ trans(':count sets', { count: workout.sets.length }) }}</span>
                        </div>
                    </div>

                    <!-- How long it took now carries the session, so it reads at
                         the size the numbers it replaced used to. Centred by
                         growing to fill the gap between the date and the
                         actions, rather than by absolute positioning — this way
                         it wraps to its own line instead of overlapping them. -->
                    <span
                        v-if="workout.duration_seconds"
                        class="flex flex-1 items-center justify-center gap-1.5 text-lg font-extrabold tabular-nums tracking-tight"
                    >
                        <Timer class="size-4 shrink-0" :class="MUTED" />
                        {{ formatDuration(workout.duration_seconds) }}
                    </span>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-full text-neutral-400 transition hover:bg-muted hover:text-foreground"
                            :aria-label="trans('Edit')"
                            @click="editing = workout"
                        >
                            <Pencil class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-full text-neutral-400 transition hover:bg-red-500/10 hover:text-red-600"
                            :aria-label="trans('Delete')"
                            @click="confirmDestroy(workout)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </header>

                <ul v-if="workout.sets.length" class="mt-4 space-y-2.5">
                    <li v-for="group in grouped(workout)" :key="group.exercise">
                        <ExerciseBadge
                            :name="group.exercise"
                            :color="group.color"
                            :icon="group.icon"
                            class="text-sm font-semibold"
                        />

                        <div v-if="group.labels.length" class="mt-1 flex flex-wrap gap-1.5 ps-[38px]">
                            <span
                                v-for="entry in group.labels"
                                :key="entry.uuid"
                                class="rounded-full bg-muted px-2.5 py-1 text-[11px] font-semibold tabular-nums"
                            >
                                {{ entry.label }}
                            </span>
                        </div>
                    </li>
                </ul>

                <p v-if="workout.notes" class="mt-4 text-xs italic" :class="MUTED">
                    {{ workout.notes }}
                </p>
            </article>

            <Pagination
                v-if="pagination?.total"
                :meta="pagination"
                :only="['workouts', 'pagination']"
            />
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="trans('Delete this workout?')"
            :description="
                confirming
                    ? trans('The session on :date and its :count sets will be removed. This cannot be undone.', {
                          date: formatDate(confirming.performed_on),
                          count: confirming.sets.length,
                      })
                    : ''
            "
            :confirm-label="trans('Delete')"
            :cancel-label="trans('Cancel')"
            :processing="deleting !== null"
            :processing-label="trans('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="destroy"
        />
    </AuthenticatedLayout>
</template>
