<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from '@/lib/i18n';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import ExerciseBadge from '@/Components/Exercise/ExerciseBadge.vue';
import { CARD, EYEBROW, MUTED, PILL_ACTION } from '@/lib/appStyles';
import { exerciseColor, exerciseIcon } from '@/lib/exerciseStyles';

const props = defineProps({
    types: { type: Array, default: () => [] },
    muscle_groups: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const deleteForm = useForm({});

// The movement awaiting confirmation. Holding the row itself, not just a flag,
// lets the prompt name what is about to go.
const confirming = ref(null);
const deleting = ref(null);

function confirmDestroy(type) {
    confirming.value = type;
}

function destroy() {
    const type = confirming.value;

    if (!type) {
        return;
    }

    deleting.value = type.uuid;

    deleteForm.delete(route('exercise.types.destroy', type.uuid), {
        preserveScroll: true,
        // Closed on success, not on click: a failed delete should leave the
        // prompt up rather than vanish with the row still there.
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = null;
        },
    });
}

/** Grouped by muscle, so the list reads as a catalogue rather than a wall. */
const sections = computed(() => {
    const byGroup = new Map(props.muscle_groups.map((g) => [g.value, { ...g, types: [] }]));

    for (const type of props.types) {
        byGroup.get(type.muscle_group)?.types.push(type);
    }

    return [...byGroup.values()].filter((section) => section.types.length > 0);
});
</script>

<template>
    <Head :title="trans('Movements')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ trans('Exercise') }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ trans('Movements') }}
                    </h1>
                </div>

                <Link
                    v-if="can.create"
                    :href="route('exercise.types.create')"
                    class="bg-primary text-primary-foreground inline-flex items-center gap-2 transition hover:opacity-90"
                    :class="PILL_ACTION"
                >
                    <Plus class="size-4" />
                    {{ trans('Add movement') }}
                </Link>
            </div>
        </template>

        <!-- A dialog rather than a card in the flow: the list below is the
             reference you edit against, and pushing it down the page hid it. -->
        <div class="space-y-3">
            <section
                v-for="(section, index) in sections"
                :key="section.value"
                :class="[CARD, 'anim p-6']"
                :style="{ '--d': `${60 + index * 40}ms` }"
            >
                <p :class="EYEBROW">{{ section.label }}</p>

                <ul class="mt-4 divide-y divide-border">
                    <li
                        v-for="type in section.types"
                        :key="type.uuid"
                        class="flex items-center justify-between gap-3 py-2.5"
                    >
                        <ExerciseBadge
                            :name="type.name"
                            :color="type.color"
                            :icon="type.icon"
                            class="min-w-0 text-sm"
                        />

                        <div class="flex shrink-0 items-center gap-2">
                            <!-- Marks a movement the person invented, as opposed
                                 to one from the shared catalogue. -->
                            <span
                                v-if="type.is_mine"
                                class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                :class="MUTED"
                            >
                                {{ trans('Mine') }}
                            </span>

                            <Link
                                v-if="type.can_update"
                                :href="route('exercise.types.edit', type.uuid)"
                                class="grid size-10 place-items-center rounded-full text-neutral-400 transition hover:bg-muted hover:text-foreground"
                                :aria-label="trans('Edit')"
                            >
                                <Pencil class="size-3.5" />
                            </Link>

                            <button
                                v-if="type.can_delete"
                                type="button"
                                class="grid size-10 place-items-center rounded-full text-neutral-400 transition hover:bg-red-500/10 hover:text-red-600"
                                :aria-label="trans('Delete')"
                                @click="confirmDestroy(type)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="trans('Delete this movement?')"
            :description="
                confirming
                    ? trans('&quot;:name&quot; will be removed. Sets already logged against it are not affected.', {
                          name: confirming.name,
                      })
                    : ''
            "
            :confirm-label="trans('Delete')"
            :cancel-label="trans('Cancel')"
            :processing="deleting !== null"
            :processing-label="trans('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="destroy"
        />
    </AuthenticatedLayout>
</template>
