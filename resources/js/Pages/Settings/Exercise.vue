<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { MUTED, PILL_ACTION, SEGMENT, SEGMENT_ON, SEGMENT_OFF } from '@/lib/appStyles';

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

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="bg-primary text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                    :class="PILL_ACTION"
                    :disabled="form.processing"
                >
                    {{ __('Save') }}
                </button>
            </div>
        </form>
    </SettingsLayout>
</template>
