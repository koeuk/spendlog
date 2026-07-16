<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Label } from '@/Components/ui/label';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    colors: { type: Object, required: true },
    // From App\Enums\ButtonColor / BodyColor — kept server-side so the swatches,
    // the migration defaults and the validator cannot drift apart.
    button_presets: { type: Array, required: true },
    body_presets: { type: Array, required: true },
});

const form = useForm({
    button_color: props.colors.button_color,
    body_color: props.colors.body_color,
});

/*
 * There is no Save button — a colour applies the moment it is chosen.
 *
 * Both fields are now a fixed set of swatches rather than a free picker, so each
 * change is one deliberate click and can post straight away. (The debounce this
 * used to need was for the native picker, which fires per pixel of a drag.)
 */
function choose(field, value) {
    if (form[field] === value) {
        return;
    }

    form[field] = value;

    form.post(route('colors.update'), {
        preserveScroll: true,
        // Inertia would otherwise reset the form from the server on every reply.
        preserveState: true,
    });
}
</script>

<template>
    <Head :title="trans('Colours')" />

    <SettingsLayout
        :heading="trans('Colours')"
        :description="trans('The button and background colours everyone sees.')"
    >
        <div class="max-w-xl space-y-8">
            <!-- Button colour -->
            <div>
                <Label>{{ __('Button colour') }}</Label>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="preset in button_presets"
                        :key="preset.value"
                        type="button"
                        :title="preset.is_default ? `${preset.label} (${trans('Default')})` : preset.label"
                        :aria-label="preset.label"
                        :aria-pressed="form.button_color === preset.value"
                        class="grid size-9 place-items-center rounded-full ring-offset-2 transition-transform hover:scale-110 ring-offset-background"
                        :class="form.button_color === preset.value ? 'ring-2 ring-foreground' : ''"
                        :style="{ backgroundColor: preset.value }"
                        @click="choose('button_color', preset.value)"
                    >
                        <!-- The tick is on the swatch rather than beside it: on a
                             row of colours, the filled one is the answer. -->
                        <Check
                            v-if="form.button_color === preset.value"
                            class="size-4 text-white"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <p class="mt-2 text-xs" :class="MUTED">
                    {{ __('Used for primary buttons and the selected tab, in both light and dark mode.') }}
                </p>
                <p v-if="form.errors.button_color" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.button_color }}
                </p>
            </div>

            <!-- System background -->
            <div>
                <Label>{{ __('System background') }}</Label>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="preset in body_presets"
                        :key="preset.value"
                        type="button"
                        :title="preset.label"
                        :aria-label="preset.label"
                        :aria-pressed="form.body_color === preset.value"
                        class="grid size-9 place-items-center rounded-full border border-border ring-offset-2 transition-transform hover:scale-110 ring-offset-background"
                        :class="form.body_color === preset.value ? 'ring-2 ring-foreground' : ''"
                        :style="{ backgroundColor: preset.value }"
                        @click="choose('body_color', preset.value)"
                    >
                        <Check
                            v-if="form.body_color === preset.value"
                            class="size-4 text-neutral-900"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <p class="mt-2 text-xs" :class="MUTED">
                    {{ __('Tints the whole page — cards, borders and labels follow. Light mode only; dark mode keeps its own.') }}
                </p>
                <p v-if="form.errors.body_color" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.body_color }}
                </p>
            </div>

            <!-- Saved as you pick. Announced politely so a screen reader is told
                 the change landed without interrupting what it is reading. -->
            <p
                class="h-4 text-xs font-medium"
                :class="form.processing ? MUTED : 'text-[#4b9d5f] dark:text-[#6cc182]'"
                role="status"
                aria-live="polite"
            >
                <template v-if="form.processing">{{ __('Saving…') }}</template>
                <template v-else-if="form.recentlySuccessful">{{ __('Saved') }}</template>
            </p>
        </div>
    </SettingsLayout>
</template>
