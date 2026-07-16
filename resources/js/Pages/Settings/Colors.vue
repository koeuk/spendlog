<script setup>
import { computed, onBeforeUnmount } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Input } from '@/Components/ui/input';
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
 * A swatch is one deliberate click, so it posts straight away. The button's free
 * picker cannot: a native colour input fires `input` for every pixel the pointer
 * crosses, so it posts on `change` (once, on commit) and the hex field debounces,
 * because it fires per keystroke and is half-typed for most of them.
 */
const DEBOUNCE_MS = 500;

let timer = null;

function normalise(value) {
    return String(value ?? '').trim().toLowerCase();
}

function save() {
    clearTimeout(timer);

    form.post(route('colors.update'), {
        preserveScroll: true,
        // Inertia would otherwise reset the form from the server on every reply,
        // yanking the picker out from under a still-moving pointer.
        preserveState: true,
    });
}

/** A swatch, or the picker being committed: apply now. */
function choose(field, value) {
    const next = normalise(value);

    if (form[field] === next) {
        return;
    }

    form[field] = next;
    save();
}

/** Dragging the picker: preview locally, do not post. */
function preview(field, value) {
    form[field] = normalise(value);
}

/** Typing a hex: debounce, and only chase a value that is actually a colour. */
function type(field, value) {
    form[field] = normalise(value);

    if (!/^#[0-9a-f]{6}$/i.test(form[field])) {
        return;
    }

    clearTimeout(timer);
    timer = setTimeout(save, DEBOUNCE_MS);
}

onBeforeUnmount(() => clearTimeout(timer));

/**
 * Mirrors App\Support\Color::readableForegroundTriplet, so the preview shows the
 * label the server will actually compute rather than guessing white.
 */
function luminance(hex) {
    const match = /^#([0-9a-f]{6})$/i.exec(hex ?? '');

    if (!match) {
        return 0;
    }

    const int = parseInt(match[1], 16);
    const channels = [(int >> 16) & 255, (int >> 8) & 255, int & 255].map((c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrast(a, b) {
    const [lighter, darker] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (lighter + 0.05) / (darker + 0.05);
}

const labelOn = computed(() =>
    contrast(form.button_color, '#0a0a0a') >= contrast(form.button_color, '#fafafa')
        ? '#0a0a0a'
        : '#fafafa',
);

/**
 * The same bar the server refuses on, shown live so a bad pick is obvious before
 * the round trip rather than as an error after it.
 */
const labelContrast = computed(() => contrast(form.button_color, labelOn.value));
const labelUnreadable = computed(
    () => /^#[0-9a-f]{6}$/i.test(form.button_color) && labelContrast.value < 4.5,
);
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

                    <span class="mx-1 h-6 w-px bg-border" aria-hidden="true" />

                    <!-- Anything outside the presets. The swatches are a shortcut,
                         not a fence. -->
                    <input
                        type="color"
                        :value="form.button_color"
                        :aria-label="trans('Custom button colour')"
                        class="size-9 shrink-0 cursor-pointer rounded-full border border-border bg-transparent p-1"
                        @input="preview('button_color', $event.target.value)"
                        @change="choose('button_color', $event.target.value)"
                    />
                    <Input
                        :model-value="form.button_color"
                        class="w-28 font-mono"
                        autocomplete="off"
                        spellcheck="false"
                        :aria-label="trans('Button colour hex')"
                        :aria-invalid="!!form.errors.button_color"
                        @update:model-value="type('button_color', $event)"
                    />

                    <!-- A sample, not a control: the label colour is computed from
                         the fill, so a pale pick shows dark text here exactly as it
                         will once applied. -->
                    <span
                        class="inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold"
                        :style="{ backgroundColor: form.button_color, color: labelOn }"
                    >
                        {{ __('Preview') }}
                    </span>
                </div>

                <p class="mt-2 text-xs" :class="MUTED">
                    {{ __('Used for primary buttons and the selected tab, in both light and dark mode. The label colour is picked automatically for contrast.') }}
                </p>

                <!-- The same bar the server refuses on, said before the round trip
                     rather than as an error after it. -->
                <p
                    v-if="labelUnreadable"
                    class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                >
                    {{ __('No label is readable on that colour (:ratio:1). Mid-tones cannot reach 4.5:1 against black or white — a darker or lighter shade will.', {
                        ratio: labelContrast.toFixed(1),
                    }) }}
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
