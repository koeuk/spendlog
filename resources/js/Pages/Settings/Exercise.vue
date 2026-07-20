<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { FORM_ACTION, MUTED, SEGMENT, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';

const props = defineProps({
    exercise: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});

const form = useForm({
    default_weight_unit: props.exercise.default_weight_unit,
});

function submit() {
    form.post(route('exercise-settings.update'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('Exercise')" />

    <SettingsLayout
        :heading="__('Exercise')"
        :description="__('How the workout form starts out. Weights are always stored in kilograms — this only changes what you type in.')"
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <span class="text-sm font-semibold">{{ __('Weight unit') }}</span>
                <p class="mt-0.5 text-xs" :class="MUTED">
                    {{ __('You can still switch unit on any individual workout.') }}
                </p>

                <div :class="[SEGMENT, 'mt-3 inline-flex']">
                    <button
                        v-for="unit in units"
                        :key="unit.value"
                        type="button"
                        class="px-4 py-2 text-sm font-semibold transition"
                        :class="form.default_weight_unit === unit.value ? SEGMENT_ON : SEGMENT_OFF"
                        @click="form.default_weight_unit = unit.value"
                    >
                        {{ unit.label }}
                    </button>
                </div>

                <p v-if="form.errors.default_weight_unit" class="mt-2 text-xs text-red-600">
                    {{ form.errors.default_weight_unit }}
                </p>
            </div>

            <!-- Was a hand-rolled PILL_ACTION button, the rounded-full shape the
                 page headers use for "Add expense". Every other settings form
                 saves with the shared Button, so this one does too. -->
            <div class="flex sm:justify-end">
                <Button type="submit" :disabled="form.processing" :class="FORM_ACTION">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
            </div>
        </form>
    </SettingsLayout>
</template>
