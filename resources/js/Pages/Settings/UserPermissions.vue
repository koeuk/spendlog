<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import FormActions from '@/Components/FormActions.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * The permissions editor, promoted from a right-side sheet to its own screen.
 *
 * Twenty-nine checkboxes across seven groups is roughly 1900px of content. As a
 * sheet that was a scroller inside a modal, with the granted count and the Save
 * button pinned in a footer the list slid under — on a phone the only way to
 * check your work was to scroll a panel inside a page.
 *
 * Seeded straight from props rather than through a watch: a route hands over one
 * user per visit, so there is no case where the record changes underneath.
 */
const props = defineProps({
    user: { type: Object, required: true },
    permission_groups: { type: Object, required: true },
});

const form = useForm({
    permissions: [...(props.user.direct_permissions ?? [])],
});

function toggle(value, checked) {
    form.permissions = checked
        ? [...new Set([...form.permissions, value])]
        : form.permissions.filter((p) => p !== value);
}

function isChecked(value) {
    return form.permissions.includes(value);
}

const grantedCount = computed(() => form.permissions.length);
const totalCount = computed(() =>
    Object.values(props.permission_groups).reduce((n, items) => n + items.length, 0),
);

const backHref = route('users.index');

function submit() {
    form.put(route('users.permissions', props.user.uuid));
}
</script>

<template>
    <Head :title="trans('Permissions')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="__('Permissions')"
        :back-label="__('Back to users')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div
                class="mb-5 flex items-start gap-2 rounded-2xl bg-neutral-50 p-3 text-xs dark:bg-neutral-800/60"
                :class="MUTED"
            >
                <Info class="mt-0.5 size-3.5 shrink-0" />
                <p>
                    {{ __('Ticked by default from the :role role. Change any of them for this person only — the role is just the starting point.', { role: user.role }) }}
                </p>
            </div>

            <!-- The count moves out of the footer and into the body: the action
                 bar is fixed to the viewport now, and a running total belongs
                 with the list it counts rather than under the buttons. -->
            <p class="mb-4 text-sm font-medium">
                {{ __('What :name can do beyond their own records.', { name: user.name }) }}
                <span class="ms-1 text-xs font-normal" :class="MUTED">
                    {{ __(':count of :total granted', { count: grantedCount, total: totalCount }) }}
                </span>
            </p>

            <div v-for="(items, group) in permission_groups" :key="group" class="mb-6 last:mb-0">
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

            <FormActions>
                <template #cancel>
                    <Button :as="Link" :href="backHref" variant="outline" class="w-full max-sm:h-12 sm:w-auto">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
