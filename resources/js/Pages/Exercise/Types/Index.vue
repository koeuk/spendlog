<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import ExerciseBadge from '@/Components/Exercise/ExerciseBadge.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { CARD, EYEBROW, MUTED, PILL_ACTION } from '@/lib/appStyles';
import { EXERCISE_ICON_NAMES, EXERCISE_COLOR_NAMES, exerciseColor, exerciseIcon } from '@/lib/exerciseStyles';

const props = defineProps({
    types: { type: Array, default: () => [] },
    muscle_groups: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

// null = closed, 'new' = adding, an object = editing that movement.
const editing = ref(null);

const form = useForm({
    name: { en: '', km: '' },
    muscle_group: 'chest',
    is_cardio: false,
    color: null,
    icon: null,
});

function open(type = null) {
    editing.value = type ?? 'new';

    form.clearErrors();
    form.name = { en: type?.name_translations?.en ?? '', km: type?.name_translations?.km ?? '' };
    form.muscle_group = type?.muscle_group ?? 'chest';
    form.is_cardio = type?.is_cardio ?? false;
    form.color = type?.color ?? null;
    form.icon = type?.icon ?? null;
}

function close() {
    editing.value = null;
}

// The dialog wants a boolean, but `editing` has to stay the source of truth —
// it is what tells submit() whether this is a create or an update.
const dialogOpen = computed({
    get: () => editing.value !== null,
    set: (value) => {
        if (!value) {
            close();
        }
    },
});

function submit() {
    const options = { preserveScroll: true, onSuccess: close };

    if (editing.value === 'new') {
        form.post(route('exercise.types.store'), options);
    } else {
        form.put(route('exercise.types.update', editing.value.uuid), options);
    }
}

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
    <Head :title="__('Movements')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p :class="EYEBROW">{{ __('Exercise') }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        {{ __('Movements') }}
                    </h1>
                </div>

                <button
                    v-if="can.create"
                    type="button"
                    class="bg-primary text-primary-foreground inline-flex items-center gap-2 transition hover:opacity-90"
                    :class="PILL_ACTION"
                    @click="open()"
                >
                    <Plus class="size-4" />
                    {{ __('Add movement') }}
                </button>
            </div>
        </template>

        <!-- A dialog rather than a card in the flow: the list below is the
             reference you edit against, and pushing it down the page hid it. -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg max-h-[85svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing === 'new' ? __('Add movement') : __('Edit movement') }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <!-- One field per locale would grow a column per language.
                         The tabs keep it to one, and every locale stays in
                         form.name so a single submit still sends the JSON. -->
                    <LocaleTabs
                        :form="form"
                        field="name"
                        :placeholders="{ en: 'e.g. Bench Press' }"
                    >
                        <template #label>
                            <span class="text-xs font-semibold">{{ __('Name') }}</span>
                        </template>

                        <template #default="{ locale, placeholder, isRequired }">
                            <input
                                :id="`name_${locale}`"
                                v-model="form.name[locale]"
                                type="text"
                                autocomplete="off"
                                class="h-10 w-full rounded-xl border border-border bg-card/70 px-3 text-sm"
                                :placeholder="placeholder"
                                :required="isRequired"
                                :aria-invalid="!!form.errors[`name.${locale}`]"
                            />
                        </template>
                    </LocaleTabs>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- Not a <label>: the trigger is a button, which a label
                             cannot forward a click to. The span labels it instead. -->
                        <div class="block">
                            <span class="text-xs font-semibold">{{ __('Muscle group') }}</span>
                            <SearchableSelect
                                v-model="form.muscle_group"
                                :options="muscle_groups"
                                :label="__('Muscle group')"
                                :search-placeholder="__('Search muscle groups…')"
                                :empty-text="__('No muscle group found.')"
                                align="start"
                                trigger-class="mt-1 h-10 w-full rounded-xl border border-border bg-card/70 px-3 text-sm"
                                content-class="w-[--reka-popover-trigger-width]"
                            />
                        </div>

                        <label class="mt-6 flex items-center gap-2">
                            <input v-model="form.is_cardio" type="checkbox" class="rounded" />
                            <span class="text-xs font-semibold">
                                {{ __('Logged as distance and time') }}
                            </span>
                        </label>
                    </div>

                    <!-- Colour and icon are optional: leaving them blank takes
                         the muscle group's colour, which is what keeps the
                         dashboard breakdown readable by group. -->
                    <div>
                        <span class="text-xs font-semibold">{{ __('Colour') }}</span>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button
                                v-for="name in EXERCISE_COLOR_NAMES"
                                :key="name"
                                type="button"
                                class="size-7 rounded-full ring-2 ring-offset-2 ring-offset-background transition"
                                :class="[
                                    exerciseColor(name).dot,
                                    form.color === name ? 'ring-primary' : 'ring-transparent',
                                ]"
                                :aria-label="name"
                                @click="form.color = form.color === name ? null : name"
                            />
                        </div>
                    </div>

                    <div>
                        <span class="text-xs font-semibold">{{ __('Icon') }}</span>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button
                                v-for="name in EXERCISE_ICON_NAMES"
                                :key="name"
                                type="button"
                                class="grid size-9 place-items-center rounded-xl border transition"
                                :class="
                                    form.icon === name
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border hover:bg-muted'
                                "
                                :aria-label="name"
                                @click="form.icon = form.icon === name ? null : name"
                            >
                                <component :is="exerciseIcon(name)" class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-full border border-border px-4 text-sm font-semibold transition hover:bg-muted"
                            :class="PILL_ACTION"
                            @click="close"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="bg-primary text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                            :class="PILL_ACTION"
                            :disabled="form.processing"
                        >
                            {{ __('Save') }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

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
                                {{ __('Mine') }}
                            </span>

                            <button
                                v-if="type.can_update"
                                type="button"
                                class="grid size-8 place-items-center rounded-full text-neutral-400 transition hover:bg-muted hover:text-foreground"
                                :aria-label="__('Edit')"
                                @click="open(type)"
                            >
                                <Pencil class="size-3.5" />
                            </button>

                            <button
                                v-if="type.can_delete"
                                type="button"
                                class="grid size-8 place-items-center rounded-full text-neutral-400 transition hover:bg-red-500/10 hover:text-red-600"
                                :aria-label="__('Delete')"
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
            :title="__('Delete this movement?')"
            :description="
                confirming
                    ? __('&quot;:name&quot; will be removed. Sets already logged against it are not affected.', {
                          name: confirming.name,
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
    </AuthenticatedLayout>
</template>
