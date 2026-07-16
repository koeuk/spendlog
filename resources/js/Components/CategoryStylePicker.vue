<script setup>
import { Label } from '@/Components/ui/label';
import {
    CATEGORY_COLOR_NAMES,
    CATEGORY_ICON_NAMES,
    categoryColor,
    categoryIcon,
} from '@/lib/categoryStyles';

const props = defineProps({
    form: { type: Object, required: true },
});

// Clicking the selected icon clears it — icon is optional.
function toggleIcon(name) {
    props.form.icon = props.form.icon === name ? null : name;
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <Label>Colour</Label>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="name in CATEGORY_COLOR_NAMES"
                    :key="name"
                    type="button"
                    class="size-7 rounded-full ring-offset-2 ring-offset-background transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="[
                        categoryColor(name).dot,
                        form.color === name
                            ? 'ring-2 ring-foreground'
                            : 'ring-1 ring-border hover:ring-foreground/40',
                    ]"
                    :aria-label="name"
                    :aria-pressed="form.color === name"
                    @click="form.color = name"
                />
            </div>
            <p v-if="form.errors.color" class="mt-2 text-sm text-red-600">
                {{ form.errors.color }}
            </p>
        </div>

        <div>
            <Label>
                Icon
                <span class="font-normal text-muted-foreground">(optional)</span>
            </Label>
            <div class="mt-2 grid grid-cols-8 gap-1.5">
                <button
                    v-for="name in CATEGORY_ICON_NAMES"
                    :key="name"
                    type="button"
                    class="flex aspect-square items-center justify-center rounded-md border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    :class="
                        form.icon === name
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border text-muted-foreground hover:border-foreground/40 hover:bg-accent hover:text-accent-foreground'
                    "
                    :aria-label="name"
                    :aria-pressed="form.icon === name"
                    @click="toggleIcon(name)"
                >
                    <component :is="categoryIcon(name)" class="size-4" />
                </button>
            </div>
            <p v-if="form.errors.icon" class="mt-2 text-sm text-red-600">
                {{ form.errors.icon }}
            </p>
        </div>
    </div>
</template>
