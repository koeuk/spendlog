<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const locales = computed(() => page.props.locales ?? []);
const current = computed(() => page.props.locale);

function switchTo(locale) {
    if (locale === current.value) {
        return;
    }

    // preserveScroll only — the page must re-render with the new dictionary.
    router.post(route('locale.update'), { locale }, { preserveScroll: true });
}
</script>

<template>
    <div class="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
        <button
            v-for="locale in locales"
            :key="locale.value"
            type="button"
            class="rounded px-2 py-1 text-xs font-medium transition"
            :class="
                locale.value === current
                    ? 'bg-gray-900 text-white'
                    : 'text-gray-600 hover:bg-gray-50'
            "
            :aria-pressed="locale.value === current"
            :title="locale.label"
            @click="switchTo(locale.value)"
        >
            {{ locale.short }}
        </button>
    </div>
</template>
