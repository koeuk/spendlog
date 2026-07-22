<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CARD_LIFT, EYEBROW, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import { ChevronDown, CircleHelp } from 'lucide-vue-next';

// CARD's surface with a rounded-xl corner instead of its 28px one — composed
// by hand because appending a second rounded-* utility would leave the winner
// to stylesheet order. Same recipe as Pages/Show.vue.
const FLAT_CARD = `${CARD_LIFT} rounded-xl border border-border bg-card/70 backdrop-blur-xl backdrop-saturate-150`;

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
            <div class="flex items-center gap-4">
                <!-- Big on purpose: the one visual anchor for the page, same
                     size and tint as the About / Policy pages'. -->
                <span
                    class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary"
                    aria-hidden="true"
                >
                    <CircleHelp class="size-7" />
                </span>
                <div>
                    <p :class="EYEBROW">{{ __('Support') }}</p>
                    <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-neutral-100">
                        {{ __('Help') }}
                    </h1>
                </div>
            </div>
        </template>

        <div class="py-8">
            <p v-if="faqs.length === 0" :class="[FLAT_CARD, 'p-8 text-center text-sm']" class="text-muted-foreground">
                {{ __('Nothing here yet. Check back soon.') }}
            </p>

            <ul v-else class="space-y-2">
                <li v-for="faq in faqs" :key="faq.uuid" :class="[FLAT_CARD, 'overflow-hidden']">
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
