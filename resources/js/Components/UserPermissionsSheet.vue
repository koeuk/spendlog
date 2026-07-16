<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * Per-user permissions, over the top of whatever their role already grants.
 *
 * Two sources, shown differently on purpose:
 *   - role permissions are inherited and read-only here; unticking one would
 *     really mean "change the role", which belongs in the edit dialog;
 *   - direct permissions are this user's own, and are what the checkboxes write.
 */
const props = defineProps({
    // The user row, or null when closed.
    user: { type: Object, default: null },
    // { 'Categories': [{ value, label, description }], ... } from Permission::grouped()
    groups: { type: Object, required: true },
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

const form = useForm({ permissions: [] });

// The row changes while the sheet is open (reopened on another user), so seed
// from the prop rather than once on mount.
watch(
    () => props.user,
    (user) => {
        form.permissions = user ? [...(user.direct_permissions ?? [])] : [];
        form.clearErrors();
    },
    { immediate: true },
);

const inherited = computed(() => props.user?.role_permissions ?? []);

function isInherited(value) {
    return inherited.value.includes(value);
}

function toggle(value, checked) {
    form.permissions = checked
        ? [...new Set([...form.permissions, value])]
        : form.permissions.filter((p) => p !== value);
}

// Inherited ones read as ticked but are not part of the payload: they come from
// the role, and writing them as direct grants would silently pin them in place
// even after the role changed.
function isChecked(value) {
    return isInherited(value) || form.permissions.includes(value);
}

function submit() {
    form.put(route('users.permissions', props.user.uuid), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

const dirtyCount = computed(() => form.permissions.length);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-b border-neutral-100 p-6 dark:border-neutral-800">
                <SheetTitle>{{ __('Permissions') }}</SheetTitle>
                <SheetDescription>
                    {{ user ? __('What :name can do beyond their own records.', { name: user.name }) : '' }}
                </SheetDescription>
            </SheetHeader>

            <div v-if="user" class="min-h-0 flex-1 overflow-y-auto p-6">
                <div
                    class="mb-5 flex items-start gap-2 rounded-2xl bg-neutral-50 p-3 text-xs dark:bg-neutral-800/60"
                    :class="MUTED"
                >
                    <Info class="mt-0.5 size-3.5 shrink-0" />
                    <p>
                        {{ __('Greyed items come with the :role role. To change those, change the role instead.', { role: user.role }) }}
                    </p>
                </div>

                <div v-for="(items, group) in groups" :key="group" class="mb-6 last:mb-0">
                    <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-400">
                        {{ group }}
                    </h4>

                    <div class="space-y-3">
                        <div v-for="item in items" :key="item.value" class="flex items-start gap-3">
                            <Checkbox
                                :id="`perm_${item.value}`"
                                :model-value="isChecked(item.value)"
                                :disabled="isInherited(item.value)"
                                class="mt-0.5"
                                @update:model-value="toggle(item.value, $event)"
                            />
                            <div class="min-w-0">
                                <Label
                                    :for="`perm_${item.value}`"
                                    class="cursor-pointer text-sm font-medium"
                                    :class="isInherited(item.value) ? 'text-neutral-400 dark:text-neutral-500' : ''"
                                >
                                    {{ item.label }}
                                    <span
                                        v-if="isInherited(item.value)"
                                        class="ms-1 text-[10px] font-semibold uppercase tracking-wide"
                                    >
                                        {{ __('from role') }}
                                    </span>
                                </Label>
                                <p class="text-xs" :class="MUTED">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <SheetFooter class="border-t border-neutral-100 p-6 dark:border-neutral-800">
                <div class="flex w-full items-center justify-between gap-3">
                    <span class="text-xs" :class="MUTED">
                        {{ __(':count extra granted', { count: dirtyCount }) }}
                    </span>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="emit('close')">
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="button" :disabled="form.processing" @click="submit">
                            {{ form.processing ? __('Saving…') : __('Save') }}
                        </Button>
                    </div>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
