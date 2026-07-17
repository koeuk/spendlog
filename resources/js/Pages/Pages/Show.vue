<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD } from '@/lib/appStyles';

defineProps({
    // { title, body } — already resolved to the reader's locale.
    page: { type: Object, required: true },
});
</script>

<template>
    <Head :title="page.title" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-neutral-100">
                {{ page.title }}
            </h1>
        </template>

        <div class="mx-auto max-w-2xl py-8">
            <!-- whitespace-pre-line keeps the paragraphs an admin typed, without
                 rendering any HTML — the body is plain text, never markup. -->
            <div
                :class="[CARD, 'whitespace-pre-line p-6 text-sm leading-relaxed text-gray-700 dark:text-neutral-300']"
            >
                {{ page.body }}
            </div>
        </div>
    </AuthenticatedLayout>
</template>
