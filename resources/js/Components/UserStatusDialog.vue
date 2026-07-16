<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { MUTED } from '@/lib/appStyles';

/**
 * Pick a status and confirm it.
 *
 * A plain yes/no ConfirmDialog cannot carry this: with four statuses there is no
 * single question to answer, and the consequence differs per choice — so the
 * chosen one's description is shown before the button, not after the click.
 */
const props = defineProps({
    // The user row, or null when closed.
    user: { type: Object, default: null },
    // From UserStatus::options() — value, label, description, can_sign_in.
    statuses: { type: Array, required: true },
});

const emit = defineEmits(['close']);

const open = computed({
    get: () => props.user !== null,
    set: (value) => {
        if (!value) {
            emit('close');
        }
    },
});

const chosen = ref(null);
const processing = ref(false);

// Reseeded per user: the sheet stays mounted, so a stale choice would carry over
// to whoever is opened next.
watch(
    () => props.user,
    (user) => {
        chosen.value = user?.status ?? null;
        processing.value = false;
    },
    { immediate: true },
);

const selected = computed(() =>
    props.statuses.find((s) => s.value === chosen.value) ?? null,
);

const unchanged = computed(() => chosen.value === props.user?.status);

// Only worth warning about when it actually takes access away that they have.
const willRevoke = computed(
    () => selected.value && !selected.value.can_sign_in && props.user?.status === 'active',
);

function submit() {
    if (unchanged.value || !chosen.value) {
        return;
    }

    processing.value = true;

    router.patch(
        route('users.status', props.user.uuid),
        { status: chosen.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ __('Change status') }}</DialogTitle>
                <DialogDescription>
                    {{ user ? __('For :name.', { name: user.name }) : '' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="user" class="space-y-2 py-4">
                <!--
                    Radio-style rows rather than a select: four options with a
                    sentence each do not fit a dropdown, and the consequence is
                    the whole point of the confirmation.
                -->
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    type="button"
                    class="flex w-full items-start gap-3 rounded-2xl border p-3 text-start transition"
                    :class="
                        chosen === status.value
                            ? 'border-neutral-900 bg-neutral-50 dark:border-neutral-100 dark:bg-neutral-800'
                            : 'border-neutral-200 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800/60'
                    "
                    :aria-pressed="chosen === status.value"
                    @click="chosen = status.value"
                >
                    <span
                        class="mt-1 grid size-4 shrink-0 place-items-center rounded-full border"
                        :class="
                            chosen === status.value
                                ? 'border-neutral-900 dark:border-neutral-100'
                                : 'border-neutral-300 dark:border-neutral-600'
                        "
                    >
                        <span
                            v-if="chosen === status.value"
                            class="size-2 rounded-full bg-neutral-900 dark:bg-neutral-100"
                        />
                    </span>

                    <span class="min-w-0">
                        <span class="flex items-center gap-2">
                            <span class="text-sm font-semibold">{{ status.label }}</span>
                            <span
                                v-if="status.value === user.status"
                                class="rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500 dark:bg-neutral-700 dark:text-neutral-300"
                            >
                                {{ __('current') }}
                            </span>
                        </span>
                        <span class="mt-0.5 block text-xs" :class="MUTED">
                            {{ status.description }}
                        </span>
                    </span>
                </button>

                <div
                    v-if="willRevoke"
                    class="flex items-start gap-2 rounded-2xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
                    <p>
                        {{ __('They will be signed out on their next click, and any API tokens are revoked.') }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="emit('close')">
                    {{ __('Cancel') }}
                </Button>
                <Button type="button" :disabled="unchanged || processing" @click="submit">
                    {{ processing ? __('Saving…') : __('Confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
