<script setup>
import { Head } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import PageEditorCard from '@/Components/PageEditorCard.vue';
import { trans } from '@/lib/i18n';

defineProps({
    // [{ slug, name, title: {en, km}, body: {en, km}, published }]
    pages: { type: Array, required: true },
});
</script>

<template>
    <Head title="Pages" />

    <!-- flush: the panel holds a card per page, so on a phone its own card
         would be a border a few pixels outside theirs. -->
    <SettingsLayout
        flush
        :heading="trans('Footer pages')"
        :description="trans('The About and Privacy pages linked at the bottom of every page.')"
    >
        <div class="space-y-6">
            <PageEditorCard v-for="page in pages" :key="page.slug" :page="page" />
        </div>
    </SettingsLayout>
</template>
