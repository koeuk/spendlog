<script setup>
import { Head, router } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import ExerciseTypeForm from '@/Components/Exercise/ExerciseTypeForm.vue';
import { trans } from '@/lib/i18n';

/**
 * Add or edit a movement on its own screen.
 *
 * Five fields, two of them swatch grids, plus a muscle-group picker that is
 * itself a bottom sheet on a phone — which in a dialog meant a sheet opening on
 * top of a modal, covering the form behind it.
 */
defineProps({
    // The record being edited; absent when adding.
    type: { type: Object, default: null },
    muscle_groups: { type: Array, required: true },
});

const backHref = route('exercise.types.index');

function leave() {
    router.get(backHref);
}
</script>

<template>
    <Head :title="type ? trans('Edit movement') : trans('Add movement')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="type ? __('Edit movement') : __('Add movement')"
        :back-label="__('Back to movements')"
    >
        <ExerciseTypeForm
            :type="type"
            :muscle-groups="muscle_groups"
            @cancel="leave"
        />
    </FormScreenLayout>
</template>
