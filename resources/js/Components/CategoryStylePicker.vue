<script setup>
import { Label } from '@/Components/ui/label';
import {
    CATEGORY_COLORS,
    COLOR_NAMES,
    ICON_NAMES,
    iconComponent,
} from '@/lib/categoryStyles';

const props = defineProps({
    form: { type: Object, required: true },
});
</script>

<template>
    <div class="grid gap-4">
        <div>
            <Label>Colour</Label>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="color in COLOR_NAMES"
                    :key="color"
                    type="button"
                    :title="color"
                    :aria-label="color"
                    :aria-pressed="form.color === color"
                    class="size-7 rounded-full ring-offset-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="[
                        CATEGORY_COLORS[color].dot,
                        form.color === color
                            ? 'ring-2 ring-gray-900'
                            : 'hover:scale-110',
                    ]"
                    @click="form.color = color"
                />
            </div>
            <p v-if="form.errors.color" class="mt-1 text-sm text-red-600">
                {{ form.errors.color }}
            </p>
        </div>

        <div>
            <Label>Icon</Label>
            <div class="mt-2 grid grid-cols-8 gap-1.5">
                <button
                    v-for="icon in ICON_NAMES"
                    :key="icon"
                    type="button"
                    :title="icon"
                    :aria-label="icon"
                    :aria-pressed="form.icon === icon"
                    class="flex size-8 items-center justify-center rounded-md border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        form.icon === icon
                            ? 'border-gray-900 bg-gray-900 text-white'
                            : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                    "
                    @click="form.icon = form.icon === icon ? null : icon"
                >
                    <component :is="iconComponent(icon)" class="size-4" />
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                Optional — click again to clear.
            </p>
            <p v-if="form.errors.icon" class="mt-1 text-sm text-red-600">
                {{ form.errors.icon }}
            </p>
        </div>
    </div>
</template>
