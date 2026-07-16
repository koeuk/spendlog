<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ImageUp, Trash2 } from 'lucide-vue-next';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { trans } from '@/lib/i18n';

const props = defineProps({
    branding: { type: Object, required: true },
    // From App\Enums\BodyColor — kept server-side so the swatches, the enum and
    // the migration default cannot drift apart.
    body_presets: { type: Array, required: true },
});

const form = useForm({
    app_name: props.branding.app_name,
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
    button_color: props.branding.button_color,
    body_color: props.branding.body_color,
});

/**
 * <input type="color"> always reports lower case, but a pasted value may not.
 * Normalising on the way in keeps the preset comparison a plain string match
 * rather than a case-insensitive one at every call site.
 */
function setColor(field, value) {
    form[field] = String(value ?? '').trim().toLowerCase();
}

const isPreset = (value) => props.body_presets.some((preset) => preset.value === form.body_color);

// Mirrors App\Support\Color::readableForegroundTriplet so the preview shows the
// label colour the server will actually compute, rather than guessing white.
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

    const luminance =
        0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];

    // Same threshold the WCAG ratio lands on between #0a0a0a and #fafafa.
    return luminance > 0.18 ? '#0a0a0a' : '#fafafa';
}

// Local object URLs so the admin sees the new image before saving.
const logoPreview = ref(props.branding.logo);
const faviconPreview = ref(props.branding.favicon);

const logoInput = ref(null);
const faviconInput = ref(null);

function pick(field, event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    form[field] = file;
    form[`remove_${field}`] = false;

    const url = URL.createObjectURL(file);

    if (field === 'logo') {
        logoPreview.value = url;
    } else {
        faviconPreview.value = url;
    }
}

function clear(field) {
    form[field] = null;
    form[`remove_${field}`] = true;

    if (field === 'logo') {
        logoPreview.value = null;
        if (logoInput.value) logoInput.value.value = '';
    } else {
        faviconPreview.value = null;
        if (faviconInput.value) faviconInput.value.value = '';
    }
}

function submit() {
    // forceFormData: file uploads cannot go as JSON.
    form.post(route('branding.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'favicon', 'remove_logo', 'remove_favicon');
            if (logoInput.value) logoInput.value.value = '';
            if (faviconInput.value) faviconInput.value.value = '';
        },
    });
}
</script>

<template>
    <Head title="Appearance" />

    <SettingsLayout
        :heading="trans('Appearance')"
        :description="trans('The name and logo shown across the app.')"
    >
        <form class="max-w-xl space-y-6" @submit.prevent="submit">
            <div>
                <Label for="app_name">{{ __('App name') }}</Label>
                <Input
                    id="app_name"
                    v-model="form.app_name"
                    class="mt-1"
                    autocomplete="off"
                    :aria-invalid="!!form.errors.app_name"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">
                    {{ __('Shown in the nav bar and the browser tab.') }}
                </p>
                <p v-if="form.errors.app_name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.app_name }}
                </p>
            </div>

            <!-- Logo -->
            <div>
                <Label>{{ __('Logo') }}</Label>
                <div class="mt-2 flex items-center gap-4">
                    <div
                        class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <img
                            v-if="logoPreview"
                            :src="logoPreview"
                            alt=""
                            class="size-full object-contain"
                        />
                        <span v-else class="text-lg font-extrabold text-gray-400 dark:text-neutral-500">
                            {{ (form.app_name || 'S').charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" @click="logoInput?.click()">
                            <ImageUp class="size-4" />
                            {{ __('Choose file') }}
                        </Button>
                        <Button
                            v-if="logoPreview"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                            @click="clear('logo')"
                        >
                            <Trash2 class="size-4" />
                            {{ __('Remove') }}
                        </Button>
                    </div>
                </div>

                <input
                    ref="logoInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="sr-only"
                    @change="pick('logo', $event)"
                />

                <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">
                    {{ __('PNG, JPG or WebP, up to 2 MB. Any size — it is scaled to fit.') }}
                </p>
                <p v-if="form.errors.logo" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.logo }}
                </p>
            </div>

            <!-- Favicon -->
            <div>
                <Label>{{ __('Favicon') }}</Label>
                <div class="mt-2 flex items-center gap-4">
                    <div
                        class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <img
                            v-if="faviconPreview"
                            :src="faviconPreview"
                            alt=""
                            class="size-full object-contain"
                        />
                        <span v-else class="text-xs font-extrabold text-gray-400 dark:text-neutral-500">
                            {{ (form.app_name || 'S').charAt(0).toUpperCase() }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" @click="faviconInput?.click()">
                            <ImageUp class="size-4" />
                            {{ __('Choose file') }}
                        </Button>
                        <Button
                            v-if="faviconPreview"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                            @click="clear('favicon')"
                        >
                            <Trash2 class="size-4" />
                            {{ __('Remove') }}
                        </Button>
                    </div>
                </div>

                <input
                    ref="faviconInput"
                    type="file"
                    accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/webp,image/jpeg"
                    class="sr-only"
                    @change="pick('favicon', $event)"
                />

                <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">
                    {{ __('Shown in the browser tab. PNG, ICO, JPG or WebP, up to 1 MB. Square works best.') }}
                </p>
                <p v-if="form.errors.favicon" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.favicon }}
                </p>
            </div>

            <!-- Button colour -->
            <div>
                <Label for="button_color">{{ __('Button colour') }}</Label>

                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <input
                        id="button_color"
                        type="color"
                        :value="form.button_color"
                        class="size-10 shrink-0 cursor-pointer rounded-lg border border-gray-200 bg-transparent p-1 dark:border-neutral-700"
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

                <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">
                    {{ __('Used for primary buttons in both light and dark mode. The label colour is picked automatically for contrast.') }}
                </p>
                <p v-if="form.errors.button_color" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.button_color }}
                </p>
            </div>

            <!-- Body colour -->
            <div>
                <Label>{{ __('Body colour') }}</Label>

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
                                : 'border-gray-200 dark:border-neutral-700'
                        "
                        :style="{ backgroundColor: preset.value }"
                        @click="setColor('body_color', preset.value)"
                    />

                    <span class="mx-1 h-6 w-px bg-gray-200 dark:bg-neutral-700" aria-hidden="true" />

                    <!-- Anything outside the five presets. -->
                    <input
                        type="color"
                        :value="form.body_color"
                        aria-label="Custom body colour"
                        class="size-9 shrink-0 cursor-pointer rounded-full border border-gray-200 bg-transparent p-1 dark:border-neutral-700"
                        @input="setColor('body_color', $event.target.value)"
                    />
                    <Input
                        :model-value="form.body_color"
                        class="w-32 font-mono"
                        autocomplete="off"
                        spellcheck="false"
                        aria-label="Body colour hex"
                        :aria-invalid="!!form.errors.body_color"
                        @update:model-value="setColor('body_color', $event)"
                    />
                </div>

                <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">
                    {{
                        isPreset()
                            ? __('Light mode only — dark mode keeps its own background.')
                            : __('Custom colour. Light mode only — dark mode keeps its own background.')
                    }}
                </p>
                <p v-if="form.errors.body_color" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.body_color }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? __('Saving…') : __('Save') }}
                </Button>
                <span v-if="form.progress" class="text-xs text-gray-500 dark:text-neutral-400">
                    {{ form.progress.percentage }}%
                </span>
            </div>
        </form>
    </SettingsLayout>
</template>
