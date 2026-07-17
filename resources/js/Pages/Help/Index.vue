<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD, EYEBROW, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import { ChevronDown } from 'lucide-vue-next';

defineProps({
    // [{ uuid, question, answer }] — already resolved to the reader's locale.
    faqs: { type: Array, required: true },
});

// Which entries are expanded, by uuid. Several can be open at once — this is a
// reference, not a wizard.
const open = ref(new Set());

function toggle(uuid) {
    // A new Set each time so the template re-renders on change.
    const next = new Set(open.value);
    next.has(uuid) ? next.delete(uuid) : next.add(uuid);
    open.value = next;
}
</script>

<template>
    <Head :title="trans('Help')" />

    <AuthenticatedLayout>
        <template #header>
            <p :class="EYEBROW">{{ __('Support') }}</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-neutral-100">
                {{ __('Help') }}
            </h1>
        </template>

        <div class="mx-auto max-w-2xl py-8">
            <p v-if="faqs.length === 0" :class="[CARD, 'p-8 text-center text-sm']" class="text-muted-foreground">
                {{ __('Nothing here yet. Check back soon.') }}
            </p>

            <ul v-else class="space-y-2">
                <li v-for="faq in faqs" :key="faq.uuid" :class="[CARD, 'overflow-hidden']">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-start"
                        :aria-expanded="open.has(faq.uuid)"
                        @click="toggle(faq.uuid)"
                    >
                        <span class="text-sm font-semibold text-gray-900 dark:text-neutral-100">
                            {{ faq.question }}
                        </span>
                        <ChevronDown
                            class="size-5 shrink-0 text-neutral-400 transition-transform duration-200"
                            :class="open.has(faq.uuid) ? 'rotate-180' : ''"
                        />
                    </button>
                    <!-- whitespace-pre-line keeps the line breaks an admin typed
                         into the answer, without allowing any HTML. -->
                    <p
                        v-if="open.has(faq.uuid)"
                        class="whitespace-pre-line px-5 pb-5 text-sm leading-relaxed"
                        :class="MUTED"
                    >
                        {{ faq.answer }}
                    </p>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
