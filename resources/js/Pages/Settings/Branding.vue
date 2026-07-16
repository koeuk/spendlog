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
});

const form = useForm({
    app_name: props.branding.app_name,
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
});

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

                <p class="mt-2 text-xs text-gray-500 dark:text-neutral-400">
                    {{ __('Shown in the browser tab. PNG, ICO, JPG or WebP, up to 1 MB. Square works best.') }}
                </p>
                <p v-if="form.errors.favicon" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.favicon }}
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
