<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * A tab per locale over one translatable field.
 *
 * Only the active locale's input is mounted, so the tabs are not just a visual
 * device — but every locale still lives in the same form object, so switching
 * tabs never loses what was typed, and one submit sends them all.
 */
const props = defineProps({
    // The useForm object. Mutated directly, matching CategoryStylePicker.
    form: { type: Object, required: true },
    // The translatable field on the form, e.g. 'item' or 'name'.
    field: { type: String, required: true },
    // [{ value: 'en', short: 'EN', label: 'English' }, ...]
    locales: { type: Array, default: null },
    placeholders: { type: Object, default: () => ({}) },
    // Which locale must be filled — the others are optional.
    required: { type: String, default: 'en' },
});

const page = usePage();

// Falls back to the shared list so a caller does not have to pass it.
const tabs = computed(() => props.locales ?? page.props.locales ?? []);

const active = ref(props.required);

/**
 * Errors arrive keyed by path — 'item.km', not nested. A tab showing an error
 * the user cannot see is a dead end, so the dot marks which tab to open.
 */
function errorFor(locale) {
    return props.form.errors?.[`${props.field}.${locale}`];
}

const anyError = computed(() => tabs.value.some((t) => errorFor(t.value)));

// Jump to the first failing tab rather than leaving the user hunting for it.
function focusFirstError() {
    const failing = tabs.value.find((t) => errorFor(t.value));

    if (failing) {
        active.value = failing.value;
    }
}

defineExpose({ focusFirstError });
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-2">
            <slot name="label" />

            <div
                class="inline-flex rounded-full border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-800"
                role="tablist"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.value"
                    :title="tab.label"
                    class="relative rounded-full px-2.5 py-1 text-xs font-semibold transition"
                    :class="
                        active === tab.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-neutral-500 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                    "
                    @click="active = tab.value"
                >
                    {{ tab.short }}

                    <!-- Marks a tab whose field failed validation, so an error on
                         a hidden locale is still discoverable. -->
                    <span
                        v-if="errorFor(tab.value)"
                        class="absolute -right-0.5 -top-0.5 size-1.5 rounded-full bg-red-500"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>

        <div class="mt-1">
            <template v-for="tab in tabs" :key="tab.value">
                <div v-show="active === tab.value">
                    <slot
                        :locale="tab.value"
                        :label="tab.label"
                        :placeholder="placeholders[tab.value] ?? ''"
                        :is-required="tab.value === required"
                        :error="errorFor(tab.value)"
                    />
                    <p v-if="errorFor(tab.value)" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ errorFor(tab.value) }}
                    </p>
                </div>
            </template>
        </div>

        <p v-if="anyError && !errorFor(active)" class="mt-1 text-xs text-red-600 dark:text-red-400">
            <button type="button" class="underline underline-offset-2" @click="focusFirstError">
                {{ __('Another language needs attention.') }}
            </button>
        </p>
    </div>
</template>
