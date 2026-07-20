<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Button } from '@/Components/ui/button';
import { CARD, MUTED, SETTINGS_ACTION } from '@/lib/appStyles';
import { trans, localized } from '@/lib/i18n';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    // [{ uuid, question: {en, km}, answer: {en, km}, status }]
    faqs: { type: Array, required: true },
    // [{ value, label }]
    statuses: { type: Array, required: true },
});

const confirming = ref(null);
const deleteForm = useForm({});

function confirmDelete(faq) {
    confirming.value = faq;
}

function destroy() {
    if (!confirming.value) {
        return;
    }

    deleteForm.delete(route('faqs.destroy', confirming.value.uuid), {
        preserveScroll: true,
        onFinish: () => {
            confirming.value = null;
        },
    });
}

/*
 * Reorder by swapping a row with its neighbour and posting the whole order.
 * Up/down rather than drag: it needs no library, works with a keyboard, and the
 * server rewrites every position from the list so the two can never disagree.
 */
function move(index, delta) {
    const target = index + delta;

    if (target < 0 || target >= props.faqs.length) {
        return;
    }

    const order = props.faqs.map((f) => f.uuid);
    [order[index], order[target]] = [order[target], order[index]];

    router.post(
        route('faqs.reorder'),
        { uuids: order },
        { preserveScroll: true, preserveState: false },
    );
}

const statusClass = (status) =>
    status === 'published'
        ? 'bg-green-500/15 text-green-700 dark:text-green-300'
        : 'bg-neutral-500/15 text-neutral-600 dark:text-neutral-400';

const statusLabel = (value) => props.statuses.find((s) => s.value === value)?.label ?? value;
</script>

<template>
    <Head title="FAQ" />

    <!-- flush: the panel holds a card per question — see Pages.vue. -->
    <SettingsLayout
        flush
        :heading="trans('Help / FAQ')"
        :description="trans('Questions and answers shown to everyone on the help page.')"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-xs" :class="MUTED">
                    {{ trans('Drafts stay hidden until you publish them.') }}
                </p>
                <Button :as="Link" :href="route('faqs.create')" size="sm" :class="SETTINGS_ACTION">
                    <Plus class="mr-1 size-4" />
                    {{ __('Add entry') }}
                </Button>
            </div>

            <p v-if="faqs.length === 0" :class="[CARD, 'p-6 text-center text-sm']" class="text-muted-foreground">
                {{ __('No entries yet. Add your first question.') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="(faq, index) in faqs"
                    :key="faq.uuid"
                    :class="[CARD, 'flex items-start gap-3 p-4']"
                >
                    <!-- Reorder controls

                         Real size rather than an expanded hit area: these two are
                         stacked with nothing between them, so 44px boxes would
                         overlap and a tap near the seam would land on whichever
                         came second in the DOM — pressing "up" and moving the row
                         down. 36px each, genuinely separated, is the honest
                         version of the same fix. -->
                    <div class="flex flex-col gap-0.5">
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded text-neutral-400 transition hover:text-neutral-900 disabled:opacity-30 dark:hover:text-neutral-100"
                            :disabled="index === 0"
                            :aria-label="__('Move up')"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded text-neutral-400 transition hover:text-neutral-900 disabled:opacity-30 dark:hover:text-neutral-100"
                            :disabled="index === faqs.length - 1"
                            :aria-label="__('Move down')"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </button>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                :class="statusClass(faq.status)"
                            >
                                {{ statusLabel(faq.status) }}
                            </span>
                        </div>
                        <p class="mt-1.5 truncate text-sm font-semibold text-gray-900 dark:text-neutral-100">
                            {{ localized(faq.question) }}
                        </p>
                        <p class="mt-0.5 line-clamp-2 text-xs" :class="MUTED">
                            {{ localized(faq.answer) }}
                        </p>
                    </div>

                    <!-- Edit sits next to Delete, so both get real size and a real
                         gap rather than overlapping invisible hit areas — the
                         neighbour here is destructive. -->
                    <div class="flex shrink-0 gap-1.5">
                        <Link
                            :href="route('faqs.edit', faq.uuid)"
                            class="grid size-10 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                            :aria-label="__('Edit')"
                        >
                            <Pencil class="size-4" />
                        </Link>
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-full text-red-600/80 transition hover:bg-red-500/10 hover:text-red-700 dark:text-red-400/80 dark:hover:text-red-300"
                            :aria-label="__('Delete')"
                            @click="confirmDelete(faq)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="trans('Delete this entry?')"
            :description="trans('This removes the question from the help page.')"
            :confirm-label="trans('Delete')"
            :cancel-label="trans('Cancel')"
            :processing="deleteForm.processing"
            :processing-label="trans('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="destroy"
        />
    </SettingsLayout>
</template>
