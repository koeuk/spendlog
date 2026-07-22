<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { FileText, Info, ShieldCheck } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD_LIFT } from '@/lib/appStyles';

const props = defineProps({
    // { slug, title, body } — title and body already resolved to the reader's
    // locale.
    page: { type: Object, required: true },
});

// One icon per fixed slug; FileText for anything a future migration adds
// before this map learns about it.
const icon = computed(
    () =>
        ({
            about: Info,
            privacy: ShieldCheck,
        })[props.page.slug] ?? FileText,
);
</script>

<template>
    <Head :title="page.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <!-- Big on purpose: the page is a wall of plain text, and this
                     is the one visual anchor saying which document you are on. -->
                <span
                    class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <component :is="icon" class="size-7" />
                </span>
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-neutral-100">
                    {{ page.title }}
                </h1>
            </div>
        </template>

        <div class="py-8">
            <!-- CARD's surface with a rounded-xl corner instead of its 28px
                 one: composed by hand because appending a second rounded-*
                 utility would leave the winner to stylesheet order. -->
            <!-- whitespace-pre-line keeps the paragraphs an admin typed, without
                 rendering any HTML — the body is plain text, never markup. -->
            <div
                :class="[
                    CARD_LIFT,
                    'whitespace-pre-line rounded-xl border border-border bg-card/70 p-6 text-sm leading-relaxed text-gray-700 backdrop-blur-xl backdrop-saturate-150 dark:text-neutral-300',
                ]"
            >
                {{ page.body }}
            </div>
        </div>
    </AuthenticatedLayout>
</template>
