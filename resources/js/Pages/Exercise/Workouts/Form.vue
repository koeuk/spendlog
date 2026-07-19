<script setup>
import { Head, router } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import WorkoutForm from '@/Components/Exercise/WorkoutForm.vue';
import { trans } from '@/lib/i18n';

/**
 * Log or edit a workout on its own screen.
 *
 * The longest form in the app: a running clock, a set list that grows a row at a
 * time, and a movement picker on every one of those rows. In a dialog the picker
 * was a bottom sheet opening inside a modal — two stacked layers on a phone,
 * with the sheet covering the list it was being added to.
 *
 * The timer is the other reason. A dialog holds no history entry, so Android's
 * back gesture closed the whole page mid-session and took the reading with it.
 */
defineProps({
    // The record being edited; absent when logging a new session.
    workout: { type: Object, default: null },
    exercise_types: { type: Array, required: true },
    // The dashboard timer's reading, handed over in the URL. Clamped server-side.
    initial_duration_seconds: { type: Number, default: 0 },
});

const backHref = route('exercise.workouts.index');

function leave() {
    router.get(backHref);
}
</script>

<template>
    <Head :title="workout ? trans('Edit workout') : trans('Log a workout')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="workout ? __('Edit workout') : __('Log a workout')"
        :back-label="__('Back to workouts')"
    >
        <!-- WorkoutForm owns its own useForm and submit, so the page only
             says where to go afterwards. Saving redirects to the index
             server-side; cancel is the same trip without one. -->
        <WorkoutForm
            :workout="workout"
            :exercise-types="exercise_types"
            :initial-duration-seconds="initial_duration_seconds"
            @cancel="leave"
        />
    </FormScreenLayout>
</template>
