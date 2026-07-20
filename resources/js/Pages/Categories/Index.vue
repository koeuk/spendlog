<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryBadge from '@/Components/CategoryBadge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SearchInput from '@/Components/SearchInput.vue';
import FloatingAddButton from '@/Components/FloatingAddButton.vue';
import { ACTIVE, CARD } from '@/lib/appStyles';
import { localized } from '@/lib/i18n';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    categories: { type: Array, required: true },
    can: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

// The actions column only earns its space if at least one action is available —
// a normal user can add a category but not edit or delete a shared one, so they
// would otherwise get an empty column under a header.
const canManageAny = computed(() => props.can.update || props.can.delete);

// Seeded from the URL so a shared or reloaded link shows its own search term.
const search = ref(props.filters?.filter?.name ?? '');

// Mirrors the server's default. Sorting is the server's job here — the page
// holds every category at once today, but ordering the whole set is what the
// URL describes, and a client-side sort would quietly disagree the day this
// list is paginated.
const NEWEST = '-created';
const OLDEST = 'created';

const sort = ref(props.filters?.sort ?? NEWEST);

const SORTS = [
    { key: NEWEST, label: 'Newest' },
    { key: OLDEST, label: 'Oldest' },
];

/*
 * Both controls narrow the same list, so each edits the current query rather
 * than replacing it — sorting used to drop the search term, and searching used
 * to drop the sort. The default sort is left out of the URL: it is what a bare
 * /categories already means.
 */
function navigate({ name, sort: nextSort } = {}) {
    const term = name ?? search.value;
    const order = nextSort ?? sort.value;
    const query = {};

    if (term) {
        query.filter = { name: term };
    }

    if (order && order !== NEWEST) {
        query.sort = order;
    }

    router.get(route('categories.index'), query, {
        // preserveState keeps the box focused mid-typing; replace keeps every
        // keystroke out of the back button.
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(search, (value) => navigate({ name: value }));

function applySort(key) {
    sort.value = key;
    navigate({ sort: key });
}

const deleting = ref(null);
const deleteForm = useForm({});

// The category awaiting confirmation. Holding the row itself, not just a flag,
// lets the prompt name what is about to go.
const confirming = ref(null);

function confirmDestroy(category) {
    confirming.value = category;
}

function destroy() {
    const category = confirming.value;

    if (!category) {
        return;
    }

    deleting.value = category.uuid;

    deleteForm.delete(route('categories.destroy', category.uuid), {
        preserveScroll: true,
        // Closed here rather than on click: a category still in use comes back
        // 409 with a flash explaining why, and dismissing the prompt first would
        // leave the row sitting there with no visible reason.
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                    {{ __('Categories') }}
                </h2>

                <div class="flex items-center gap-2">
                    <!-- Up here rather than beside the search box. On a phone the
                         add button left this row empty when it moved to the
                         floating one, while the search row carried a full-width
                         field and a 90px toggle crammed onto one line. -->
                    <div
                        class="inline-flex h-9 shrink-0 rounded-xl border border-gray-200 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-800"
                        role="group"
                        :aria-label="__('Sort categories')"
                    >
                        <button
                            v-for="option in SORTS"
                            :key="option.key"
                            type="button"
                            class="flex items-center rounded-lg px-3.5 text-sm font-medium transition"
                            :class="
                                sort === option.key
                                    ? ACTIVE
                                    : 'text-gray-600 hover:bg-gray-50 dark:text-neutral-400 dark:hover:bg-neutral-700'
                            "
                            :aria-pressed="sort === option.key"
                            @click="applySort(option.key)"
                        >
                            {{ __(option.label) }}
                        </button>
                    </div>

                    <!-- Desktop only; the phone gets the floating button below. -->
                    <Button
                        v-if="can.create"
                        :as="Link"
                        :href="route('categories.create')"
                        size="sm"
                        class="max-sm:hidden"
                    >
                        {{ __('Add category') }}
                    </Button>
                </div>
            </div>
        </template>

        <!-- pt-2, not pt-8. The layout's header already carries pb-6, so the
             old padding stacked into a 56px band of nothing between the title
             and the first control. -->
        <div class="pb-8 pt-2">
            <!-- Width and gutters come from the layout's one container, so the
                 column never resizes when navigating between pages. -->
            <div>
                <!-- The search box has the row to itself now that sorting sits
                     in the header, so it takes the full width on a phone and
                     stays capped on a desk. -->
                <SearchInput
                    v-model="search"
                    :placeholder="__('Search categories…')"
                    class="mb-4 block w-full sm:max-w-sm"
                    input-class="bg-card"
                />

                <!--
                    The card is 28px-rounded, so a flush table crowds its corners.
                    Padding here plus roomier cells keeps the content clear of the
                    curve without the rows losing their full-width dividers.
                -->
                <div :class="[CARD, 'overflow-hidden p-2 sm:p-3']">
                    <Table class="[&_td]:px-3 [&_th]:px-3 [&_tr:last-child]:border-0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ __('Name') }}</TableHead>
                                <TableHead class="text-center">{{ __('Expenses') }}</TableHead>
                                <TableHead v-if="canManageAny" class="w-32 text-right">
                                    {{ __('Actions') }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="!categories.length">
                                <TableCell
                                    :colspan="canManageAny ? 3 : 2"
                                    class="py-10 text-center text-sm text-gray-500 dark:text-neutral-400"
                                >
                                    {{ search ? __('No categories match your search.') : __('No categories yet.') }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="category in categories" :key="category.uuid">
                                <TableCell>
                                    <CategoryBadge
                                        :name="localized(category.name)"
                                        :color="category.color"
                                        :icon="category.icon"
                                    />
                                </TableCell>
                                <TableCell class="text-center text-base font-semibold text-gray-700 dark:text-neutral-200">
                                    {{ category.expenses_count }}
                                </TableCell>
                                <TableCell v-if="canManageAny" class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            v-if="can.update"
                                            :as="Link"
                                            :href="route('categories.edit', category.uuid)"
                                            variant="secondary"
                                            size="xs"
                                            class="rounded-xl max-sm:h-8"
                                        >
                                            {{ __('Edit') }}
                                        </Button>
                                        <Button
                                            v-if="can.delete"
                                            variant="destructive"
                                            size="xs"
                                            class="rounded-xl max-sm:h-8"
                                            :disabled="deleting === category.uuid"
                                            @click="confirmDestroy(category)"
                                        >
                                            {{ __('Delete') }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="__('Delete this category?')"
            :description="
                confirming
                    ? __('&quot;:name&quot; will be removed for everyone. This cannot be undone.', {
                          name: localized(confirming.name),
                      })
                    : ''
            "
            :confirm-label="__('Delete')"
            :cancel-label="__('Cancel')"
            :processing="deleting !== null"
            :processing-label="__('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="destroy"
        />

        <FloatingAddButton
            v-if="can.create"
            :href="route('categories.create')"
            :label="__('Add category')"
        />
    </AuthenticatedLayout>
</template>
