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
 * This user's permissions. Every box is live.
 *
 * The role only decided the starting set when the account was created — it grants
 * nothing at run time, so unticking here genuinely takes the permission away.
 * (If the role still granted it, hasPermissionTo() would keep returning true and
 * the checkbox would be decoration.)
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

function toggle(value, checked) {
    form.permissions = checked
        ? [...new Set([...form.permissions, value])]
        : form.permissions.filter((p) => p !== value);
}

function isChecked(value) {
    return form.permissions.includes(value);
}

function submit() {
    form.put(route('users.permissions', props.user.uuid), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

const grantedCount = computed(() => form.permissions.length);
const totalCount = computed(() =>
    Object.values(props.groups).reduce((n, items) => n + items.length, 0),
);
</script>

<template>
    <Sheet v-model:open="open">
        <!--
            The width override has to carry the same data-[side=right] scope the
            component's own class uses. A plain `sm:max-w-md` loses: the base sets
            `data-[side=right]:sm:max-w-sm`, and an attribute-scoped selector
            outranks a bare class no matter which is written last.
        -->
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 p-0 data-[side=right]:sm:max-w-md"
        >
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
                        {{ __('Ticked by default from the :role role. Change any of them for this person only — the role is just the starting point.', { role: user.role }) }}
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
                                class="mt-0.5"
                                @update:model-value="toggle(item.value, $event)"
                            />
                            <div class="min-w-0">
                                <Label
                                    :for="`perm_${item.value}`"
                                    class="cursor-pointer text-sm font-medium"
                                >
                                    {{ item.label }}
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
                        {{ __(':count of :total granted', { count: grantedCount, total: totalCount }) }}
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
