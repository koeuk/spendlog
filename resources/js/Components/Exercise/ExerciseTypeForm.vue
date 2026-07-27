<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import FormActions from '@/Components/FormActions.vue';
import { PILL_ACTION } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import {
    EXERCISE_COLOR_NAMES,
    EXERCISE_ICON_NAMES,
    exerciseColor,
    exerciseIcon,
} from '@/lib/exerciseStyles';

/**
 * The movement form, lifted out of the Types index when it stopped being a
 * dialog.
 *
 * Owns its own useForm and submit, the same shape WorkoutForm has: the page
 * decides where to go afterwards, the form decides what to send.
 */
const props = defineProps({
    // Null when adding; the type being edited otherwise.
    type: { type: Object, default: null },
    muscleGroups: { type: Array, required: true },
});

const emit = defineEmits(['cancel']);

const editing = computed(() => props.type !== null);

const form = useForm({
    // One name. The column behind it is still translatable JSON, but the value
    // typed here is stored under the fallback locale — see TranslatableInput.
    name: props.type?.name ?? '',
    muscle_group: props.type?.muscle_group ?? 'chest',
    is_cardio: props.type?.is_cardio ?? false,
    color: props.type?.color ?? null,
    icon: props.type?.icon ?? null,
});

function submit() {
    if (editing.value) {
        form.put(route('exercise.types.update', props.type.uuid));
    } else {
        form.post(route('exercise.types.store'));
    }
}
</script>

<template>
    <!-- Capped like the other forms: the card is page-width now, and these
         are short single-line controls that read badly stretched across it. -->
    <form class="flex flex-1 flex-col space-y-4" @submit.prevent="submit">
        <div>
            <label for="name" class="text-xs font-semibold">{{ trans('Name') }}</label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                autocomplete="off"
                class="mt-1 h-10 w-full rounded-xl border border-border bg-card/70 px-3 text-sm"
                placeholder="e.g. Bench Press"
                required
                :aria-invalid="!!form.errors.name"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.name }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Not a <label>: the trigger is a button, which a label cannot
                 forward a click to. The span labels it instead. -->
            <div class="block">
                <span class="text-xs font-semibold">{{ trans('Muscle group') }}</span>
                <SearchableSelect
                    v-model="form.muscle_group"
                    :options="muscleGroups"
                    :label="trans('Muscle group')"
                    :search-placeholder="trans('Search muscle groups…')"
                    :empty-text="trans('No muscle group found.')"
                    align="start"
                    trigger-class="mt-1 h-10 w-full rounded-xl border border-border bg-card/70 px-3 text-sm"
                    content-class="w-[--reka-popover-trigger-width]"
                />
            </div>

            <label class="mt-6 flex items-center gap-2">
                <input v-model="form.is_cardio" type="checkbox" class="rounded" />
                <span class="text-xs font-semibold">
                    {{ trans('Logged as distance and time') }}
                </span>
            </label>
        </div>

        <!-- Colour and icon are optional: leaving them blank takes the muscle
             group's colour, which is what keeps the dashboard breakdown readable
             by group. -->
        <div>
            <span class="text-xs font-semibold">{{ trans('Colour') }}</span>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                <button
                    v-for="name in EXERCISE_COLOR_NAMES"
                    :key="name"
                    type="button"
                    class="size-7 rounded-full ring-2 ring-offset-2 ring-offset-background transition"
                    :class="[
                        exerciseColor(name).dot,
                        form.color === name ? 'ring-primary' : 'ring-transparent',
                    ]"
                    :aria-label="name"
                    @click="form.color = form.color === name ? null : name"
                />
            </div>
        </div>

        <div>
            <span class="text-xs font-semibold">{{ trans('Icon') }}</span>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                <button
                    v-for="name in EXERCISE_ICON_NAMES"
                    :key="name"
                    type="button"
                    class="grid size-9 place-items-center rounded-xl border transition"
                    :class="
                        form.icon === name
                            ? 'border-primary bg-primary/10'
                            : 'border-border hover:bg-muted'
                    "
                    :aria-label="name"
                    @click="form.icon = form.icon === name ? null : name"
                >
                    <component :is="exerciseIcon(name)" class="size-4" />
                </button>
            </div>
        </div>

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
                    {{ trans('Save') }}
                </button>
            </template>
        </FormActions>
    </form>
</template>
