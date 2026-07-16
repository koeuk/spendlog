<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { trans } from '@/lib/i18n';

const props = defineProps({
    colors: { type: Object, required: true },
    // From App\Enums\BodyColor — kept server-side so the swatches, the enum and
    // the migration default cannot drift apart.
    body_presets: { type: Array, required: true },
});

const form = useForm({
    button_color: props.colors.button_color,
    body_color: props.colors.body_color,
});

/**
 * <input type="color"> always reports lower case, but a pasted value may not.
 * Normalising on the way in keeps the preset comparison a plain string match
 * rather than a case-insensitive one at every call site.
 */
function setColor(field, value) {
    form[field] = String(value ?? '').trim().toLowerCase();
}

const usingPreset = computed(() =>
    props.body_presets.some((preset) => preset.value === form.body_color),
);

/**
 * Mirrors App\Support\Color::readableForegroundTriplet so the preview shows the
 * label colour the server will actually compute, rather than guessing white.
 */
function readableOn(hex) {
    const match = /^#([0-9a-f]{6})$/i.exec(hex ?? '');

    if (!match) {
        return '#fafafa';
    }

    const int = parseInt(match[1], 16);
    const channels = [(int >> 16) & 255, (int >> 8) & 255, int & 255].map((c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });

    const luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];

    // Same threshold the WCAG ratio lands on between #0a0a0a and #fafafa.
    return luminance > 0.18 ? '#0a0a0a' : '#fafafa';
}

/**
 * WCAG contrast of the label against the button, so a mid-tone pick is called
 * out rather than silently shipped.
 *
 * Some fills cannot reach AA with any label — #ad661f peaks at 4.43:1 — so the
 * server picking the better of black/white is not a guarantee of readability,
 * only of the best available. This is where that gets said out loud.
 */
const buttonContrast = computed(() => {
    const fill = form.button_color;
    const label = readableOn(fill);

    const lum = (hex) => {
        const match = /^#([0-9a-f]{6})$/i.exec(hex ?? '');

        if (!match) {
            return 0;
        }

        const int = parseInt(match[1], 16);
        const c = [(int >> 16) & 255, (int >> 8) & 255, int & 255].map((v) => {
            const n = v / 255;
            return n <= 0.03928 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4;
        });

        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };

    const [lighter, darker] = [lum(fill), lum(label)].sort((a, b) => b - a);

    return (lighter + 0.05) / (darker + 0.05);
});

const contrastWarning = computed(() => buttonContrast.value < 4.5);

function submit() {
    form.post(route('colors.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Colours" />

    <SettingsLayout
        :heading="trans('Colours')"
        :description="trans('The button and background colours everyone sees.')"
    >
        <form class="max-w-xl space-y-8" @submit.prevent="submit">
            <!-- Button colour -->
            <div>
                <Label for="button_color">{{ __('Button colour') }}</Label>

                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <input
                        id="button_color"
                        type="color"
                        :value="form.button_color"
                        class="size-10 shrink-0 cursor-pointer rounded-lg border border-neutral-200 bg-transparent p-1 dark:border-neutral-700"
                        @input="setColor('button_color', $event.target.value)"
                    />
                    <Input
                        :model-value="form.button_color"
                        class="w-32 font-mono"
                        autocomplete="off"
                        spellcheck="false"
                        aria-label="Button colour hex"
                        :aria-invalid="!!form.errors.button_color"
                        @update:model-value="setColor('button_color', $event)"
                    />

                    <!-- Live preview: the label colour is computed from the fill,
                         so a pale pick shows dark text here exactly as it will
                         once saved. -->
                    <span
                        class="inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold"
                        :style="{
                            backgroundColor: form.button_color,
                            color: readableOn(form.button_color),
                        }"
                    >
                        {{ __('Save') }}
                    </span>
                </div>

                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Used for primary buttons in both light and dark mode. The label colour is picked automatically for contrast.') }}
                </p>

                <p
                    v-if="contrastWarning"
                    class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                >
                    {{ __('Low contrast (:ratio:1). Mid-tone colours cannot reach the 4.5:1 readability bar with any label — a darker or lighter shade will.', {
                        ratio: buttonContrast.toFixed(1),
                    }) }}
                </p>

                <p v-if="form.errors.button_color" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.button_color }}
                </p>
            </div>

            <!-- System background -->
            <div>
                <Label>{{ __('System background') }}</Label>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button
                        v-for="preset in body_presets"
                        :key="preset.value"
                        type="button"
                        :title="preset.label"
                        :aria-label="preset.label"
                        :aria-pressed="form.body_color === preset.value"
                        class="size-9 rounded-full border transition-transform hover:scale-110"
                        :class="
                            form.body_color === preset.value
                                ? 'border-neutral-900 ring-2 ring-neutral-900 ring-offset-2 dark:border-neutral-100 dark:ring-neutral-100 dark:ring-offset-neutral-900'
                                : 'border-neutral-200 dark:border-neutral-700'
                        "
                        :style="{ backgroundColor: preset.value }"
                        @click="setColor('body_color', preset.value)"
                    />

                    <span class="mx-1 h-6 w-px bg-neutral-200 dark:bg-neutral-700" aria-hidden="true" />

                    <!-- Anything outside the five presets. -->
                    <input
                        type="color"
                        :value="form.body_color"
                        aria-label="Custom background colour"
                        class="size-9 shrink-0 cursor-pointer rounded-full border border-neutral-200 bg-transparent p-1 dark:border-neutral-700"
                        @input="setColor('body_color', $event.target.value)"
                    />
                    <Input
                        :model-value="form.body_color"
                        class="w-32 font-mono"
                        autocomplete="off"
                        spellcheck="false"
                        aria-label="Background colour hex"
                        :aria-invalid="!!form.errors.body_color"
                        @update:model-value="setColor('body_color', $event)"
                    />
                </div>

                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                    {{
                        usingPreset
                            ? __('Light mode only — dark mode keeps its own background.')
                            : __('Custom colour. Light mode only — dark mode keeps its own background.')
                    }}
                </p>
                <p v-if="form.errors.body_color" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.body_color }}
                </p>
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? __('Saving…') : __('Save') }}
            </Button>
        </form>
    </SettingsLayout>
</template>
