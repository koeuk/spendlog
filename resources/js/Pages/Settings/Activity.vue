<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Flag, FolderOpen, HandCoins, History, PiggyBank, Receipt, Repeat } from 'lucide-vue-next';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { CARD, MUTED, SEGMENT, SEGMENT_OFF, SEGMENT_ON } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    // [{ uuid, action, subject, label, changes: [{field, from, to}], user, when, when_human }]
    entries: { type: Array, required: true },
    pagination: { type: Object, required: true },
    // 'mine' | 'all'
    scope: { type: String, required: true },
    can: { type: Object, required: true },
});

const everyone = computed(() => props.scope === 'all');

const ICONS = {
    expense: Receipt,
    income: HandCoins,
    budget: PiggyBank,
    category: FolderOpen,
    savings_goal: Flag,
    savings_entry: Repeat,
};

const iconFor = (subject) => ICONS[subject] ?? History;

// Green for what was added, amber for what changed, red for what went.
const tintFor = (action) =>
    ({
        created: 'bg-green-500/15 text-green-700 dark:text-green-300',
        updated: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        deleted: 'bg-red-500/15 text-red-700 dark:text-red-300',
    })[action] ?? 'bg-neutral-500/15 text-neutral-600 dark:text-neutral-400';

const actionLabel = (action) => trans(action.charAt(0).toUpperCase() + action.slice(1));
const subjectLabel = (subject) => trans(subject.replace('_', ' '));

const absolute = (iso) => (iso ? new Date(iso).toLocaleString() : '');
</script>

<template>
    <Head :title="trans('Activity')" />

    <!-- flush: the panel holds a card per line — see Faqs.vue. -->
    <SettingsLayout
        flush
        :heading="trans('Activity log')"
        :description="trans('What you have created, changed and removed, newest first.')"
    >
        <template v-if="can.all" #actions>
            <!-- Two links, not a form: the scope is a query string so a page
                 can be shared and the back button works. -->
            <div :class="SEGMENT" role="group">
                <Link
                    :href="route('activity.index')"
                    class="px-3 py-1.5 text-xs font-semibold transition"
                    :class="everyone ? SEGMENT_OFF : SEGMENT_ON"
                    :aria-pressed="!everyone"
                >
                    {{ __('Mine') }}
                </Link>
                <Link
                    :href="route('activity.index', { scope: 'all' })"
                    class="px-3 py-1.5 text-xs font-semibold transition"
                    :class="everyone ? SEGMENT_ON : SEGMENT_OFF"
                    :aria-pressed="everyone"
                >
                    {{ __('Everyone') }}
                </Link>
            </div>
        </template>

        <div class="space-y-4">
            <p v-if="entries.length === 0" :class="[CARD, 'p-6 text-center text-sm']" class="text-muted-foreground">
                {{ __('Nothing logged yet.') }}
            </p>

            <ul v-else class="space-y-2">
                <li v-for="entry in entries" :key="entry.uuid" :class="[CARD, 'p-4']">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl"
                            :class="tintFor(entry.action)"
                            aria-hidden="true"
                        >
                            <component :is="iconFor(entry.subject)" class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-neutral-100">
                                {{ entry.label }}
                            </p>
                            <p class="mt-0.5 text-xs" :class="MUTED">
                                {{ actionLabel(entry.action) }} {{ subjectLabel(entry.subject) }}
                                <template v-if="everyone && entry.user"> · {{ trans('by :name', { name: entry.user }) }}</template>
                                · <time :datetime="entry.when" :title="absolute(entry.when)">{{ entry.when_human }}</time>
                            </p>

                            <!-- What an update changed, one line per field. -->
                            <ul v-if="entry.changes.length" class="mt-2 space-y-0.5 text-xs" :class="MUTED">
                                <li v-for="change in entry.changes" :key="change.field" class="flex flex-wrap items-center gap-x-1.5">
                                    <span class="capitalize">{{ change.field }}:</span>
                                    <span class="line-through">{{ change.from ?? '—' }}</span>
                                    <ArrowRight class="size-3" aria-hidden="true" />
                                    <span class="font-semibold text-foreground">{{ change.to ?? '—' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>

            <Pagination :meta="pagination" :only="['entries', 'pagination']" />
        </div>
    </SettingsLayout>
</template>
